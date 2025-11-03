<?php

namespace App\Console\Commands;

use App\Services\HccDuskScraper;
use Facebook\WebDriver\Chrome\ChromeOptions;
use Facebook\WebDriver\Remote\DesiredCapabilities;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Illuminate\Console\Command;
use Laravel\Dusk\Browser;

class HccGetToken extends Command
{
    protected $signature = 'hcc:get-token';
    protected $description = 'Get auth token from browser storage after login';

    public function handle()
    {
        $this->info("Getting HCC authentication token...");

        $scraper = app(HccDuskScraper::class);

        $driver = $this->createDriver();
        $browser = new Browser($driver);

        try {
            // Login
            $this->info("Logging in...");
            $scraper->login($browser);

            // Navigate to attendance
            $scraper->navigateToAttendance($browser);
            $browser->pause(5000);

            // Get localStorage
            $localStorage = $browser->script('return JSON.stringify(localStorage);');
            $this->line("LocalStorage:");
            $this->line($localStorage);

            // Get sessionStorage
            $sessionStorage = $browser->script('return JSON.stringify(sessionStorage);');
            $this->line("SessionStorage:");
            $this->line($sessionStorage);

            // Get all cookies from current domain
            $cookies = $driver->manage()->getCookies();
            $this->line("");
            $this->info("Cookies (" . count($cookies) . "):");
            foreach ($cookies as $cookie) {
                $this->line("  {$cookie['name']}: {$cookie['value']}");
            }

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






