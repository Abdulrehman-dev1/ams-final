<?php

namespace App\Console\Commands;

use App\Services\HccDuskScraper;
use Carbon\Carbon;
use Facebook\WebDriver\Chrome\ChromeOptions;
use Facebook\WebDriver\Remote\DesiredCapabilities;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Illuminate\Console\Command;
use Laravel\Dusk\Browser;

class HccScrapeRecent extends Command
{
    protected $signature = 'hcc:scrape:recent';
    protected $description = 'Scrape recent attendance (last 10 minutes) from HikCentral Connect';

    public function handle()
    {
        $timezone = config('hcc.timezone', 'Asia/Karachi');
        $lookback = config('hcc.lookback_minutes', 10);

        $now = Carbon::now($timezone);
        $from = $now->copy()->subMinutes($lookback);

        $this->info("Scraping recent attendance ({$lookback} minute look-back)...");
        $this->info("From: {$from->toDateTimeString()}");
        $this->info("To: {$now->toDateTimeString()}");

        $scraper = app(HccDuskScraper::class);
        $totalSaved = 0;

        $driver = $this->createDriver();
        $browser = new Browser($driver);

        try {
            // Login
            $scraper->login($browser);

            // Navigate to attendance page
            $scraper->navigateToAttendance($browser);

            // Inject API capture
            $scraper->injectApiCapture($browser);

            // Set date range
            $scraper->setDateRange($browser, $from, $now);

            // Wait for data to load
            $browser->pause(3000);

            // Extract data
            $records = $scraper->extractAttendanceData($browser);

            // Save records
            if (!empty($records)) {
                $totalSaved = $scraper->saveAttendanceRecords($records);
            }

            $this->info("✓ Scraped {$totalSaved} recent attendance records");
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
