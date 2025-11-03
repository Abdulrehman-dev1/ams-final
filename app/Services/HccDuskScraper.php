<?php

namespace App\Services;

use App\Models\HccAttendanceTransaction;
use App\Models\HccDevice;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Laravel\Dusk\Browser;

class HccDuskScraper
{
    protected string $username;
    protected string $password;
    protected string $loginUrl;
    protected string $timezone;

    public function __construct()
    {
        $this->username = config('hcc.dusk_username');
        $this->password = config('hcc.dusk_password');
        $this->loginUrl = config('hcc.dusk_login_url', 'https://www.hik-connect.com');
        $this->timezone = config('hcc.timezone', 'Asia/Karachi');
    }

    /**
     * Login to HikCentral Connect
     */
    public function login(Browser $browser): void
    {
        Log::info("HCC Dusk: Logging in to {$this->loginUrl}...");

        $browser->visit($this->loginUrl);

        Log::info("HCC Dusk: Waiting for login page to load...");
        $browser->pause(3000);

        $driver = $browser->driver;

        // Click on "Phone" radio button
        $phoneRadioSelector = '#HCBLogin > div > div:nth-child(1) > div.login-container > div > nav > div.login-form > form > div:nth-child(1) > div > div > label.el-radio-button.is-checked.is-simple > span';

        Log::info("HCC Dusk: Clicking phone radio button");

        // Use JavaScript for more reliable interaction with Vue components
        $browser->script("
            // Click phone radio button
            var phoneRadio = document.querySelector('{$phoneRadioSelector}');
            if (phoneRadio) phoneRadio.click();
        ");

        $browser->pause(1000);

        // Fill phone number and password
        $phoneSelector = '#HCBLogin > div > div:nth-child(1) > div.login-container > div > nav > div.login-form > form > div:nth-child(2) > div > div > input';
        $passwordSelector = '#HCBLogin > div > div:nth-child(1) > div.login-container > div > nav > div.login-form > form > div:nth-child(3) > div > div > input';

        Log::info("HCC Dusk: Filling phone number and password");

        $browser->script("
            var phoneInput = document.querySelector('{$phoneSelector}');
            var passwordInput = document.querySelector('{$passwordSelector}');
            var submitBtn = document.querySelector('button[type=\"submit\"]');

            if (phoneInput) {
                phoneInput.value = '{$this->username}';
                phoneInput.dispatchEvent(new Event('input', { bubbles: true }));
            }
            if (passwordInput) {
                passwordInput.value = '{$this->password}';
                passwordInput.dispatchEvent(new Event('input', { bubbles: true }));
            }

            setTimeout(function() {
                if (submitBtn) submitBtn.click();
            }, 500);
        ");

        Log::info("HCC Dusk: Login form submitted, waiting for redirect...");

        // Wait for URL to change from login page
        $wait = new \Facebook\WebDriver\WebDriverWait($driver, 15);
        $wait->until(function($driver) {
            $url = $driver->getCurrentURL();
            return strpos($url, 'overview') !== false || strpos($url, 'main') !== false;
        });

        $browser->pause(3000); // Additional wait for app to initialize

        $currentUrl = $driver->getCurrentURL();
        Log::info("HCC Dusk: Redirected to", ['url' => $currentUrl]);

        if (strpos($currentUrl, 'overview') !== false || strpos($currentUrl, 'main') !== false) {
            Log::info("HCC Dusk: Login successful!");
        } else {
            Log::warning("HCC Dusk: Unexpected redirect - login may have failed");
        }
    }

    /**
     * Navigate to attendance page following exact user flow
     */
    public function navigateToAttendance(Browser $browser): void
    {
        Log::info("HCC Dusk: Navigating to attendance page...");

        $driver = $browser->driver;

        // Step 8: Wait 4 seconds then click Attendance tab
        Log::info("HCC Dusk: Step 8 - Waiting 4 seconds then clicking Attendance tab");
        $browser->pause(4000);

        $attendanceTabSelector = '#tab-HCBAttendance';

        // Try clicking with JavaScript first
        $browser->script("
            var tab = document.querySelector('{$attendanceTabSelector}');
            if (tab) {
                console.log('[HCC] Found attendance tab, clicking...');
                tab.click();
            } else {
                console.log('[HCC] Attendance tab not found');
            }
        ");
        $browser->pause(2000);

        // Step 9: Click on Attendance Records submenu
        Log::info("HCC Dusk: Step 9 - Clicking Attendance Records submenu");
        $browser->script("
            // Look for attendance records submenu
            var submenu = document.querySelector('#navbase .el-submenu.is-opened div');
            if (submenu) {
                console.log('[HCC] Found attendance records submenu, clicking...');
                submenu.click();
            } else {
                console.log('[HCC] Attendance records submenu not found');
            }
        ");
        $browser->pause(2000);

        // Step 10: Click on Transaction submenu
        Log::info("HCC Dusk: Step 10 - Clicking Transaction submenu");
        $browser->script("
            // Look for transaction menu item
            var transactionItem = document.querySelector('#navbase .el-menu-item.second-menu.is-active');
            if (transactionItem) {
                console.log('[HCC] Found transaction menu item, clicking...');
                transactionItem.click();
            } else {
                console.log('[HCC] Transaction menu item not found');
            }
        ");
        $browser->pause(5000); // Wait for page to load

        $currentUrl = $driver->getCurrentURL();
        Log::info("HCC Dusk: Now at attendance transaction page", ['url' => $currentUrl]);
    }

    /**
     * Set date range filter
     */
    public function setDateRange(Browser $browser, Carbon $from, Carbon $to): void
    {
        Log::info("HCC Dusk: Setting date range", [
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
        ]);

        // Adjust selectors based on actual UI
        $browser->type('input[name="startDate"], #dateFrom, .date-picker-from', $from->format('Y-m-d'))
            ->type('input[name="endDate"], #dateTo, .date-picker-to', $to->format('Y-m-d'))
            ->press('button.search, button.filter, .apply-filter')
            ->pause(2000); // Wait for results to load
    }

    /**
     * Extract attendance data from page
     */
    public function extractAttendanceData(Browser $browser): array
    {
        Log::info("HCC Dusk: Extracting attendance data...");

        $records = [];

        // Method 1: Try to get data from table
        $tableRows = $browser->elements('table tbody tr, .attendance-row, .transaction-item');

        foreach ($tableRows as $row) {
            try {
                $cells = $row->findElements(\Facebook\WebDriver\WebDriverBy::tagName('td'));

                if (count($cells) >= 5) {
                    $records[] = [
                        'person_code' => $cells[0]->getText(),
                        'full_name' => $cells[1]->getText(),
                        'department' => $cells[2]->getText() ?? null,
                        'attendance_datetime' => $cells[3]->getText(),
                        'device' => $cells[4]->getText() ?? null,
                    ];
                }
            } catch (\Exception $e) {
                Log::warning("HCC Dusk: Failed to parse row", ['error' => $e->getMessage()]);
            }
        }

        // Method 2: Try to intercept network requests (more reliable)
        // This requires injecting JavaScript to capture API calls
        $jsonData = $browser->script("
            return window.__attendanceData || [];
        ");

        if (!empty($jsonData)) {
            $records = array_merge($records, $jsonData);
        }

        Log::info("HCC Dusk: Extracted records", ['count' => count($records)]);

        return $records;
    }

    /**
     * Extract device data from page
     */
    public function extractDeviceData(Browser $browser): array
    {
        Log::info("HCC Dusk: Extracting device data...");

        $devices = [];

        // Navigate to devices page
        $browser->visit($this->loginUrl . '/devices')
            ->waitFor('.device-list, #devices-table', 10);

        $deviceRows = $browser->elements('table tbody tr, .device-item');

        foreach ($deviceRows as $row) {
            try {
                $cells = $row->findElements(\Facebook\WebDriver\WebDriverBy::tagName('td'));

                if (count($cells) >= 3) {
                    $devices[] = [
                        'device_id' => $cells[0]->getText(),
                        'name' => $cells[1]->getText(),
                        'serial_no' => $cells[2]->getText() ?? null,
                    ];
                }
            } catch (\Exception $e) {
                Log::warning("HCC Dusk: Failed to parse device row", ['error' => $e->getMessage()]);
            }
        }

        Log::info("HCC Dusk: Extracted devices", ['count' => count($devices)]);

        return $devices;
    }

    /**
     * Inject JavaScript to capture API responses
     */
    public function injectApiCapture(Browser $browser): void
    {
        $browser->script("
            window.__hccAttendanceData = [];
            window.__hccRawResponse = null;
            window.__hccCaptured = false;

            // Intercept XHR
            (function() {
                var origOpen = XMLHttpRequest.prototype.open;
                var origSend = XMLHttpRequest.prototype.send;

                XMLHttpRequest.prototype.open = function(method, url) {
                    this._url = url;
                    return origOpen.apply(this, arguments);
                };

                XMLHttpRequest.prototype.send = function() {
                    var xhr = this;
                    this.addEventListener('load', function() {
                        try {
                            var url = xhr._url || '';
                            if (url.includes('hccattendance/report/v1/list')) {
                                console.log('[HCC] Captured attendance API:', url);
                                var response = JSON.parse(this.responseText);
                                console.log('[HCC] Full response:', response);

                                window.__hccRawResponse = response;

                                // Try different paths
                                if (response.data && response.data.list) {
                                    window.__hccAttendanceData = response.data.list;
                                } else if (response.data && Array.isArray(response.data)) {
                                    window.__hccAttendanceData = response.data;
                                } else if (response.list) {
                                    window.__hccAttendanceData = response.list;
                                } else if (Array.isArray(response)) {
                                    window.__hccAttendanceData = response;
                                }

                                window.__hccCaptured = true;
                                console.log('[HCC] Extracted records:', window.__hccAttendanceData.length);
                            }
                        } catch(e) {
                            console.log('[HCC] Parse error:', e);
                        }
                    });
                    return origSend.apply(this, arguments);
                };
            })();
        ");

        Log::info("HCC Dusk: API capture script injected");
    }

    /**
     * Normalize and save attendance records
     */
    public function saveAttendanceRecords(array $records): int
    {
        $saved = 0;

        foreach ($records as $record) {
            try {
                $normalized = $this->normalizeAttendanceRecord($record);

                if ($normalized) {
                    HccAttendanceTransaction::updateOrCreate(
                        [
                            'person_code' => $normalized['person_code'],
                            'attendance_date' => $normalized['attendance_date'],
                            'attendance_time' => $normalized['attendance_time'],
                            'device_id' => $normalized['device_id'],
                        ],
                        $normalized
                    );
                    $saved++;
                }
            } catch (\Exception $e) {
                Log::warning("HCC Dusk: Failed to save record", [
                    'error' => $e->getMessage(),
                    'record' => $record,
                ]);
            }
        }

        return $saved;
    }

    /**
     * Normalize attendance record
     */
    protected function normalizeAttendanceRecord(array $record): ?array
    {
        // Handle different data formats
        $personCode = $record['personCode'] ?? $record['person_code'] ?? null;
        $fullName = $record['fullName'] ?? $record['full_name'] ?? 'Unknown';
        $clockStamp = $record['clockStamp'] ?? $record['attendance_datetime'] ?? null;

        if (!$personCode || !$clockStamp) {
            return null;
        }

        try {
            $dt = Carbon::parse($clockStamp)->setTimezone($this->timezone);
        } catch (\Exception $e) {
            return null;
        }

        return [
            'person_code' => $personCode,
            'full_name' => $fullName,
            'department' => $record['department'] ?? $record['groupName'] ?? null,
            'attendance_date' => $dt->format('Y-m-d'),
            'attendance_time' => $dt->format('H:i:s'),
            'device_id' => $record['deviceId'] ?? $record['device'] ?? null,
            'device_name' => null,
            'device_serial' => null,
            'weekday' => $record['weekday'] ?? $dt->format('l'),
            'source_data' => $record,
        ];
    }

    /**
     * Save device records
     */
    public function saveDeviceRecords(array $devices): int
    {
        $saved = 0;

        foreach ($devices as $device) {
            try {
                HccDevice::updateOrCreate(
                    ['device_id' => $device['device_id']],
                    [
                        'name' => $device['name'] ?? null,
                        'serial_no' => $device['serial_no'] ?? null,
                        'category' => $device['category'] ?? null,
                        'raw' => $device,
                    ]
                );
                $saved++;
            } catch (\Exception $e) {
                Log::warning("HCC Dusk: Failed to save device", [
                    'error' => $e->getMessage(),
                    'device' => $device,
                ]);
            }
        }

        return $saved;
    }
}

