<?php

namespace App\Console\Commands;

use App\Models\HccAttendanceTransaction;
use App\Services\HccDuskScraper;
use Carbon\Carbon;
use Facebook\WebDriver\Chrome\ChromeOptions;
use Facebook\WebDriver\Remote\DesiredCapabilities;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Laravel\Dusk\Browser;

class HccScrapeApiDirect extends Command
{
    protected $signature = 'hcc:scrape-api-direct {--from=} {--to=}';
    protected $description = 'Scrape HCC using Dusk to get cookies, then make direct API calls';

    public function handle()
    {
        $from = $this->option('from') ?: Carbon::yesterday()->format('Y-m-d');
        $to = $this->option('to') ?: Carbon::today()->format('Y-m-d');

        $this->info("Scraping HCC attendance from {$from} to {$to} using API...");

        $scraper = app(HccDuskScraper::class);
        $driver = $this->createDriver();
        $browser = new Browser($driver);

        try {
            // Step 1: Login with Dusk to get authenticated session
            $this->info("Step 1: Logging in to get session...");
            $browser->visit(config('hcc.dusk_login_url'));
            $scraper->login($browser);

            $browser->pause(3000);

            // Step 2: Navigate to attendance page (this ensures we're on the right domain)
            $this->info("Step 2: Navigating to attendance page...");
            $scraper->navigateToAttendance($browser);

            $browser->pause(10000); // Wait longer for Vue app to initialize

            // Step 3: Trigger the page's own search to establish API session
            $this->info("Step 3: Triggering page search to establish API session...");
            $browser->script("
                // Click any search button to trigger the page's own API call
                var searchBtn = document.querySelector('.el-button--primary, button[type=submit]');
                if (searchBtn) {
                    console.log('[HCC] Clicking search button to establish API session');
                    searchBtn.click();
                }
            ");

            $browser->pause(5000); // Wait for the page's API call to complete

            // Step 4: Now make our own API call (session should be established)
            $this->info("Step 4: Making API call from authenticated browser session...");

            $payload = [
                'page' => 1,
                'pageSize' => 100,
                'language' => 'en',
                'reportTypeId' => 1,
                'columnIdList' => [],
                'filterList' => [
                    [
                        'columnName' => 'fullName',
                        'operation' => 'LIKE',
                        'value' => ''
                    ],
                    [
                        'columnName' => 'personCode',
                        'operation' => 'LIKE',
                        'value' => ''
                    ],
                    [
                        'columnName' => 'groupId',
                        'operation' => 'IN',
                        'value' => ''
                    ],
                    [
                        'columnName' => 'clockStamp',
                        'operation' => 'BETWEEN',
                        'value' => "{$from}T00:00:00+05:00,{$to}T23:59:59+05:00"
                    ],
                    [
                        'columnName' => 'deviceId',
                        'operation' => 'IN',
                        'value' => ''
                    ]
                ]
            ];

            $this->line("Payload: " . json_encode($payload, JSON_PRETTY_PRINT));

            // Use the browser to make the API call (keeps authentication)
            $payloadJson = json_encode($payload);
            $apiResponse = $browser->script("
                return new Promise((resolve, reject) => {
                    fetch('https://isgp-team.hikcentralconnect.com/hcc/hccattendance/report/v1/list', {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json, text/plain, */*',
                            'Content-Type': 'application/json',
                            'clientsource': '0'
                        },
                        body: '{$payloadJson}',
                        credentials: 'include'
                    })
                    .then(response => response.json())
                    .then(data => resolve(data))
                    .catch(error => reject(error.toString()));
                });
            ");

            $this->line("API Response: " . json_encode($apiResponse, JSON_PRETTY_PRINT));

            if (isset($apiResponse['reportDataList']) && is_array($apiResponse['reportDataList'])) {
                $records = $apiResponse['reportDataList'];
                $this->info("✓ Found " . count($records) . " attendance records!");

                // Process and save
                $this->processRecords($records);

                $this->info("✓ Data saved successfully!");
            } else if (isset($apiResponse['errorCode'])) {
                $this->error("API Error: " . ($apiResponse['message'] ?? 'Unknown error'));
                $this->line("Error Code: " . $apiResponse['errorCode']);
            } else {
                $this->warn("No records found in response");
                $this->line("Response keys: " . implode(', ', array_keys($apiResponse)));
            }

        } catch (\Exception $e) {
            $this->error("Error: " . $e->getMessage());
            Log::error('HCC API Direct scrape failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return Command::FAILURE;
        } finally {
            $browser->quit();
        }

        return Command::SUCCESS;
    }

    protected function processRecords($records)
    {
        $inserted = 0;
        $skipped = 0;

        foreach ($records as $record) {
            // Extract fields from the record
            $personCode = $record['personCode'] ?? '';
            $fullName = $record['fullName'] ?? '';
            $department = $record['fullPath'] ?? $record['department'] ?? '';
            $clockDate = $record['clockDate'] ?? '';
            $clockTime = $record['clockTime'] ?? '';
            $deviceId = $record['deviceId'] ?? '';
            $deviceName = $record['deviceName'] ?? '';
            $deviceSerial = $record['deviceSerial'] ?? '';
            $week = $record['week'] ?? '';

            // Check for duplicates
            $exists = HccAttendanceTransaction::where('person_code', $personCode)
                ->where('attendance_date', $clockDate)
                ->where('attendance_time', $clockTime)
                ->exists();

            if ($exists) {
                $skipped++;
                continue;
            }

            // Create record
            HccAttendanceTransaction::create([
                'person_code' => $personCode,
                'full_name' => $fullName,
                'department' => $department,
                'attendance_date' => $clockDate,
                'attendance_time' => $clockTime,
                'device_id' => $deviceId,
                'device_name' => $deviceName,
                'device_serial' => $deviceSerial,
                'weekday' => $week,
                'source_data' => json_encode($record),
            ]);

            $inserted++;
        }

        $this->info("Inserted: {$inserted}, Skipped (duplicates): {$skipped}");
    }

    protected function createDriver()
    {
        $options = (new ChromeOptions)->addArguments([
            '--disable-gpu',
            '--headless',
            '--no-sandbox',
            '--disable-dev-shm-usage',
            '--window-size=1920,1080',
        ]);

        return RemoteWebDriver::create(
            config('hcc.dusk_driver_url', 'http://localhost:9515'),
            DesiredCapabilities::chrome()->setCapability(
                ChromeOptions::CAPABILITY, $options
            )
        );
    }
}

