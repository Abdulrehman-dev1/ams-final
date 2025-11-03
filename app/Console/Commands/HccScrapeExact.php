<?php

namespace App\Console\Commands;

use App\Services\HccDuskScraper;
use Facebook\WebDriver\Chrome\ChromeOptions;
use Facebook\WebDriver\Remote\DesiredCapabilities;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Illuminate\Console\Command;
use Laravel\Dusk\Browser;

class HccScrapeExact extends Command
{
    protected $signature = 'hcc:scrape-exact {--from=2025-10-01} {--to=2025-10-31}';
    protected $description = 'Scrape HCC attendance following exact user steps';

    public function handle()
    {
        $from = $this->option('from');
        $to = $this->option('to');

        $this->info("Scraping HCC attendance data from {$from} to {$to}...");

        $driver = $this->createDriver();
        $browser = new Browser($driver);

        try {
            // Step 1: Start at overview page
            $this->info("Step 1: Starting at overview page...");
            $browser->visit('https://www.hik-connect.com/views/login/index.html#/main/overview')
                ->pause(3000);

            // Step 2: Click login button
            $this->info("Step 2: Clicking login button...");
            $browser->script("
                var loginBtn = document.querySelector('#HCBLogin > div > div.master-station-wrap > div > div.master-station-header > div.header-right > div:nth-child(5)');
                if (loginBtn) {
                    console.log('[HCC] Clicking login button');
                    loginBtn.click();
                } else {
                    console.log('[HCC] Login button not found');
                }
            ");
            $browser->pause(2000);

            // Step 3: Should redirect to login page
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
            $browser->pause(1000);

            // Step 5-6: Fill phone number
            $this->info("Step 5-6: Filling phone number...");
            $browser->script("
                var phoneInput = document.querySelector('#HCBLogin > div > div:nth-child(1) > div.login-container > div > nav > div.login-form > form > div:nth-child(2) > div > div > input');
                if (phoneInput) {
                    console.log('[HCC] Filling phone number');
                    phoneInput.value = '03322414255';
                    phoneInput.dispatchEvent(new Event('input', { bubbles: true }));
                } else {
                    console.log('[HCC] Phone input not found');
                }
            ");
            $browser->pause(1000);

            // Step 7: Fill password
            $this->info("Step 7: Filling password...");
            $browser->script("
                var passwordInput = document.querySelector('input[type=\"password\"]');
                if (passwordInput) {
                    console.log('[HCC] Filling password');
                    passwordInput.value = 'Alrehman123';
                    passwordInput.dispatchEvent(new Event('input', { bubbles: true }));
                } else {
                    console.log('[HCC] Password input not found');
                }
            ");
            $browser->pause(1000);

            // Step 8: Click login button
            $this->info("Step 8: Clicking login button...");
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

            // Step 9: Click Attendance tab
            $this->info("Step 9: Clicking Attendance tab...");
            $browser->script("
                var attendanceTab = document.querySelector('#tab-HCBAttendance');
                if (attendanceTab) {
                    console.log('[HCC] Clicking attendance tab');
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
                    console.log('[HCC] Clicking attendance records');
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
                    console.log('[HCC] Clicking transaction menu');
                    transactionMenu.click();
                } else {
                    console.log('[HCC] Transaction menu not found');
                }
            ");
            $browser->pause(3000);

            // Inject API capture script
            $this->info("Injecting API capture script...");
            $browser->script("
                window.__hccApiData = null;

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
                                console.log('[HCC] Response:', xhr.responseText);

                                try {
                                    window.__hccApiData = JSON.parse(xhr.responseText);
                                } catch (e) {
                                    window.__hccApiData = xhr.responseText;
                                }
                            }
                        });

                        return originalSend.apply(this, arguments);
                    };
                })();
            ");

            // Wait for API call to happen
            $this->info("Waiting for API call...");
            $browser->pause(5000);

            // Check if we got data
            $apiData = $browser->script('return window.__hccApiData;');

            if ($apiData && isset($apiData['reportDataList'])) {
                $this->info("✓ Successfully captured API data!");
                $this->info("Records found: " . count($apiData['reportDataList']));

                // Save to database
                $this->saveAttendanceData($apiData['reportDataList']);

            } else {
                $this->warn("No API data captured. Checking console logs...");

                // Check console logs
                $logs = $driver->manage()->getLog('browser');
                foreach (array_slice($logs, -10) as $log) {
                    if (strpos($log['message'], '[HCC]') !== false) {
                        $this->line($log['message']);
                    }
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

        foreach ($records as $record) {
            try {
                \App\Models\HccAttendanceTransaction::updateOrCreate(
                    [
                        'person_code' => $record['personCode'],
                        'attendance_date' => $record['clockDate'],
                        'attendance_time' => $record['clockTime'],
                        'device_id' => $record['deviceId'] ?? null,
                    ],
                    [
                        'full_name' => $record['fullName'],
                        'department' => $record['fullPath'],
                        'device_name' => $record['deviceName'] ?? null,
                        'device_serial' => $record['deviceSerial'] ?? null,
                        'weekday' => $record['week'],
                        'source_data' => json_encode($record),
                    ]
                );
            } catch (\Exception $e) {
                $this->warn("Failed to save record: " . $e->getMessage());
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
