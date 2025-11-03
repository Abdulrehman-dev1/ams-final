<?php

namespace App\Console\Commands;

use App\Services\HccDuskScraper;
use Facebook\WebDriver\Chrome\ChromeOptions;
use Facebook\WebDriver\Remote\DesiredCapabilities;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Illuminate\Console\Command;
use Laravel\Dusk\Browser;

class HccScrapeDevices extends Command
{
    protected $signature = 'hcc:scrape:devices';
    protected $description = 'Scrape device list from HikCentral Connect';

    public function handle()
    {
        $this->info("Scraping devices from HikCentral Connect...");

        $scraper = app(HccDuskScraper::class);
        $totalSaved = 0;

        $driver = $this->createDriver();
        $browser = new Browser($driver);

        try {
            // Login
            $scraper->login($browser);

            // Extract device data
            $devices = $scraper->extractDeviceData($browser);

            // Save devices
            if (!empty($devices)) {
                $totalSaved = $scraper->saveDeviceRecords($devices);
            }

            $this->info("✓ Scraped {$totalSaved} devices");
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
