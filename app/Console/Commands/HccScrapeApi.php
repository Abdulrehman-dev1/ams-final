<?php

namespace App\Console\Commands;

use App\Services\HccDuskScraper;
use Facebook\WebDriver\Chrome\ChromeOptions;
use Facebook\WebDriver\Remote\DesiredCapabilities;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Illuminate\Console\Command;
use Laravel\Dusk\Browser;

class HccScrapeApi extends Command
{
    protected $signature = 'hcc:scrape-api {--from=} {--to=} {--page-size=100}';
    protected $description = 'Scrape HCC attendance using API interception with pagination';

    public function handle()
    {
        $from = $this->option('from') ?: '2025-10-01';
        $to = $this->option('to') ?: '2025-10-31';
        $pageSize = (int) $this->option('page-size');

        $this->info("Scraping HCC attendance data via API from {$from} to {$to} (pageSize: {$pageSize})...");

        $scraper = app(HccDuskScraper::class);

        $driver = $this->createDriver();
        $browser = new Browser($driver);

        try {
            // Login following exact flow
            $this->info("Step 1: Logging in...");
            $scraper->login($browser);

            // Navigate to attendance
            $this->info("Step 2: Navigating to attendance...");
            $scraper->navigateToAttendance($browser);

            // Inject API capture script with pagination
            $this->info("Step 3: Injecting API capture script...");
            $browser->script("
                window.__hccApiData = [];
                window.__hccCurrentPage = 1;
                window.__hccPageSize = {$pageSize};
                window.__hccTotalPages = 0;
                window.__hccHasMoreData = true;

                // Override XMLHttpRequest to capture API calls
                (function() {
                    var originalOpen = XMLHttpRequest.prototype.open;
                    var originalSend = XMLHttpRequest.prototype.send;

                    XMLHttpRequest.prototype.open = function(method, url, async, user, password) {
                        this._method = method;
                        this._url = url;
                        return originalOpen.apply(this, arguments);
                    };

                    XMLHttpRequest.prototype.send = function(data) {
                        var xhr = this;

                        xhr.addEventListener('readystatechange', function() {
                            if (xhr.readyState === 4 && xhr._url && xhr._url.includes('hccattendance/report/v1/list')) {
                                console.log('[HCC] API Response captured!');
                                console.log('[HCC] Status:', xhr.status);

                                try {
                                    var response = JSON.parse(xhr.responseText);
                                    if (response.reportDataList && response.reportDataList.length > 0) {
                                        console.log('[HCC] Found ' + response.reportDataList.length + ' records on page ' + window.__hccCurrentPage);

                                        // Store the data
                                        window.__hccApiData = window.__hccApiData.concat(response.reportDataList);
                                        window.__hccTotalPages = Math.ceil(response.totalCount / window.__hccPageSize);
                                        window.__hccHasMoreData = response.nextPageExists || false;

                                        console.log('[HCC] Total records so far:', window.__hccApiData.length);
                                        console.log('[HCC] Has more pages:', window.__hccHasMoreData);

                                        // If there are more pages, trigger next page
                                        if (window.__hccHasMoreData && window.__hccCurrentPage < window.__hccTotalPages) {
                                            setTimeout(function() {
                                                window.__hccCurrentPage++;
                                                console.log('[HCC] Loading page ' + window.__hccCurrentPage);

                                                // Trigger next page load
                                                var nextPageBtn = document.querySelector('.el-pagination .btn-next, .pagination .next, [aria-label=\"Next Page\"]');
                                                if (nextPageBtn) {
                                                    nextPageBtn.click();
                                                } else {
                                                    // Try to trigger API call with next page
                                                    var event = new Event('click');
                                                    document.dispatchEvent(event);
                                                }
                                            }, 2000);
                                        }
                                    } else {
                                        console.log('[HCC] No data in response');
                                    }
                                } catch (e) {
                                    console.log('[HCC] Failed to parse response:', e);
                                }
                            }
                        });

                        return originalSend.apply(this, arguments);
                    };
                })();
            ");

            // Wait for initial page load and API call
            $this->info("Step 4: Waiting for initial API call...");
            $browser->pause(5000);

            // Trigger search to load data
            $this->info("Step 5: Triggering search to load data...");
            $browser->script("
                // Look for search/filter button
                var searchBtn = document.querySelector('.el-button--primary, button[type=button]');
                if (searchBtn) {
                    console.log('[HCC] Found search button, clicking...');
                    searchBtn.click();
                } else {
                    console.log('[HCC] Search button not found');
                }
            ");

            // Wait for API data to load
            $this->info("Step 6: Waiting for API data...");
            $browser->pause(10000); // Wait 10 seconds for initial data

            // Check console logs
            $logs = $driver->manage()->getLog('browser');
            $this->line("Browser Console:");
            foreach (array_slice($logs, -10) as $log) {
                if (strpos($log['message'], '[HCC]') !== false) {
                    $this->line($log['message']);
                }
            }

            // Check status safely
            $status = $browser->script("
                return {
                    hasMoreData: window.__hccHasMoreData || false,
                    currentPage: window.__hccCurrentPage || 1,
                    totalPages: window.__hccTotalPages || 1,
                    totalRecords: window.__hccApiData ? window.__hccApiData.length : 0
                };
            ");

            if ($status && isset($status['currentPage'])) {
                $this->line("Status: Page {$status['currentPage']}/{$status['totalPages']} - Records: {$status['totalRecords']}");
            } else {
                $this->warn("Could not get status from browser");
            }

            // Get final data
            $this->info("Step 7: Processing final data...");
            $finalData = $browser->script('return window.__hccApiData;');

            if ($finalData && count($finalData) > 0) {
                $this->info("✓ Found " . count($finalData) . " total attendance records!");

                // Debug: Show first record structure
                $this->line("First record structure:");
                $this->line(json_encode($finalData[0], JSON_PRETTY_PRINT));

                // Process and save data with duplicate checking
                $this->info("Step 8: Processing and saving data...");
                $this->processApiData($finalData);

                $this->info("✓ Data processing completed!");
            } else {
                $this->warn("No API data captured");
            }

        } catch (\Exception $e) {
            $this->error("Error: " . $e->getMessage());
            return Command::FAILURE;
        } finally {
            $browser->quit();
        }

        return Command::SUCCESS;
    }

    protected function processApiData($apiData)
    {
        $inserted = 0;
        $skipped = 0;

        foreach ($apiData as $record) {
            // Debug: Show record structure
            $this->line("Processing record: " . json_encode($record));

            // Handle different possible field names
            $personCode = $record['personCode'] ?? $record['person_code'] ?? '';
            $fullName = $record['fullName'] ?? $record['full_name'] ?? '';
            $department = $record['fullPath'] ?? $record['department'] ?? '';
            $attendanceDate = $record['clockDate'] ?? $record['attendance_date'] ?? '';
            $attendanceTime = $record['clockTime'] ?? $record['attendance_time'] ?? '';
            $deviceId = $record['deviceId'] ?? $record['device_id'] ?? '';
            $deviceName = $record['deviceName'] ?? $record['device_name'] ?? '';
            $deviceSerial = $record['deviceSerial'] ?? $record['device_serial'] ?? '';
            $weekday = $record['week'] ?? $record['weekday'] ?? '';

            // Check if record already exists
            $existing = \App\Models\HccAttendanceTransaction::where('person_code', $personCode)
                ->where('attendance_date', $attendanceDate)
                ->where('attendance_time', $attendanceTime)
                ->first();

            if ($existing) {
                $skipped++;
                continue;
            }

            // Map API response to our database structure
            $dbRecord = [
                'person_code' => $personCode,
                'full_name' => $fullName,
                'department' => $department,
                'attendance_date' => $attendanceDate,
                'attendance_time' => $attendanceTime,
                'device_id' => $deviceId,
                'device_name' => $deviceName,
                'device_serial' => $deviceSerial,
                'weekday' => $weekday,
                'source_data' => json_encode($record),
            ];

            \App\Models\HccAttendanceTransaction::create($dbRecord);
            $inserted++;
        }

        $this->info("Records inserted: {$inserted}, Skipped (duplicates): {$skipped}");
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

