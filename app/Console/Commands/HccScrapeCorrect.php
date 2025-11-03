<?php

namespace App\Console\Commands;

use Facebook\WebDriver\Chrome\ChromeOptions;
use Facebook\WebDriver\Remote\DesiredCapabilities;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Illuminate\Console\Command;
use Laravel\Dusk\Browser;

class HccScrapeCorrect extends Command
{
    protected $signature = 'hcc:scrape-correct {--from=2025-10-01} {--to=2025-10-31}';
    protected $description = 'HCC scraper following exact user steps';

    public function handle()
    {
        $from = $this->option('from');
        $to = $this->option('to');

        $this->info("🎯 HCC scraper following EXACT steps from {$from} to {$to}...");

        $driver = $this->createDriver();
        $browser = new Browser($driver);

        try {
            // Step 1: Navigate directly to login page
            $this->info("Step 1: Navigating to login page...");
            $browser->visit('https://www.hik-connect.com/views/login/index.html#/login')
                ->pause(3000);

            // Check if we're on login page
            $currentUrl = $driver->getCurrentURL();
            $this->info("Step 2: Current URL: " . $currentUrl);

            if (strpos($currentUrl, 'login') === false) {
                $this->error("❌ Not on login page! Current URL: " . $currentUrl);
                return Command::FAILURE;
            }

            // Step 4: Click Phone tab
            $this->info("Step 4: Clicking Phone tab...");
            $browser->script("
                var phoneTab = document.querySelector('#HCBLogin > div > div:nth-child(1) > div.login-container > div > nav > div.login-form > form > div:nth-child(1) > div > div > label:nth-child(2)');
                if (phoneTab) {
                    console.log('[HCC] Found phone tab, clicking...');
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
                    console.log('[HCC] Found phone input, filling...');
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
                    console.log('[HCC] Found password input, filling...');
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
                var loginBtn = document.querySelector('#HCBLogin > div > div:nth-child(1) > div.login-container > div > nav > div.login-form > form > div:nth-child(5) > div > button');
                if (loginBtn) {
                    console.log('[HCC] Found login button, clicking...');
                    loginBtn.click();
                } else {
                    console.log('[HCC] Login button not found');
                }
            ");
            $browser->pause(5000);

            // Check if login was successful
            $currentUrl = $driver->getCurrentURL();
            $this->info("Step 8: After login URL: " . $currentUrl);

            if (strpos($currentUrl, 'portal') === false && strpos($currentUrl, 'overview') === false) {
                $this->error("❌ Login failed! Still on: " . $currentUrl);

                // Check for error messages
                $errorMsg = $browser->script("
                    var error = document.querySelector('.el-message, .error, [class*=\"error\"]');
                    return error ? error.textContent : 'No error message found';
                ");

                $this->error("Error message: " . ($errorMsg[0] ?? 'None'));
                return Command::FAILURE;
            }

            $this->info("✅ Login successful!");

            // Step 9: Click Attendance tab
            $this->info("Step 9: Clicking Attendance tab...");
            $browser->script("
                var attendanceTab = document.querySelector('#tab-HCBAttendance');
                if (attendanceTab) {
                    console.log('[HCC] Found attendance tab, clicking...');
                    attendanceTab.click();
                } else {
                    console.log('[HCC] Attendance tab not found');
                }
            ");
            $browser->pause(2000);

            // Step 10: Click Attendance Records submenu
            $this->info("Step 10: Clicking Attendance Records submenu...");
            $browser->script("
                var attendanceRecords = document.querySelector('#navbase > div.nav-base-item.el-black-bg.el-row > div > div.page-scrollbar__wrap.el-scrollbar__wrap.el-scrollbar__wrap--hidden-default > div > ul > li.el-submenu.is-opened > div');
                if (attendanceRecords) {
                    console.log('[HCC] Found attendance records, clicking...');
                    attendanceRecords.click();
                } else {
                    console.log('[HCC] Attendance records not found');
                }
            ");
            $browser->pause(2000);

            // Step 11: Click Transaction submenu
            $this->info("Step 11: Clicking Transaction submenu...");
            $browser->script("
                var transactionMenu = document.querySelector('#navbase > div.nav-base-item.el-black-bg.el-row > div > div.page-scrollbar__wrap.el-scrollbar__wrap.el-scrollbar__wrap--hidden-default > div > ul > li.el-submenu.is-active.is-opened > ul > li.el-menu-item.second-menu.is-active');
                if (transactionMenu) {
                    console.log('[HCC] Found transaction menu, clicking...');
                    transactionMenu.click();
                } else {
                    console.log('[HCC] Transaction menu not found');
                }
            ");
            $browser->pause(5000);

            // Check final URL
            $currentUrl = $driver->getCurrentURL();
            $this->info("Step 12: Final URL: " . $currentUrl);

            // Wait for table to load
            $this->info("Step 13: Waiting for table to load...");
            $browser->pause(10000);

            // Check for table data
            $this->info("Step 14: Checking for table data...");
            $tableData = $browser->script("
                var rows = document.querySelectorAll('tbody tr, .el-table__body tr, table tr');
                var data = [];

                console.log('[HCC] Found ' + rows.length + ' table rows');

                // Debug: log first row structure
                if (rows.length > 0) {
                    var firstRowCells = rows[0].querySelectorAll('td');
                    console.log('[HCC] First row has ' + firstRowCells.length + ' cells');
                    for (var j = 0; j < firstRowCells.length; j++) {
                        console.log('[HCC] Cell ' + j + ': \"' + firstRowCells[j].textContent.trim() + '\"');
                    }
                }

                for (var i = 0; i < rows.length; i++) {
                    var cells = rows[i].querySelectorAll('td');
                    if (cells.length >= 6) {
                        var row = {
                            firstName: cells[0] ? cells[0].textContent.trim() : '',
                            lastName: cells[1] ? cells[1].textContent.trim() : '',
                            personCode: cells[2] ? cells[2].textContent.trim() : '',
                            department: cells[3] ? cells[3].textContent.trim() : '',
                            clockDate: cells[4] ? cells[4].textContent.trim() : '',
                            clockTime: cells[5] ? cells[5].textContent.trim() : '',
                            weekday: cells[6] ? cells[6].textContent.trim() : '',
                            dataSource: cells[7] ? cells[7].textContent.trim() : '',
                            deviceName: cells[8] ? cells[8].textContent.trim() : '',
                            deviceSerial: cells[9] ? cells[9].textContent.trim() : '',
                            punchState: cells[10] ? cells[10].textContent.trim() : '',
                            location: cells[11] ? cells[11].textContent.trim() : ''
                        };
                        data.push(row);
                    }
                }

                return data;
            ");

            if ($tableData && count($tableData) > 0) {
                $this->info("🎉 Successfully found " . count($tableData) . " attendance records!");

                // Save to database
                $this->saveAttendanceData($tableData);

                // Show sample data
                $this->info("Sample record: " . json_encode($tableData[0] ?? []));

            } else {
                $this->warn("❌ No table data found");

                // Debug info
                $debugInfo = $browser->script("
                    return {
                        url: window.location.href,
                        bodyText: document.body.textContent.length,
                        allElements: document.querySelectorAll('*').length,
                        tables: document.querySelectorAll('table').length,
                        rows: document.querySelectorAll('tr').length,
                        tbodyRows: document.querySelectorAll('tbody tr').length
                    };
                ");

                $this->info("Debug info: " . json_encode($debugInfo));
            }

        } catch (\Exception $e) {
            $this->error("❌ Error: " . $e->getMessage());
            return Command::FAILURE;
        } finally {
            $browser->quit();
        }

        return Command::SUCCESS;
    }

    protected function saveAttendanceData($records)
    {
        $this->info("💾 Saving " . count($records) . " attendance records...");

        $saved = 0;
        foreach ($records as $index => $record) {
            try {
                \App\Models\HccAttendanceTransaction::updateOrCreate(
                    [
                        'person_code' => $record['personCode'] ?? 'unknown_' . $index,
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
                $saved++;
            } catch (\Exception $e) {
                $this->warn("Failed to save record " . ($index + 1) . ": " . $e->getMessage());
            }
        }

        $this->info("✅ Successfully saved {$saved} records!");
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

