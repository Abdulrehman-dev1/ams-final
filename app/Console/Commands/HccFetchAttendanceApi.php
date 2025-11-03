<?php

namespace App\Console\Commands;

use App\Models\HccAttendanceTransaction;
use App\Services\HccClient;
use App\Services\HccDuskScraper;
use Carbon\Carbon;
use Facebook\WebDriver\Chrome\ChromeOptions;
use Facebook\WebDriver\Remote\DesiredCapabilities;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Laravel\Dusk\Browser;

class HccFetchAttendanceApi extends Command
{
    protected $signature = 'hcc:fetch-attendance {--from= : Start date (Y-m-d)} {--to= : End date (Y-m-d)} {--page=1 : Page number} {--page-size=20 : Records per page}';
    protected $description = 'Fetch attendance data from HCC API with exact payload structure';

    public function handle()
    {
        $fromDate = $this->option('from') ?: Carbon::today()->startOfMonth()->format('Y-m-d');
        $toDate = $this->option('to') ?: Carbon::today()->endOfMonth()->format('Y-m-d');
        $page = (int) $this->option('page');
        $pageSize = (int) $this->option('page-size');

        $this->info("📅 Date Range: {$fromDate} to {$toDate}");
        $this->info("📄 Page: {$page}, Page Size: {$pageSize}");
        $this->info("");

        // Step 1: Get cookies via Dusk login
        $this->info("🔐 Step 1: Logging in to get cookies...");
        $cookieString = $this->loginAndGetCookies();

        if (!$cookieString) {
            $this->error("❌ Failed to get authentication cookies");
            return Command::FAILURE;
        }

        $this->info("✅ Got authentication cookies");
        $this->info("");

        // Step 2: Make API call with cookies
        $this->info("🌐 Step 2: Fetching attendance data from API...");

        // Set cookies in config for HccClient
        config(['hcc.cookie' => $cookieString]);

        try {
            $client = app(HccClient::class);

            // Prepare dates in the exact format HCC expects
            $timezone = config('hcc.timezone', 'Asia/Karachi');
            $from = Carbon::parse($fromDate)->setTimezone($timezone)->startOfDay();
            $to = Carbon::parse($toDate)->setTimezone($timezone)->endOfDay();

            $fromIso = $from->format('Y-m-d\TH:i:sP');
            $toIso = $to->format('Y-m-d\TH:i:sP');

            $this->info("📡 API Endpoint: " . config('hcc.base_url') . config('hcc.endpoints.attendance_list'));
            $this->info("📤 Payload:");

            $payload = [
                'page' => $page,
                'pageSize' => $pageSize,
                'language' => 'en',
                'reportTypeId' => 1,
                'columnIdList' => [],
                'filterList' => [
                    ['columnName' => 'fullName', 'operation' => 'LIKE', 'value' => ''],
                    ['columnName' => 'personCode', 'operation' => 'LIKE', 'value' => ''],
                    ['columnName' => 'groupId', 'operation' => 'IN', 'value' => ''],
                    ['columnName' => 'clockStamp', 'operation' => 'BETWEEN', 'value' => "{$fromIso},{$toIso}"],
                    ['columnName' => 'deviceId', 'operation' => 'IN', 'value' => ''],
                ],
            ];

            $this->line(json_encode($payload, JSON_PRETTY_PRINT));
            $this->info("");

            // Make the API call
            $response = $client->attendanceList($page, $pageSize, $fromIso, $toIso);

            $this->info("✅ API call successful!");
            $this->info("");

            // Display response summary
            $this->displayResponse($response);

            // Extract and save attendance records
            $records = $this->extractRecords($response);

            if (!empty($records)) {
                $savedCount = $this->saveRecords($records);
                $this->info("💾 Saved {$savedCount} attendance records to database");
            } else {
                $this->warn("⚠️  No attendance records found in the response");
            }

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error("❌ API Error: " . $e->getMessage());
            Log::error("HCC API Fetch Error", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return Command::FAILURE;
        }
    }

    protected function loginAndGetCookies(): ?string
    {
        $driver = $this->createDriver(true); // Headless mode
        $browser = new Browser($driver);

        try {
            $scraper = app(HccDuskScraper::class);

            // Login to hik-connect.com
            $scraper->login($browser);

            $this->info("  → Logged in, now navigating to HCC domain...");

            // Try to navigate to the attendance page at hikcentralconnect.com
            // The URL structure might be similar to the API base
            $hccUrl = "https://isgp-team.hikcentralconnect.com";
            $browser->visit($hccUrl);
            $browser->pause(5000);

            // Check if we need to navigate further
            $currentUrl = $driver->getCurrentURL();
            $this->info("  → Current URL after HCC visit: {$currentUrl}");

            // If still on hik-connect, try using the scraper's navigation
            if (str_contains($currentUrl, 'hik-connect.com')) {
                $this->info("  → Still on hik-connect, trying navigation through UI...");
                $scraper->navigateToAttendance($browser);
                $browser->pause(8000);

                $currentUrl = $driver->getCurrentURL();
                $this->info("  → Current URL after UI navigation: {$currentUrl}");
            }

            // Get all cookies from the current page
            $cookies = $driver->manage()->getCookies();

            $this->info("  → Total cookies found: " . count($cookies));

            // Build cookie string with ALL cookies (don't filter)
            $cookieString = '';
            $cookieDetails = [];

            foreach ($cookies as $cookie) {
                $cookieString .= "{$cookie['name']}={$cookie['value']}; ";
                $domain = $cookie['domain'] ?? 'unknown';
                $cookieDetails[] = "{$cookie['name']} ({$domain})";
            }
            $cookieString = rtrim($cookieString, '; ');

            $this->info("  ✓ Got " . count($cookieDetails) . " cookies:");
            foreach ($cookieDetails as $detail) {
                $this->line("    - {$detail}");
            }

            return $cookieString;

        } catch (\Exception $e) {
            $this->error("  ✗ Login failed: " . $e->getMessage());
            Log::error("Cookie fetch error", ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return null;
        } finally {
            $browser->quit();
        }
    }

    protected function createDriver($headless = true)
    {
        $args = [
            '--disable-gpu',
            '--no-sandbox',
            '--disable-dev-shm-usage',
            '--window-size=1920,1080',
        ];

        if ($headless) {
            $args[] = '--headless';
        }

        $options = (new ChromeOptions)->addArguments($args);

        return RemoteWebDriver::create(
            config('hcc.dusk_driver_url', 'http://localhost:9515'),
            DesiredCapabilities::chrome()->setCapability(
                ChromeOptions::CAPABILITY, $options
            )
        );
    }

    protected function displayResponse(array $response)
    {
        $this->info("📥 Full Response:");
        $this->line(json_encode($response, JSON_PRETTY_PRINT));
        $this->info("");

        $this->info("Response Summary:");
        $this->line("  Structure keys: " . json_encode(array_keys($response)));

        // Check for error
        if (isset($response['errorCode']) && $response['errorCode'] !== 0 && $response['errorCode'] !== '0') {
            $this->warn("  ⚠️  API returned error code: " . $response['errorCode']);
            if (isset($response['message'])) {
                $this->warn("  Message: " . $response['message']);
            }
        }

        // Try different possible response structures
        if (isset($response['data'])) {
            $this->line("  Has 'data' key: Yes");

            if (isset($response['data']['list']) && is_array($response['data']['list'])) {
                $this->line("  Record count (data.list): " . count($response['data']['list']));
                if (!empty($response['data']['list'])) {
                    $this->info("\n  📋 Sample Record:");
                    $this->line(json_encode($response['data']['list'][0], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
                }
            } elseif (is_array($response['data'])) {
                $this->line("  Record count (data): " . count($response['data']));
            }

            if (isset($response['data']['total'])) {
                $this->line("  Total records available: " . $response['data']['total']);
            }
        } elseif (isset($response['list']) && is_array($response['list'])) {
            $this->line("  Record count (list): " . count($response['list']));
        }

        $this->info("");
    }

    protected function extractRecords(array $response): array
    {
        // Try different possible response structures
        if (isset($response['data']['list']) && is_array($response['data']['list'])) {
            return $response['data']['list'];
        } elseif (isset($response['data']) && is_array($response['data'])) {
            return $response['data'];
        } elseif (isset($response['list']) && is_array($response['list'])) {
            return $response['list'];
        }

        return [];
    }

    protected function saveRecords(array $records): int
    {
        $saved = 0;
        $timezone = config('hcc.timezone', 'Asia/Karachi');

        foreach ($records as $record) {
            try {
                $personCode = $record['personCode'] ?? $record['person_code'] ?? null;
                $fullName = $record['fullName'] ?? $record['full_name'] ?? 'Unknown';
                $clockStamp = $record['clockStamp'] ?? $record['attendance_datetime'] ?? null;

                if (!$personCode || !$clockStamp) {
                    continue;
                }

                $dt = Carbon::parse($clockStamp)->setTimezone($timezone);

                HccAttendanceTransaction::updateOrCreate(
                    [
                        'person_code' => $personCode,
                        'attendance_date' => $dt->format('Y-m-d'),
                        'attendance_time' => $dt->format('H:i:s'),
                    ],
                    [
                        'full_name' => $fullName,
                        'department' => $record['groupName'] ?? $record['department'] ?? null,
                        'device_id' => $record['deviceId'] ?? $record['device'] ?? null,
                        'device_name' => $record['deviceName'] ?? null,
                        'device_serial' => $record['deviceSerial'] ?? null,
                        'weekday' => $record['weekday'] ?? $dt->format('l'),
                        'source_data' => $record,
                    ]
                );
                $saved++;
            } catch (\Exception $e) {
                Log::warning("Failed to save attendance record", [
                    'error' => $e->getMessage(),
                    'record' => $record,
                ]);
            }
        }

        return $saved;
    }
}

