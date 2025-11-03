<?php

namespace App\Console\Commands;

use App\Services\HccDuskScraper;
use Facebook\WebDriver\Chrome\ChromeOptions;
use Facebook\WebDriver\Remote\DesiredCapabilities;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Illuminate\Console\Command;
use Laravel\Dusk\Browser;

class HccCaptureRequest extends Command
{
    protected $signature = 'hcc:capture-request';
    protected $description = 'Capture the actual API request headers from browser';

    public function handle()
    {
        $this->info("Capturing HCC API request...");

        $scraper = app(HccDuskScraper::class);

        $driver = $this->createDriver();
        $browser = new Browser($driver);

        try {
            // Login
            $this->info("Step 1: Logging in...");
            $scraper->login($browser);

            // Navigate to attendance
            $this->info("Step 2: Going to attendance page...");
            $scraper->navigateToAttendance($browser);

            // Inject script to capture full request including headers
            $this->info("Step 3: Injecting request capture script...");
            $browser->script("
                window.__hccRequestCaptured = null;

                (function() {
                    var origSend = XMLHttpRequest.prototype.send;

                    XMLHttpRequest.prototype.send = function() {
                        var xhr = this;

                        // Capture request details before sending
                        var originalOpen = this._url;

                        this.addEventListener('readystatechange', function() {
                            if (xhr.readyState === 4 && xhr._url && xhr._url.includes('hccattendance/report/v1/list')) {
                                console.log('[HCC] Captured API request');

                                // Get all request headers
                                var requestHeaders = {};
                                var headerStr = xhr.getAllResponseHeaders();

                                window.__hccRequestCaptured = {
                                    url: xhr._url,
                                    method: 'POST',
                                    status: xhr.status,
                                    response: xhr.responseText
                                };

                                console.log('[HCC] Request captured:', window.__hccRequestCaptured);
                            }
                        });

                        return origSend.apply(this, arguments);
                    };
                })();
            ");

            // Wait for API call to happen
            $this->info("Step 4: Waiting for API call...");
            $browser->pause(10000);

            // Check console logs
            $logs = $driver->manage()->getLog('browser');
            $this->line("Browser Console:");
            foreach (array_slice($logs, -15) as $log) {
                if (strpos($log['message'], '[HCC]') !== false) {
                    $this->line($log['message']);
                }
            }

            // Get captured request
            $captured = $browser->script('return window.__hccRequestCaptured;');

            if ($captured) {
                $this->info("✓ API Request Captured!");
                $this->line(json_encode($captured, JSON_PRETTY_PRINT));
            } else {
                $this->warn("No API request captured yet. Page may need manual interaction.");

                // Try clicking search button
                $this->info("Trying to trigger search...");
                $browser->script("
                    var searchBtn = document.querySelector('.el-button--primary, button[type=button]');
                    if (searchBtn) searchBtn.click();
                ");
                $browser->pause(5000);

                $captured = $browser->script('return window.__hccRequestCaptured;');
                if ($captured) {
                    $this->info("✓ API Request Captured after search!");
                    $this->line(json_encode($captured, JSON_PRETTY_PRINT));
                }
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

