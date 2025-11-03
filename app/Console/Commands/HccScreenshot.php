<?php

namespace App\Console\Commands;

use App\Services\HccDuskScraper;
use Facebook\WebDriver\Chrome\ChromeOptions;
use Facebook\WebDriver\Remote\DesiredCapabilities;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Illuminate\Console\Command;
use Laravel\Dusk\Browser;

class HccScreenshot extends Command
{
    protected $signature = 'hcc:screenshot';
    protected $description = 'Take a screenshot of HCC attendance page after login';

    public function handle()
    {
        $this->info("Taking screenshot of HCC attendance page...");

        $scraper = app(HccDuskScraper::class);

        $driver = $this->createDriver();
        $browser = new Browser($driver);

        try {
            // Login
            $this->info("Step 1: Logging in...");
            $scraper->login($browser);

            // Navigate to attendance
            $this->info("Step 2: Navigating to attendance page...");
            $scraper->navigateToAttendance($browser);

            // Check for JavaScript errors
            $logs = $driver->manage()->getLog('browser');
            $this->line("Browser Console Logs:");
            foreach (array_slice($logs, -10) as $log) {
                $this->line("  [{$log['level']}] {$log['message']}");
            }
            
            // Try to make portal visible with JavaScript
            $this->info("Attempting to show portal...");
            $browser->script("
                var portal = document.querySelector('#portal');
                if (portal) {
                    portal.style.display = 'block';
                    console.log('[HCC] Portal display set to block');
                }
            ");
            
            $browser->pause(5000);
            
            // Check if portal is visible now
            $portalVisible = $browser->script("return document.querySelector('#portal')?.style.display;");
            $this->info("Portal display after force show: " . json_encode($portalVisible));
            
            // Get portal inner HTML
            $portalContent = $browser->script("return document.querySelector('#portal')?.innerHTML || 'Portal not found';");
            $contentLength = is_array($portalContent) ? count($portalContent) : strlen($portalContent);
            $this->line("Portal content length: {$contentLength}");

            // Try to trigger data load by clicking search
            $browser->script("
                var btns = document.querySelectorAll('button, .el-button');
                btns.forEach(function(btn) {
                    console.log('Button text:', btn.innerText);
                });
            ");

            // Take screenshot
            $browser->screenshot('attendance-page');

            $this->info("✓ Screenshot saved to: tests/Browser/screenshots/attendance-page.png");

            // Get page HTML
            $html = $browser->script('return document.body.outerHTML;');
            file_put_contents(storage_path('app/attendance-page.html'), $html);
            $this->info("✓ Page HTML saved to: storage/app/attendance-page.html");

            $this->info("");
            $this->info("Current URL: " . $driver->getCurrentURL());

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
            // Headless disabled - you'll see Chrome window open
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

