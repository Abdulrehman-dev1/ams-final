<?php

namespace App\Console\Commands;

use App\Models\HccAttendanceTransaction;
use Carbon\Carbon;
use Facebook\WebDriver\Chrome\ChromeOptions;
use Facebook\WebDriver\Remote\DesiredCapabilities;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Laravel\Dusk\Browser;

class HccCaptureAttendanceData extends Command
{
    protected $signature = 'hcc:capture-attendance {--from= : Start date (Y-m-d)} {--to= : End date (Y-m-d)} {--headless : Run browser in headless mode}';
    protected $description = 'Capture attendance data from Hik-Connect using exact user workflow';

    public function handle()
    {
        $fromDate = $this->option('from') ?: Carbon::now()->startOfMonth()->format('Y-m-d');
        $toDate = $this->option('to') ?: Carbon::now()->endOfMonth()->format('Y-m-d');
        $headless = $this->option('headless');

        $this->info("📅 Date Range: {$fromDate} to {$toDate}");
        $this->info("🌐 Starting browser automation...");

        $driver = $this->createDriver($headless);
        $browser = new Browser($driver);

        try {
            // Step 1: Navigate to overview page
            $this->info("Step 1: Navigating to Hik-Connect overview page...");
            $browser->visit('https://www.hik-connect.com/views/login/index.html#/main/overview');
            $browser->pause(3000);

            // Step 2 & 3: Click Log In button to redirect to login page
            $this->info("Step 2-3: Clicking Log In button...");
            $loginButtonSelector = '#HCBLogin > div > div.master-station-wrap > div > div.master-station-header > div.header-right > div:nth-child(5)';

            $browser->script("
                var loginBtn = document.querySelector('{$loginButtonSelector}');
                if (loginBtn) {
                    console.log('[HCC] Found login button, clicking...');
                    loginBtn.click();
                } else {
                    // Try XPath approach
                    var xpath = \"//*[@id='HCBLogin']/div/div[1]/div/div[1]/div[3]/div[4]\";
                    var loginBtnXPath = document.evaluate(xpath, document, null, XPathResult.FIRST_ORDERED_NODE_TYPE, null).singleNodeValue;
                    if (loginBtnXPath) {
                        console.log('[HCC] Found login button via XPath, clicking...');
                        loginBtnXPath.click();
                    }
                }
            ");

            $browser->pause(2000);

            // Step 4: Click on Phone Number radio button
            $this->info("Step 4: Selecting Phone Number login method...");
            $phoneRadioSelector = '#HCBLogin > div > div:nth-child(1) > div.login-container > div > nav > div.login-form > form > div:nth-child(1) > div > div > label:nth-child(2)';

            $browser->script("
                var phoneRadio = document.querySelector('{$phoneRadioSelector}');
                if (phoneRadio) {
                    console.log('[HCC] Found phone radio button, clicking...');
                    phoneRadio.click();
                }
            ");

            $browser->pause(1000);

            // Step 5 & 6: Enter phone number
            $this->info("Step 5-6: Entering phone number...");
            $phoneInputSelector = '#HCBLogin > div > div:nth-child(1) > div.login-container > div > nav > div.login-form > form > div:nth-child(2) > div > div > input';

            $browser->script("
                var phoneInput = document.querySelector('{$phoneInputSelector}');
                if (phoneInput) {
                    phoneInput.value = '03322414255';
                    phoneInput.dispatchEvent(new Event('input', { bubbles: true }));
                    console.log('[HCC] Phone number entered');
                }
            ");

            $browser->pause(500);

            // Step 7: Enter password
            $this->info("Step 7: Entering password...");
            $browser->script("
                var passwordInput = document.querySelector('input[type=\"password\"]');
                if (passwordInput) {
                    passwordInput.value = 'Alrehman123';
                    passwordInput.dispatchEvent(new Event('input', { bubbles: true }));
                    console.log('[HCC] Password entered');
                }
            ");

            $browser->pause(500);

            // Step 8: Click login button
            $this->info("Step 8: Clicking login button...");
            $loginSubmitSelector = '#HCBLogin > div > div:nth-child(1) > div.login-container > div > nav > div.login-form > form > div:nth-child(5) > div > button';

            $browser->script("
                var submitBtn = document.querySelector('{$loginSubmitSelector}');
                if (submitBtn) {
                    console.log('[HCC] Found submit button, clicking...');
                    submitBtn.click();
                }
            ");

            $this->info("⏳ Waiting for login...");
            $browser->pause(5000); // Wait for login to complete

            // Check current URL after login
            $currentUrl = $browser->driver->getCurrentURL();
            $this->info("📍 Current URL after login: " . $currentUrl);

            // Check if we're logged in
            $isLoggedIn = $browser->script("
                var url = window.location.href;
                var isLogin = url.includes('login');
                var isMain = url.includes('main') || url.includes('overview');
                console.log('[HCC] URL:', url, 'isLogin:', isLogin, 'isMain:', isMain);
                return isMain && !isLogin;
            ");

            if (!$isLoggedIn) {
                $this->warn("⚠️  Login may have failed! Still on login page or unexpected page.");
                $this->info("Current URL: " . $currentUrl);
            } else {
                $this->info("✅ Login successful!");
            }

            // Step 9: Wait 4 seconds then click on Attendance tab
            $this->info("Step 9: Waiting 4 seconds then clicking Attendance tab...");
            $browser->pause(4000);

            $attendanceTabSelector = '#tab-HCBAttendance';

            $tabFound = $browser->script("
                var attTab = document.querySelector('{$attendanceTabSelector}');
                if (attTab) {
                    console.log('[HCC] Found attendance tab, clicking...');
                    attTab.click();
                    return true;
                } else {
                    console.log('[HCC] Attendance tab not found, trying alternative selectors...');
                    // Try alternative selectors
                    var tabs = document.querySelectorAll('[id*=\"Attendance\"], [class*=\"attendance\"]');
                    console.log('[HCC] Found ' + tabs.length + ' elements with attendance keyword');
                    for (var i = 0; i < tabs.length; i++) {
                        console.log('[HCC] Tab ' + i + ':', tabs[i].id, tabs[i].className);
                    }
                    return false;
                }
            ");

            if (!$tabFound) {
                $this->warn("⚠️  Attendance tab not found! Check the browser window.");
            }

            $browser->pause(3000);

            // Step 10: Click on submenu to expand
            $this->info("Step 10: Expanding Attendance submenu...");
            $submenuSelector = '#navbase > div.nav-base-item.el-black-bg.el-row > div > div.page-scrollbar__wrap.el-scrollbar__wrap.el-scrollbar__wrap--hidden-default > div > ul > li.el-submenu.is-opened > div';

            $browser->script("
                var submenu = document.querySelector('{$submenuSelector}');
                if (submenu) {
                    console.log('[HCC] Found submenu, clicking...');
                    submenu.click();
                } else {
                    // Fallback: look for any opened submenu
                    var fallbackSubmenu = document.querySelector('#navbase .el-submenu.is-opened > div');
                    if (fallbackSubmenu) {
                        console.log('[HCC] Found submenu via fallback, clicking...');
                        fallbackSubmenu.click();
                    }
                }
            ");

            $browser->pause(2000);

            // Step 11: Click on Transaction menu item
            $this->info("Step 11: Clicking Transaction menu item...");
            $transactionSelector = '#navbase > div.nav-base-item.el-black-bg.el-row > div > div.page-scrollbar__wrap.el-scrollbar__wrap.el-scrollbar__wrap--hidden-default > div > ul > li.el-submenu.is-active.is-opened > ul > li.el-menu-item.second-menu.is-active';

            $browser->script("
                var transactionItem = document.querySelector('{$transactionSelector}');
                if (transactionItem) {
                    console.log('[HCC] Found transaction item, clicking...');
                    transactionItem.click();
                } else {
                    // Fallback: look for any active second menu item
                    var fallbackItem = document.querySelector('#navbase .el-menu-item.second-menu.is-active');
                    if (fallbackItem) {
                        console.log('[HCC] Found transaction item via fallback, clicking...');
                        fallbackItem.click();
                    }
                }
            ");

            $browser->pause(3000);

            // Step 12: Inject API capture script
            $this->info("Step 12: Injecting API capture script...");
            $this->injectApiCapture($browser, $fromDate, $toDate);

            $browser->pause(2000);

            // Trigger the API call by applying filters
            $this->info("📡 Triggering API call with date range...");
            $this->triggerApiCall($browser, $fromDate, $toDate);

            // Wait for API response
            $this->info("⏳ Waiting for API response...");
            $browser->pause(8000); // Increased wait time

            // Check if data was captured
            $checkCapture = $browser->script("return window.__hccCaptured || false;");
            $this->info("🔍 Capture status: " . ($checkCapture ? 'YES' : 'NO'));

            // Extract captured data
            $this->info("📊 Extracting captured data...");
            $capturedData = $browser->script("
                return {
                    captured: window.__hccCaptured || false,
                    rawResponse: window.__hccRawResponse || null,
                    attendanceData: window.__hccAttendanceData || [],
                    payload: window.__hccPayload || null,
                    requestUrl: window.__hccRequestUrl || null
                };
            ");

            if ($capturedData && is_array($capturedData) && isset($capturedData['captured']) && $capturedData['captured']) {
                $this->info("✅ Successfully captured API data!");

                // Display captured information
                $this->displayCapturedInfo($capturedData);

                // Save attendance records
                $savedCount = $this->saveAttendanceRecords($capturedData['attendanceData']);
                $this->info("💾 Saved {$savedCount} attendance records to database");

                return Command::SUCCESS;
            } else {
                $this->warn("⚠️  No API data was captured. The API call may not have been triggered.");

                // Try to get console logs for debugging
                $consoleOutput = $browser->script("
                    var logs = window.__hccConsoleLogs || [];
                    return logs.join('\\n');
                ");

                if ($consoleOutput) {
                    $this->info("\n📜 Browser console output:");
                    $this->line($consoleOutput);
                }

                // Get current URL
                $currentUrl = $browser->driver->getCurrentURL();
                $this->info("\n🌐 Current URL: " . $currentUrl);

                // Check what variables are set
                $debugInfo = $browser->script("
                    return {
                        hasCaptureScript: typeof window.__hccCaptured !== 'undefined',
                        currentUrl: window.location.href,
                        hasAttendanceData: typeof window.__hccAttendanceData !== 'undefined',
                        dataCount: (window.__hccAttendanceData || []).length
                    };
                ");

                if ($debugInfo) {
                    $this->info("\n🔧 Debug info:");
                    $this->line(json_encode($debugInfo, JSON_PRETTY_PRINT));
                }

                // Keep browser open for debugging
                if (!$headless) {
                    $this->info("\n👁️  Browser will remain open for 60 seconds for manual inspection...");
                    $this->info("Check the browser window and network tab to see if the API call was made.");
                    $browser->pause(60000);
                }

                return Command::FAILURE;
            }

        } catch (\Exception $e) {
            $this->error("❌ Error: " . $e->getMessage());
            Log::error("HCC Capture Error", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return Command::FAILURE;
        } finally {
            if ($headless || $this->confirm('Close browser?', true)) {
                $browser->quit();
            }
        }
    }

    protected function createDriver($headless = false)
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

    protected function injectApiCapture(Browser $browser, $fromDate, $toDate)
    {
        $timezone = config('hcc.timezone', 'Asia/Karachi');
        $from = Carbon::parse($fromDate)->setTimezone($timezone)->startOfDay();
        $to = Carbon::parse($toDate)->setTimezone($timezone)->endOfDay();

        $fromFormatted = $from->format('Y-m-d\TH:i:sP');
        $toFormatted = $to->format('Y-m-d\TH:i:sP');

        $browser->script("
            window.__hccAttendanceData = [];
            window.__hccRawResponse = null;
            window.__hccCaptured = false;
            window.__hccPayload = null;
            window.__hccRequestUrl = null;

            console.log('[HCC] API capture script initialized');
            console.log('[HCC] Date range: {$fromFormatted} to {$toFormatted}');

            // Intercept XHR
            (function() {
                var origOpen = XMLHttpRequest.prototype.open;
                var origSend = XMLHttpRequest.prototype.send;

                XMLHttpRequest.prototype.open = function(method, url) {
                    this._url = url;
                    this._method = method;
                    return origOpen.apply(this, arguments);
                };

                XMLHttpRequest.prototype.send = function(body) {
                    var xhr = this;
                    var url = this._url || '';

                    // Log all requests for debugging
                    console.log('[HCC] Request:', this._method, url);

                    if (url.includes('hccattendance/report/v1/list')) {
                        console.log('[HCC] 🎯 Attendance API detected!');
                        console.log('[HCC] Payload:', body);

                        try {
                            window.__hccPayload = JSON.parse(body);
                            window.__hccRequestUrl = url;
                        } catch(e) {
                            console.log('[HCC] Failed to parse payload:', e);
                        }
                    }

                    this.addEventListener('load', function() {
                        try {
                            if (url.includes('hccattendance/report/v1/list')) {
                                console.log('[HCC] ✅ Attendance API response received!');
                                var response = JSON.parse(this.responseText);
                                console.log('[HCC] Response:', response);

                                window.__hccRawResponse = response;

                                // Extract attendance data from different possible response structures
                                if (response.data && response.data.list) {
                                    window.__hccAttendanceData = response.data.list;
                                    console.log('[HCC] Extracted from data.list:', response.data.list.length, 'records');
                                } else if (response.data && Array.isArray(response.data)) {
                                    window.__hccAttendanceData = response.data;
                                    console.log('[HCC] Extracted from data:', response.data.length, 'records');
                                } else if (response.list) {
                                    window.__hccAttendanceData = response.list;
                                    console.log('[HCC] Extracted from list:', response.list.length, 'records');
                                } else if (Array.isArray(response)) {
                                    window.__hccAttendanceData = response;
                                    console.log('[HCC] Extracted from root:', response.length, 'records');
                                }

                                window.__hccCaptured = true;
                                console.log('[HCC] 📊 Total records captured:', window.__hccAttendanceData.length);
                            }
                        } catch(e) {
                            console.log('[HCC] ❌ Parse error:', e);
                        }
                    });

                    return origSend.apply(this, arguments);
                };
            })();

            console.log('[HCC] ✅ API interceptor ready');
        ");
    }

    protected function triggerApiCall(Browser $browser, $fromDate, $toDate)
    {
        // Try to trigger the search/filter to make the API call
        $timezone = config('hcc.timezone', 'Asia/Karachi');
        $from = Carbon::parse($fromDate)->setTimezone($timezone);
        $to = Carbon::parse($toDate)->setTimezone($timezone);

        // Try clicking the search/query button to trigger the API
        $browser->script("
            // Look for search/query buttons
            var searchBtn = document.querySelector('button.search, button.query, .search-btn, .query-btn, button[type=\"button\"]');
            if (searchBtn && (searchBtn.textContent.includes('Search') || searchBtn.textContent.includes('Query'))) {
                console.log('[HCC] Found search button, clicking...');
                searchBtn.click();
            } else {
                console.log('[HCC] No explicit search button found, API should auto-trigger');
            }
        ");
    }

    protected function displayCapturedInfo($data)
    {
        if ($data['requestUrl']) {
            $this->info("🌐 Request URL: " . $data['requestUrl']);
        }

        if ($data['payload']) {
            $this->info("📤 Payload:");
            $this->line(json_encode($data['payload'], JSON_PRETTY_PRINT));
        }

        if ($data['rawResponse']) {
            $this->info("\n📥 Response Summary:");
            $this->line("  - Has data: " . (isset($data['rawResponse']['data']) ? 'Yes' : 'No'));
            $this->line("  - Record count: " . count($data['attendanceData']));
        }

        if (!empty($data['attendanceData'])) {
            $this->info("\n📋 Sample Record:");
            $this->line(json_encode($data['attendanceData'][0], JSON_PRETTY_PRINT));
        }
    }

    protected function saveAttendanceRecords(array $records): int
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

