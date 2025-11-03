<?php

namespace App\Console\Commands;

use App\Services\HccDuskScraper;
use Facebook\WebDriver\Chrome\ChromeOptions;
use Facebook\WebDriver\Remote\DesiredCapabilities;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Illuminate\Console\Command;
use Laravel\Dusk\Browser;

class HccScrapeTest extends Command
{
    protected $signature = 'hcc:scrape:test';
    protected $description = 'Test HCC scraper - login and capture attendance data';

    public function handle()
    {
        $this->info("Testing HCC Scraper...");

        $scraper = app(HccDuskScraper::class);

        $driver = $this->createDriver();
        $browser = new Browser($driver);

        try {
            // Login
            $this->info("Step 1: Logging in...");
            $scraper->login($browser);
            $this->info("✓ Login successful");

            // Navigate to attendance page first
            $this->info("Step 2: Navigating to attendance page...");
            $scraper->navigateToAttendance($browser);

            // Wait for page to fully load
            $this->info("Step 3: Waiting for page to load...");
            $browser->pause(5000);

            // Inject capture script AFTER page loads
            $this->info("Step 4: Injecting API capture script...");
            $scraper->injectApiCapture($browser);

            // Trigger search to make new API call that will be captured
            $this->info("Step 5: Triggering search/refresh...");
            $browser->script("
                // Try different button selectors
                var searchBtn = document.querySelector('.el-button--primary, button.search, button[type=submit], .search-btn');
                if (searchBtn) {
                    console.log('[HCC] Clicking search button');
                    searchBtn.click();
                } else {
                    console.log('[HCC] Search button not found, trying refresh');
                    location.reload();
                }
            ");
            $browser->pause(5000);

            // Check browser console logs
            $consoleLogs = $browser->driver->manage()->getLog('browser');
            $this->line("Browser Console Logs:");
            foreach ($consoleLogs as $log) {
                if (strpos($log['message'], '[HCC]') !== false) {
                    $this->line($log['message']);
                }
            }

            // Check raw response first
            $rawResponse = $browser->script('return window.__hccRawResponse;');
            $this->line("Raw API Response:");
            $this->line(json_encode($rawResponse, JSON_PRETTY_PRINT));

            // Check if data was captured
            $captured = $browser->script('return window.__hccCaptured || false;');
            $dataCount = (int)$browser->script('return (window.__hccAttendanceData || []).length;');

            $this->info("API Captured: " . ($captured ? 'Yes' : 'No'));
            $this->info("Records Found: {$dataCount}");

            if ($dataCount > 0) {
                $data = $browser->script('return window.__hccAttendanceData || [];');

                $this->line("Sample data:");
                $this->line(json_encode(array_slice($data, 0, 2), JSON_PRETTY_PRINT));

                // Save the data
                $saved = $scraper->saveAttendanceRecords($data);
                $this->info("✓ Saved {$saved} records to database");
            } else {
                $this->warn("No data captured. Page may still be loading or different selectors needed.");

                // Get page info for debugging
                $url = $browser->driver->getCurrentURL();
                $this->info("Current URL: {$url}");
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

