<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Facebook\WebDriver\Chrome\ChromeOptions;
use Facebook\WebDriver\Remote\DesiredCapabilities;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Laravel\Dusk\Browser;

class HccCaptureAttendanceFinal extends Command
{
    protected $signature = 'hcc:capture
                            {--from= : Start date}
                            {--to= : End date}
                            {--page=1 : Page number}
                            {--page-size=100 : Page size}';

    protected $description = 'Capture HCC attendance data via Dusk';

    protected $username = '03322414255';
    protected $password = 'Alrehman123';

    public function handle()
    {
        $from = $this->option('from') ?: Carbon::now()->setTimezone('Asia/Karachi')->startOfMonth()->format('Y-m-d\TH:i:sP');
        $to = $this->option('to') ?: Carbon::now()->setTimezone('Asia/Karachi')->endOfMonth()->format('Y-m-d\TH:i:sP');
        $page = (int) $this->option('page');
        $pageSize = (int) $this->option('page-size');

        $this->info("🚀 Starting HCC Attendance Capture");
        $this->line("Date range: {$from} to {$to}");

        $driver = $this->createDriver(false);
        $browser = new Browser($driver);

        try {
            // Step 1: Open initial URL
            $this->info("\n[1] Opening Hik-Connect portal...");
            $browser->visit('https://www.hik-connect.com/views/login/index.html#/main/overview');
            $this->info("    Waiting 5s for page load...");
            $browser->pause(5000);

            // Step 2: Click "Log In" button
            $this->info("[2] Clicking Log In button...");
            $this->retryClick($browser, [
                '#HCBLogin > div > div.master-station-wrap > div > div.master-station-header > div.header-right > div:nth-child(5)',
            ], '//*[@id="HCBLogin"]/div/div[1]/div/div[1]/div[3]/div[4]');
            $this->info("    Waiting 3s for login page...");
            $browser->pause(3000);

            // Step 3: Switch to Phone Number login
            $this->info("[3] Selecting Phone Number login...");
            $this->retryClick($browser, [
                '#HCBLogin nav .login-form form > div:nth-child(1) label:nth-child(2)',
            ]);
            $this->info("    Waiting 2s...");
            $browser->pause(2000);

            // Step 4: Enter username
            $this->info("[4] Entering username: 0332***4255");
            $this->retryType($browser, [
                'input[placeholder*="Phone"]',
                '#HCBLogin nav .login-form form > div:nth-child(2) input',
            ], $this->username);
            $this->info("    Waiting 1s...");
            $browser->pause(1000);

            // Step 5: Enter password
            $this->info("[5] Entering password: ***");
            $this->retryType($browser, [
                'input[placeholder="Password"][type="password"]',
                'input[type="password"]',
            ], $this->password);
            $this->info("    Waiting 1s...");
            $browser->pause(1000);

            // Step 6: Click Login button
            $this->info("[6] Clicking Login button...");
            $this->retryClick($browser, [
                'button[type="submit"]',
                '#HCBLogin nav .login-form form > div:nth-child(5) button',
            ]);

            $this->info("[7] Waiting for login (8s for redirect)...");
            $browser->pause(8000);

            // Check if logged in
            $currentUrl = $driver->getCurrentURL();
            if (str_contains($currentUrl, 'login') && !str_contains($currentUrl, 'overview')) {
                $this->error("❌ Login failed - still on login page");
                $browser->screenshot('storage/logs/login-failed');
                return Command::FAILURE;
            }
            $this->info("✅ Logged in: {$currentUrl}");

            // Step 7: Inject API capture script BEFORE navigating
            $this->info("[8] Injecting API capture script...");
            $this->injectApiCapture($browser);
            $this->info("    API capture script injected");

            // Step 8: Click Attendance tab
            $this->info("[9] Clicking Attendance tab...");
            $this->info("    Waiting 4s before clicking...");
            $browser->pause(4000);
            $this->retryClick($browser, [
                '#tab-HCBAttendance',
                '[id*="Attendance"]',
            ], '//*[@id="tab-HCBAttendance"]');
            $this->info("    Waiting 5s for tab to load...");
            $browser->pause(5000);

            $currentUrl = $driver->getCurrentURL();
            $this->line("    Current URL: {$currentUrl}");

            // Step 9: Expand Attendance submenu
            $this->info("[10] Expanding Attendance submenu...");
            $this->retryClick($browser, [
                '#navbase li.el-submenu.is-opened > div',
                '#navbase .el-submenu div',
            ], '#navbase > div.nav-base-item.el-black-bg.el-row > div > div.page-scrollbar__wrap.el-scrollbar__wrap.el-scrollbar__wrap--hidden-default > div > ul > li.el-submenu.is-opened > div');
            $this->info("    Waiting 3s for submenu...");
            $browser->pause(3000);

            // Step 10: Click Transaction menu item
            $this->info("[11] Clicking Transaction menu item...");
            $this->retryClick($browser, [
                '#navbase .el-menu-item.second-menu.is-active',
                '#navbase .el-menu-item.second-menu',
            ], '#navbase > div.nav-base-item.el-black-bg.el-row > div > div.page-scrollbar__wrap.el-scrollbar__wrap.el-scrollbar__wrap--hidden-default > div > ul > li.el-submenu.is-active.is-opened > ul > li.el-menu-item.second-menu.is-active');
            $this->info("    Waiting 8s for page to load...");
            $browser->pause(8000);

            $currentUrl = $driver->getCurrentURL();
            $this->line("    Final URL: {$currentUrl}");

            // Step 11: Check if data was captured
            $this->info("[12] Checking for captured API data...");
            $captured = $browser->script("return window.__hccCaptured || false;");

            if (!$captured) {
                $this->warn("⚠️  API not captured yet, waiting 10s more...");
                $browser->pause(10000);
                $captured = $browser->script("return window.__hccCaptured || false;");
            }

            if ($captured) {
                $data = $browser->script("
                    return {
                        headers: window.__hccHeaders || {},
                        payload: window.__hccPayload || {},
                        response: window.__hccResponse || {}
                    };
                ");

                if (!$data || !is_array($data)) {
                    $this->error("❌ Invalid data structure returned");
                    $this->saveErrorLog("Data returned is not an array");
                    return Command::FAILURE;
                }

                // Save to files
                $this->saveToFile('request_headers.json', $data['headers'] ?? []);
                $this->saveToFile('payload.json', $data['payload'] ?? []);
                $this->saveToFile('response.json', $data['response'] ?? []);

                $this->info("✅ Data captured successfully!");
                $this->displaySummary($data);

                // Forward to Laravel API if response has data
                if (isset($data['response']['data']['reportDataList']) && is_array($data['response']['data']['reportDataList'])) {
                    $this->forwardToApi($data['response']['data']['reportDataList']);
                }

                return Command::SUCCESS;
            } else {
                $this->error("❌ Failed to capture API call");
                $this->saveErrorLog("API call not intercepted");
                $browser->screenshot('storage/logs/capture-failed');
                return Command::FAILURE;
            }

        } catch (\Exception $e) {
            $this->error("❌ Error: " . $e->getMessage());
            $this->saveErrorLog($e->getMessage() . "\n" . $e->getTraceAsString());
            Log::error('HCC Capture Failed', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return Command::FAILURE;
        } finally {
            if ($this->confirm('Close browser?', true)) {
                $browser->quit();
            }
        }
    }

    protected function retryClick(Browser $browser, array $selectors, ?string $xpath = null, int $maxAttempts = 3)
    {
        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            try {
                // Try CSS selectors first
                foreach ($selectors as $selector) {
                    // Skip jQuery-style selectors like :contains()
                    if (strpos($selector, ':contains') !== false) {
                        continue;
                    }

                    $found = $browser->script("
                        var el = document.querySelector(`{$selector}`);
                        if (el) {
                            el.click();
                            return true;
                        }
                        return false;
                    ");

                    if ($found) {
                        return true;
                    }
                }

                // Try XPath fallback
                if ($xpath) {
                    $found = $browser->script("
                        var result = document.evaluate(`{$xpath}`, document, null, XPathResult.FIRST_ORDERED_NODE_TYPE, null);
                        if (result.singleNodeValue) {
                            result.singleNodeValue.click();
                            return true;
                        }
                        return false;
                    ");

                    if ($found) {
                        return true;
                    }
                }

                if ($attempt < $maxAttempts) {
                    $this->warn("  Attempt {$attempt} failed, retrying...");
                    $browser->pause(1000 * $attempt);
                }
            } catch (\Exception $e) {
                if ($attempt >= $maxAttempts) {
                    throw $e;
                }
                $browser->pause(1000 * $attempt);
            }
        }
        return false;
    }

    protected function retryType(Browser $browser, array $selectors, string $value, int $maxAttempts = 3)
    {
        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            foreach ($selectors as $selector) {
                $success = $browser->script("
                    var el = document.querySelector('{$selector}');
                    if (el) {
                        el.value = '{$value}';
                        el.dispatchEvent(new Event('input', { bubbles: true }));
                        el.dispatchEvent(new Event('change', { bubbles: true }));
                        return true;
                    }
                    return false;
                ");

                if ($success) {
                    return true;
                }
            }

            if ($attempt < $maxAttempts) {
                $browser->pause(1000 * $attempt);
            }
        }
        return false;
    }

    protected function injectApiCapture(Browser $browser)
    {
        $browser->script("
            window.__hccCaptured = false;
            window.__hccHeaders = null;
            window.__hccPayload = null;
            window.__hccResponse = null;

            (function() {
                var origOpen = XMLHttpRequest.prototype.open;
                var origSend = XMLHttpRequest.prototype.send;
                var origSetRequestHeader = XMLHttpRequest.prototype.setRequestHeader;

                XMLHttpRequest.prototype.open = function(method, url) {
                    this._url = url;
                    this._method = method;
                    this._headers = {};
                    return origOpen.apply(this, arguments);
                };

                XMLHttpRequest.prototype.setRequestHeader = function(name, value) {
                    this._headers[name] = value;
                    return origSetRequestHeader.apply(this, arguments);
                };

                XMLHttpRequest.prototype.send = function(body) {
                    var xhr = this;

                    if (this._url && this._url.includes('/hcc/hccattendance/report/v1/list')) {
                        console.log('[HCC] 🎯 Captured attendance API call');

                        // Save headers and payload
                        window.__hccHeaders = this._headers || {};
                        try {
                            window.__hccPayload = JSON.parse(body);
                        } catch(e) {
                            window.__hccPayload = body;
                        }

                        this.addEventListener('load', function() {
                            try {
                                window.__hccResponse = JSON.parse(this.responseText);
                                window.__hccCaptured = true;
                                console.log('[HCC] ✅ Response captured:', window.__hccResponse);
                            } catch(e) {
                                console.log('[HCC] ❌ Parse error:', e);
                            }
                        });
                    }

                    return origSend.apply(this, arguments);
                };
            })();

            console.log('[HCC] API capture ready');
        ");
    }

    protected function saveToFile(string $filename, $data)
    {
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        Storage::disk('local')->put("hcc/{$filename}", $json);
        $this->line("  📄 Saved: storage/app/hcc/{$filename}");
    }

    protected function saveErrorLog(string $error)
    {
        Storage::disk('local')->put('hcc/error.log', $error);
        $this->line("  📄 Error log: storage/app/hcc/error.log");
    }

    protected function displaySummary($data)
    {
        if (isset($data['response']['data']['reportDataList'])) {
            $count = count($data['response']['data']['reportDataList']);
            $this->info("  📊 Records captured: {$count}");

            if ($count > 0) {
                $this->line("  📋 Sample record:");
                $this->line(json_encode($data['response']['data']['reportDataList'][0], JSON_PRETTY_PRINT));
            }
        }
    }

    protected function forwardToApi(array $records)
    {
        $endpoint = config('app.url') . '/api/hik/attendance/import';
        $this->info("\n[13] Forwarding {count} records to {$endpoint}...", ['count' => count($records)]);

        try {
            $response = Http::post($endpoint, [
                'records' => $records,
                'imported_at' => now()->toIso8601String(),
            ]);

            if ($response->successful()) {
                $this->info("✅ Data forwarded successfully");
            } else {
                $this->warn("⚠️  API returned status {$response->status()}");
            }
        } catch (\Exception $e) {
            $this->warn("⚠️  Failed to forward: " . $e->getMessage());
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
}

