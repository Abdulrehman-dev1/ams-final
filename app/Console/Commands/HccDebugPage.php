<?php

namespace App\Console\Commands;

use App\Services\HccDuskScraper;
use Facebook\WebDriver\Chrome\ChromeOptions;
use Facebook\WebDriver\Remote\DesiredCapabilities;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Illuminate\Console\Command;
use Laravel\Dusk\Browser;

class HccDebugPage extends Command
{
    protected $signature = 'hcc:debug-page';
    protected $description = 'Debug what elements are available on the page after login';

    public function handle()
    {
        $this->info("Debugging HCC page elements...");

        $scraper = app(HccDuskScraper::class);

        $driver = $this->createDriver();
        $browser = new Browser($driver);

        try {
            // Login
            $this->info("Logging in...");
            $scraper->login($browser);

            // Get page HTML to see what's available
            $this->info("Getting page HTML...");
            $html = $browser->driver->getPageSource();

            // Look for attendance-related elements
            $this->line("Looking for attendance elements...");

            // Check for common attendance selectors
            $selectors = [
                '#tab-HCBAttendance',
                '[id*="Attendance"]',
                '[class*="attendance"]',
                'a[href*="attendance"]',
                'li[class*="attendance"]',
                '.el-menu-item',
                '.nav-item'
            ];

            foreach ($selectors as $selector) {
                $elements = $browser->script("return document.querySelectorAll('{$selector}');");
                if (count($elements) > 0) {
                    $this->line("Found {$selector}: " . count($elements) . " elements");
                    foreach ($elements as $i => $element) {
                        $text = $browser->script("
                            var el = arguments[0];
                            return el ? (el.textContent || el.innerText || '') : '';
                        ", [$element]);
                        $this->line("  [{$i}] " . trim($text));
                    }
                }
            }

            // Get all clickable elements with text containing "attendance"
            $this->line("Looking for elements containing 'attendance'...");
            $attendanceElements = $browser->script("
                var elements = document.querySelectorAll('*');
                var results = [];
                for (var i = 0; i < elements.length; i++) {
                    var el = elements[i];
                    var text = (el.textContent || el.innerText || '').toLowerCase();
                    if (text.includes('attendance') && el.offsetParent !== null) {
                        results.push({
                            tag: el.tagName,
                            text: text.trim().substring(0, 50),
                            id: el.id,
                            className: el.className
                        });
                    }
                }
                return results;
            ");

            foreach ($attendanceElements as $element) {
                $this->line("Found: {$element['tag']} - {$element['text']} (id: {$element['id']}, class: {$element['className']})");
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

