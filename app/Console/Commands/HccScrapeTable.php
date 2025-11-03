<?php

namespace App\Console\Commands;

use App\Services\HccDuskScraper;
use Facebook\WebDriver\Chrome\ChromeOptions;
use Facebook\WebDriver\Remote\DesiredCapabilities;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Illuminate\Console\Command;
use Laravel\Dusk\Browser;

class HccScrapeTable extends Command
{
    protected $signature = 'hcc:scrape-table {--from=2025-10-01} {--to=2025-10-31}';
    protected $description = 'Scrape HCC attendance data directly from table';

    public function handle()
    {
        $from = $this->option('from');
        $to = $this->option('to');

        $this->info("Scraping HCC attendance table data from {$from} to {$to}...");

        $driver = $this->createDriver();
        $browser = new Browser($driver);

        try {
            // Step 1: Start at overview page
            $this->info("Step 1: Starting at overview page...");
            $browser->visit('https://www.hik-connect.com/views/login/index.html#/main/overview')
                ->pause(3000);

            // Step 2: Navigate directly to login page (since clicking button isn't working)
            $this->info("Step 2: Navigating to login page...");
            $browser->visit('https://www.hik-connect.com/views/login/index.html#/login')
                ->pause(3000);

            // Step 3: Check we're on login page
            $currentUrl = $driver->getCurrentURL();
            $this->info("Step 3: Current URL: " . $currentUrl);

            // Step 4: Click Phone tab
            $this->info("Step 4: Clicking Phone tab...");
            $browser->script("
                var phoneTab = document.querySelector('#HCBLogin > div > div:nth-child(1) > div.login-container > div > nav > div.login-form > form > div:nth-child(1) > div > div > label:nth-child(2)');
                if (phoneTab) {
                    console.log('[HCC] Clicking phone tab');
                    phoneTab.click();
                } else {
                    console.log('[HCC] Phone tab not found');
                }
            ");
            $browser->pause(2000);

            // Step 5: Fill phone number
            $this->info("Step 5: Filling phone number...");
            $browser->script("
                var phoneInput = document.querySelector('#HCBLogin > div > div:nth-child(1) > div.login-container > div > nav > div.login-form > form > div:nth-child(2) > div > div > input');
                if (phoneInput) {
                    console.log('[HCC] Filling phone number');
                    phoneInput.value = '03322414255';
                    phoneInput.dispatchEvent(new Event('input', { bubbles: true }));
                    phoneInput.dispatchEvent(new Event('change', { bubbles: true }));
                } else {
                    console.log('[HCC] Phone input not found');
                }
            ");
            $browser->pause(1000);

            // Step 6: Fill password
            $this->info("Step 6: Filling password...");
            $browser->script("
                var passwordInput = document.querySelector('input[type=\"password\"]');
                if (passwordInput) {
                    console.log('[HCC] Filling password');
                    passwordInput.value = 'Alrehman123';
                    passwordInput.dispatchEvent(new Event('input', { bubbles: true }));
                    passwordInput.dispatchEvent(new Event('change', { bubbles: true }));
                } else {
                    console.log('[HCC] Password input not found');
                }
            ");
            $browser->pause(1000);

            // Step 7: Click login button
            $this->info("Step 7: Clicking login button...");
            $browser->script("
                var loginSubmitBtn = document.querySelector('#HCBLogin > div > div:nth-child(1) > div.login-container > div > nav > div.login-form > form > div:nth-child(5) > div > button');
                if (loginSubmitBtn) {
                    console.log('[HCC] Clicking login submit button');
                    loginSubmitBtn.click();
                } else {
                    console.log('[HCC] Login submit button not found');
                }
            ");
            $browser->pause(4000);

            // Step 8: Navigate directly to attendance transaction page
            $this->info("Step 8: Navigating directly to attendance transaction page...");
            $browser->visit('https://www.hik-connect.com/views/login/index.html#/Attendance/Transaction')
                ->pause(10000); // Wait longer for page to load

            // Step 9: Check if we're logged in by going to overview first
            $this->info("Step 9: Checking login status...");
            $browser->visit('https://www.hik-connect.com/views/login/index.html#/main/overview')
                ->pause(5000);

            $currentUrl = $driver->getCurrentURL();
            $this->info("Overview URL: " . $currentUrl);

            // Check if we're actually logged in
            $loginStatus = $browser->script("
                var userInfo = document.querySelector('.user-info, .username, [class*=\"user\"]');
                var logoutBtn = document.querySelector('.logout, [class*=\"logout\"]');
                return {
                    hasUserInfo: userInfo ? 'Yes' : 'No',
                    hasLogoutBtn: logoutBtn ? 'Yes' : 'No',
                    pageText: document.body.textContent.substring(0, 100)
                };
            ");

            $this->info("Login status: " . json_encode($loginStatus));

            // Step 10: Navigate to attendance page
            $this->info("Step 10: Navigating to attendance page...");
            $browser->visit('https://www.hik-connect.com/views/login/index.html#/Attendance/Transaction')
                ->pause(10000); // Wait longer for Vue app to load

            // Check current URL and page state
            $currentUrl = $driver->getCurrentURL();
            $this->info("Current URL: " . $currentUrl);

            // Wait for page content to load
            $this->info("Waiting for page content to load...");
            $browser->pause(5000);

            // Check for loading indicators
            $loadingInfo = $browser->script("
                var loading = document.querySelector('.el-loading-mask, .loading, [class*=\"loading\"]');
                var vueApp = document.querySelector('#app, [id*=\"app\"]');
                var content = document.querySelector('.el-table, .content, main');

                return {
                    hasLoading: loading ? 'Yes' : 'No',
                    hasVueApp: vueApp ? 'Yes' : 'No',
                    hasContent: content ? 'Yes' : 'No',
                    bodyText: document.body.textContent.length,
                    allElements: document.querySelectorAll('*').length
                };
            ");

            $this->info("Loading info: " . json_encode($loadingInfo));

            // Check for table elements
            $tableInfo = $browser->script("
                var tables = document.querySelectorAll('table, .el-table, .el-table__body');
                var info = {
                    tableCount: tables.length,
                    tbodyRows: document.querySelectorAll('tbody tr').length,
                    allRows: document.querySelectorAll('tr').length,
                    pageText: document.body.textContent.substring(0, 200)
                };
                return info;
            ");

            $this->info("Table info: " . json_encode($tableInfo));

            // Wait for table to load and scrape data
            $this->info("Scraping table data...");
            $tableData = $browser->script("
                var rows = document.querySelectorAll('tbody tr');
                var data = [];

                console.log('[HCC] Found ' + rows.length + ' table rows');

                // Debug: log first row structure
                if (rows.length > 0) {
                    var firstRowCells = rows[0].querySelectorAll('td');
                    console.log('[HCC] First row has ' + firstRowCells.length + ' cells');
                    for (var j = 0; j < firstRowCells.length; j++) {
                        console.log('[HCC] Cell ' + j + ': ' + firstRowCells[j].textContent.trim());
                    }
                }

                for (var i = 0; i < rows.length; i++) {
                    var cells = rows[i].querySelectorAll('td');
                    if (cells.length >= 12) {
                        var row = {
                            firstName: cells[0].textContent.trim(),
                            lastName: cells[1].textContent.trim(),
                            personCode: cells[2].textContent.trim(),
                            department: cells[3].textContent.trim(),
                            clockDate: cells[4].textContent.trim(),
                            clockTime: cells[5].textContent.trim(),
                            weekday: cells[6].textContent.trim(),
                            dataSource: cells[7].textContent.trim(),
                            deviceName: cells[8].textContent.trim(),
                            deviceSerial: cells[9].textContent.trim(),
                            punchState: cells[10].textContent.trim(),
                            location: cells[11].textContent.trim()
                        };
                        data.push(row);
                    }
                }

                console.log('[HCC] Scraped ' + data.length + ' records');
                return data;
            ");

            if ($tableData && count($tableData) > 0) {
                $this->info("✓ Successfully scraped " . count($tableData) . " records from table!");

                // Save to database
                $this->saveAttendanceData($tableData);

            } else {
                $this->warn("No table data found. Checking page state...");

                // Check if we're on the right page
                $currentUrl = $driver->getCurrentURL();
                $this->info("Current URL: " . $currentUrl);

                // Check for table element
                $tableExists = $browser->script("
                    var table = document.querySelector('table, .el-table');
                    return table ? 'Table found' : 'No table found';
                ");
                $this->info("Table status: " . $tableExists);
            }

            // Check console logs for debugging
            $logs = $driver->manage()->getLog('browser');
            $this->line("Browser Console:");
            foreach (array_slice($logs, -10) as $log) {
                if (strpos($log['message'], '[HCC]') !== false) {
                    $this->line($log['message']);
                }
            }

        } catch (\Exception $e) {
            $this->error("Error: " . $e->getMessage());
            return Command::FAILURE;
        } finally {
            $browser->quit();
        }

        return Command::SUCCESS;
    }

    protected function saveAttendanceData($records)
    {
        $this->info("Saving " . count($records) . " attendance records...");

        foreach ($records as $index => $record) {
            try {
                $this->line("Record " . ($index + 1) . ": " . json_encode($record));

                \App\Models\HccAttendanceTransaction::updateOrCreate(
                    [
                        'person_code' => $record['personCode'] ?? 'unknown',
                        'attendance_date' => $record['clockDate'] ?? date('Y-m-d'),
                        'attendance_time' => $record['clockTime'] ?? '00:00',
                        'device_id' => $record['deviceSerial'] ?? null,
                    ],
                    [
                        'full_name' => trim(($record['firstName'] ?? '') . ' ' . ($record['lastName'] ?? '')),
                        'department' => $record['department'] ?? 'Unknown',
                        'device_name' => $record['deviceName'] ?? null,
                        'device_serial' => $record['deviceSerial'] ?? null,
                        'weekday' => $record['weekday'] ?? 'Unknown',
                        'source_data' => json_encode($record),
                    ]
                );
            } catch (\Exception $e) {
                $this->warn("Failed to save record " . ($index + 1) . ": " . $e->getMessage());
            }
        }

        $this->info("✓ Data saved successfully!");
    }

    protected function createDriver()
    {
        $options = (new ChromeOptions)->addArguments([
            '--disable-gpu',
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
