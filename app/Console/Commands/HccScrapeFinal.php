<?php

namespace App\Console\Commands;

use Facebook\WebDriver\Chrome\ChromeOptions;
use Facebook\WebDriver\Remote\DesiredCapabilities;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Illuminate\Console\Command;
use Laravel\Dusk\Browser;

class HccScrapeFinal extends Command
{
    protected $signature = 'hcc:scrape-final {--from=2025-10-01} {--to=2025-10-31}';
    protected $description = 'Final HCC attendance scraper with proper waiting';

    public function handle()
    {
        $from = $this->option('from');
        $to = $this->option('to');

        $this->info("🎯 Final HCC attendance scraper from {$from} to {$to}...");

        $driver = $this->createDriver();
        $browser = new Browser($driver);

        try {
            // Step 1: Login
            $this->info("Step 1: Logging in...");
            $browser->visit('https://www.hik-connect.com/views/login/index.html#/login')
                ->pause(3000);

            // Click Phone tab
            $browser->script("
                var phoneTab = document.querySelector('#HCBLogin > div > div:nth-child(1) > div.login-container > div > nav > div.login-form > form > div:nth-child(1) > div > div > label:nth-child(2)');
                if (phoneTab) phoneTab.click();
            ");
            $browser->pause(2000);

            // Fill credentials
            $browser->script("
                var phoneInput = document.querySelector('#HCBLogin > div > div:nth-child(1) > div.login-container > div > nav > div.login-form > form > div:nth-child(2) > div > div > input');
                var passwordInput = document.querySelector('input[type=\"password\"]');
                var loginBtn = document.querySelector('#HCBLogin > div > div:nth-child(1) > div.login-container > div > nav > div.login-form > form > div:nth-child(5) > div > button');

                if (phoneInput) {
                    phoneInput.value = '03322414255';
                    phoneInput.dispatchEvent(new Event('input', { bubbles: true }));
                }
                if (passwordInput) {
                    passwordInput.value = 'Alrehman123';
                    passwordInput.dispatchEvent(new Event('input', { bubbles: true }));
                }
                if (loginBtn) {
                    setTimeout(() => loginBtn.click(), 500);
                }
            ");
            $browser->pause(5000);

            // Step 2: Navigate to attendance page
            $this->info("Step 2: Navigating to attendance page...");
            $browser->visit('https://www.hik-connect.com/views/login/index.html#/Attendance/Transaction')
                ->pause(15000); // Wait longer for Vue app to load

            // Step 3: Wait for table to load and scrape
            $this->info("Step 3: Waiting for table to load...");

            // Try multiple times to get table data
            $maxAttempts = 5;
            $tableData = [];

            for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
                $this->info("Attempt {$attempt}/{$maxAttempts}: Checking for table data...");

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
                    $this->info("✅ Found " . count($tableData) . " records!");
                    break;
                }

                $this->info("No data yet, waiting 5 seconds...");
                $browser->pause(5000);
            }

            if ($tableData && count($tableData) > 0) {
                $this->info("🎉 Successfully scraped " . count($tableData) . " attendance records!");

                // Save to database
                $this->saveAttendanceData($tableData);

                // Show sample data
                $this->info("Sample record: " . json_encode($tableData[0] ?? []));

            } else {
                $this->warn("❌ No table data found after {$maxAttempts} attempts");

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

