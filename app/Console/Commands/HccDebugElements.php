<?php

namespace App\Console\Commands;

use Facebook\WebDriver\Chrome\ChromeOptions;
use Facebook\WebDriver\Remote\DesiredCapabilities;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Illuminate\Console\Command;
use Laravel\Dusk\Browser;

class HccDebugElements extends Command
{
    protected $signature = 'hcc:debug-elements';
    protected $description = 'Debug: Show available elements after login';

    protected $username = '03322414255';
    protected $password = 'Alrehman123';

    public function handle()
    {
        $this->info("🔍 Debugging Element Availability");

        $driver = $this->createDriver(false);
        $browser = new Browser($driver);

        try {
            // Login first
            $this->info("Logging in...");
            $browser->visit('https://www.hik-connect.com/views/login/index.html#/main/overview');
            $browser->pause(5000);

            // Click login button (XPath method)
            $browser->script("
                var xpath = '//*[@id=\"HCBLogin\"]/div/div[1]/div/div[1]/div[3]/div[4]';
                var btn = document.evaluate(xpath, document, null, XPathResult.FIRST_ORDERED_NODE_TYPE, null).singleNodeValue;
                if (btn) btn.click();
            ");
            $browser->pause(3000);

            // Select phone login
            $browser->script("
                var selector = '#HCBLogin nav .login-form form > div:nth-child(1) label:nth-child(2)';
                var el = document.querySelector(selector);
                if (el) el.click();
            ");
            $browser->pause(1000);

            // Enter credentials
            $browser->script("
                var phoneInput = document.querySelector('#HCBLogin nav .login-form form > div:nth-child(2) input');
                var passInput = document.querySelector('input[type=\"password\"]');
                if (phoneInput) {
                    phoneInput.value = '{$this->username}';
                    phoneInput.dispatchEvent(new Event('input', { bubbles: true }));
                }
                if (passInput) {
                    passInput.value = '{$this->password}';
                    passInput.dispatchEvent(new Event('input', { bubbles: true }));
                }
            ");
            $browser->pause(1000);

            // Click login
            $browser->script("
                var loginBtn = document.querySelector('button[type=\"submit\"]');
                if (loginBtn) loginBtn.click();
            ");

            $this->info("Waiting for login...");
            $browser->pause(10000);

            $currentUrl = $driver->getCurrentURL();
            $this->info("✅ Current URL: {$currentUrl}");
            $this->info("");

            // Now debug what elements are available
            $this->info("=== SEARCHING FOR ATTENDANCE ELEMENTS ===\n");

            try {
                // Check all in one script to avoid multiple returns
                $debugInfo = $browser->script("
                    var info = [];

                    // 1. Check for attendance tab
                    var tab = document.querySelector('#tab-HCBAttendance');
                    info.push('1. #tab-HCBAttendance: ' + (tab ? 'FOUND' : 'NOT FOUND'));

                    // 2. Find all tabs
                    var tabs = document.querySelectorAll('[id^=\\'tab-\\']');
                    var tabIds = [];
                    for (var i = 0; i < tabs.length; i++) {
                        tabIds.push(tabs[i].id);
                    }
                    info.push('2. All tabs: ' + (tabIds.length > 0 ? tabIds.join(', ') : 'None'));

                    // 3. Check for navbase
                    var navbase = document.querySelector('#navbase');
                    info.push('3. #navbase: ' + (navbase ? 'FOUND' : 'NOT FOUND'));

                    // 4. Current page info
                    info.push('4. Page ready: ' + document.readyState);
                    info.push('5. Body classes: ' + document.body.className);

                    return info.join('\\n');
                ");

                $this->line($debugInfo);
            } catch (\Exception $e) {
                $this->error("Script error: " . $e->getMessage());
            }

            $this->info("\n\n=== BROWSER WILL STAY OPEN FOR 60 SECONDS ===");
            $this->info("Use this time to:");
            $this->info("1. Manually click Attendance → Transaction");
            $this->info("2. Open DevTools (F12) and inspect the elements");
            $this->info("3. Note the correct selectors");
            $this->info("");

            $browser->pause(60000);

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error("Error: " . $e->getMessage());
            return Command::FAILURE;
        } finally {
            if ($this->confirm('Close browser?', true)) {
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
}

