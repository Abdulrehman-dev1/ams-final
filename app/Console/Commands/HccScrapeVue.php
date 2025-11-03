<?php

namespace App\Console\Commands;

use App\Models\HccAttendanceTransaction;
use App\Services\HccDuskScraper;
use Carbon\Carbon;
use Facebook\WebDriver\Chrome\ChromeOptions;
use Facebook\WebDriver\Remote\DesiredCapabilities;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Laravel\Dusk\Browser;

class HccScrapeVue extends Command
{
    protected $signature = 'hcc:scrape-vue {--from=} {--to=}';
    protected $description = 'Scrape HCC by intercepting Vue data after page loads';

    public function handle()
    {
        $from = $this->option('from') ?: '2025-10-17';
        $to = $this->option('to') ?: '2025-10-17';

        $this->info("Scraping HCC attendance from {$from} to {$to} by waiting for Vue data...");

        $scraper = app(HccDuskScraper::class);
        $driver = $this->createDriver();
        $browser = new Browser($driver);

        try {
            // Step 1: Login
            $this->info("Step 1: Logging in...");
            $browser->visit(config('hcc.dusk_login_url'));
            $scraper->login($browser);
            $browser->pause(3000);

            // Step 2: Navigate to attendance
            $this->info("Step 2: Navigating to attendance page...");
            $scraper->navigateToAttendance($browser);
            $browser->pause(5000);

            // Step 3: Set date range and trigger search
            $this->info("Step 3: Setting date range and waiting for data to load...");

            // Wait much longer for the page and Vue to fully initialize
            $browser->pause(10000);

            // Try to extract data from the loaded page
            $this->info("Step 4: Extracting data from page...");

            $data = $browser->script("
                // Try multiple ways to get the data
                var result = {
                    method: '',
                    data: []
                };

                // Method 1: Check if Vue instance has data
                if (window.__NUXT__ && window.__NUXT__.data) {
                    result.method = 'nuxt';
                    result.data = window.__NUXT__.data;
                }
                // Method 2: Check Vue root instance
                else if (window.__VUE_DEVTOOLS_GLOBAL_HOOK__ && window.__VUE_DEVTOOLS_GLOBAL_HOOK__.store) {
                    result.method = 'devtools';
                    result.data = window.__VUE_DEVTOOLS_GLOBAL_HOOK__.store.state;
                }
                // Method 3: Look for table data in DOM
                else {
                    result.method = 'dom';
                    var tableData = [];
                    var rows = document.querySelectorAll('.el-table__body tr, table tbody tr');

                    for (var i = 0; i < rows.length; i++) {
                        var cells = rows[i].querySelectorAll('td');
                        var rowData = {};

                        // Try to map cells to fields
                        if (cells.length >= 7) {
                            rowData = {
                                date: cells[0]?.textContent?.trim() || '',
                                time: cells[1]?.textContent?.trim() || '',
                                personCode: cells[2]?.textContent?.trim() || '',
                                fullName: cells[3]?.textContent?.trim() || '',
                                department: cells[4]?.textContent?.trim() || '',
                                deviceName: cells[5]?.textContent?.trim() || '',
                                week: cells[6]?.textContent?.trim() || ''
                            };

                            if (rowData.personCode) {
                                tableData.push(rowData);
                            }
                        }
                    }
                    result.data = tableData;
                }

                console.log('[HCC] Extraction method:', result.method);
                console.log('[HCC] Data found:', result.data);

                return result;
            ");

            $this->line("Extraction method: " . ($data['method'] ?? 'unknown'));
            $this->line("Data: " . json_encode($data, JSON_PRETTY_PRINT));

            if (isset($data['data']) && is_array($data['data']) && count($data['data']) > 0) {
                $records = $data['data'];
                $this->info("✓ Found " . count($records) . " records!");

                // Process records
                $this->processRecords($records);

                $this->info("✓ Data saved successfully!");
            } else {
                $this->warn("No data found");

                // Debug: Take screenshot
                $screenshot = $browser->screenshot('hcc-debug');
                $this->line("Screenshot saved to: tests/Browser/screenshots/{$screenshot}");
            }

        } catch (\Exception $e) {
            $this->error("Error: " . $e->getMessage());
            Log::error('HCC Vue scrape failed', [
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
            if (!is_array($record)) continue;

            // Handle both API format and DOM format
            $personCode = $record['personCode'] ?? $record['person_code'] ?? '';
            $fullName = $record['fullName'] ?? $record['full_name'] ?? '';
            $department = $record['fullPath'] ?? $record['department'] ?? '';
            $date = $record['clockDate'] ?? $record['date'] ?? '';
            $time = $record['clockTime'] ?? $record['time'] ?? '';
            $deviceId = $record['deviceId'] ?? $record['device_id'] ?? '';
            $deviceName = $record['deviceName'] ?? $record['device_name'] ?? '';
            $deviceSerial = $record['deviceSerial'] ?? $record['device_serial'] ?? '';
            $week = $record['week'] ?? $record['weekday'] ?? '';

            if (empty($personCode) || empty($date)) {
                continue;
            }

            // Check for duplicates
            $exists = HccAttendanceTransaction::where('person_code', $personCode)
                ->where('attendance_date', $date)
                ->where('attendance_time', $time)
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
                'attendance_date' => $date,
                'attendance_time' => $time,
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


