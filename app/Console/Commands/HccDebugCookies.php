<?php

namespace App\Console\Commands;

use App\Services\HccDuskScraper;
use Facebook\WebDriver\Chrome\ChromeOptions;
use Facebook\WebDriver\Remote\DesiredCapabilities;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Illuminate\Console\Command;
use Laravel\Dusk\Browser;

class HccDebugCookies extends Command
{
    protected $signature = 'hcc:debug-cookies';
    protected $description = 'Debug cookie collection by keeping browser open';

    public function handle()
    {
        $this->info("🔍 Starting debug session...");
        $this->info("Browser will remain open - manually navigate through the UI and press Enter when done");
        $this->info("");

        $driver = $this->createDriver(false); // Non-headless
        $browser = new Browser($driver);

        try {
            $scraper = app(HccDuskScraper::class);

            // Step 1: Login
            $this->info("Step 1: Logging in...");
            $scraper->login($browser);
            $this->info("✅ Logged in");
            $this->info("");

            // Show cookies after login
            $this->showCookies($driver, "After Login (hik-connect.com)");

            // Step 2: Navigate to HCC domain
            $this->info("Step 2: Navigating to HCC domain...");
            $browser->visit("https://isgp.hikcentralconnect.com");
            $browser->pause(5000);

            $currentUrl = $driver->getCurrentURL();
            $this->info("Current URL: {$currentUrl}");
            $this->info("");

            $this->showCookies($driver, "After visiting HCC domain");

            // Step 3: Try navigation to attendance
            $this->info("Step 3: Trying to navigate to attendance...");
            $scraper->navigateToAttendance($browser);
            $browser->pause(5000);

            $currentUrl = $driver->getCurrentURL();
            $this->info("Current URL: {$currentUrl}");
            $this->info("");

            $this->showCookies($driver, "After navigating to attendance");

            // Step 4: Manual navigation
            $this->info("===============================================");
            $this->info("NOW IT'S YOUR TURN!");
            $this->info("===============================================");
            $this->info("The browser is open. Please:");
            $this->info("1. Manually navigate to the Attendance > Transaction page");
            $this->info("2. Wait for the page to fully load");
            $this->info("3. Open DevTools (F12) and check:");
            $this->info("   - Network tab for API calls");
            $this->info("   - Application tab > Cookies");
            $this->info("   - Console for any auth tokens");
            $this->info("4. Press Enter here when you're done exploring");
            $this->info("");

            $this->ask("Press Enter to show final cookies and close");

            $currentUrl = $driver->getCurrentURL();
            $this->info("\n📍 Final URL: {$currentUrl}");
            $this->showCookies($driver, "Final Cookies");

            // Try to extract localStorage/sessionStorage
            $this->info("\n🔐 Checking browser storage...");
            $localStorage = $browser->script("
                var items = {};
                for (var i = 0; i < localStorage.length; i++) {
                    var key = localStorage.key(i);
                    items[key] = localStorage.getItem(key);
                }
                return items;
            ");

            if (!empty($localStorage)) {
                $this->info("📦 LocalStorage:");
                foreach ($localStorage as $key => $value) {
                    $this->line("  - {$key}: " . substr($value, 0, 100) . (strlen($value) > 100 ? '...' : ''));
                }
            } else {
                $this->warn("No localStorage items found");
            }

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

    protected function showCookies($driver, $label)
    {
        $cookies = $driver->manage()->getCookies();
        $this->info("🍪 {$label}:");
        $this->info("   Total: " . count($cookies));

        foreach ($cookies as $cookie) {
            $domain = $cookie['domain'] ?? 'unknown';
            $value = substr($cookie['value'], 0, 40) . (strlen($cookie['value']) > 40 ? '...' : '');
            $this->line("   - {$cookie['name']} ({$domain}): {$value}");
        }
        $this->info("");
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





