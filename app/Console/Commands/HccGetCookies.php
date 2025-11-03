<?php

namespace App\Console\Commands;

use App\Services\HccDuskScraper;
use Facebook\WebDriver\Chrome\ChromeOptions;
use Facebook\WebDriver\Remote\DesiredCapabilities;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Illuminate\Console\Command;
use Laravel\Dusk\Browser;

class HccGetCookies extends Command
{
    protected $signature = 'hcc:get-cookies';
    protected $description = 'Login via Dusk and get authentication cookies for API use';

    public function handle()
    {
        $this->info("Getting HCC authentication cookies...");

        $scraper = app(HccDuskScraper::class);

        $driver = $this->createDriver();
        $browser = new Browser($driver);

        try {
            // Login
            $this->info("Logging in...");
            $scraper->login($browser);

            // Navigate to attendance to trigger cross-domain cookies
            $this->info("Navigating to attendance page to get HCC cookies...");
            $scraper->navigateToAttendance($browser);
            $browser->pause(3000);

            // Navigate to the actual API domain to get its cookies
            $browser->visit('https://isgp-team.hikcentralconnect.com');
            $browser->pause(2000);

            // Get all cookies
            $cookies = $driver->manage()->getCookies();

            $this->info("Found " . count($cookies) . " cookies:");

            $cookieString = '';
            foreach ($cookies as $cookie) {
                $this->line("  - {$cookie['name']}: {$cookie['value']}");
                $cookieString .= "{$cookie['name']}={$cookie['value']}; ";
            }

            $cookieString = rtrim($cookieString, '; ');

            $this->line("");
            $this->info("Copy this to your .env file:");
            $this->line("HCC_COOKIE=\"{$cookieString}\"");

        } catch (\Exception $e) {
            $this->error("Error: " . $e->getMessage());
            return Command::FAILURE;
        } finally {
            $browser->quit();
        }

        return Command::SUCCESS;
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

