<?php

namespace App\Console\Commands;

use App\Services\HccAttendanceIngestor;
use App\Services\HccDuskScraper;
use Carbon\Carbon;
use Facebook\WebDriver\Chrome\ChromeOptions;
use Facebook\WebDriver\Remote\DesiredCapabilities;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Illuminate\Console\Command;
use Laravel\Dusk\Browser;

class HccDuskLogin extends Command
{
    protected $signature = 'hcc:dusk-login {--from= : Start date} {--to= : End date}';
    protected $description = 'Login via Dusk, get cookies, then use API to fetch attendance';

    public function handle()
    {
        $from = $this->option('from') ?: Carbon::today()->format('Y-m-d');
        $to = $this->option('to') ?: Carbon::today()->format('Y-m-d');

        $this->info("Step 1: Logging in with Dusk to get authenticated cookies...");

        $scraper = app(HccDuskScraper::class);
        $cookieString = null;
        
        $driver = $this->createDriver();
        $browser = new Browser($driver);

        try {
            // Login
            $scraper->login($browser);
            
            // Navigate to attendance to ensure cookies are set for HCC domain
            $scraper->navigateToAttendance($browser);
            $browser->pause(3000);
            
            // Get all cookies
            $cookies = $driver->manage()->getCookies();
            
            $cookieString = '';
            foreach ($cookies as $cookie) {
                $cookieString .= "{$cookie['name']}={$cookie['value']}; ";
            }
            $cookieString = rtrim($cookieString, '; ');
            
            $this->info("✓ Got " . count($cookies) . " cookies");
            
        } catch (\Exception $e) {
            $this->error("Login failed: " . $e->getMessage());
            return Command::FAILURE;
        } finally {
            $browser->quit();
        }

        // Now use the API client with these cookies
        $this->info("");
        $this->info("Step 2: Using cookies to fetch data via API...");
        
        // Temporarily set cookie in config
        config(['hcc.cookie' => $cookieString]);
        
        $ingestor = app(HccAttendanceIngestor::class);
        
        try {
            $fromDate = Carbon::parse($from)->setTimezone('Asia/Karachi')->startOfDay();
            $toDate = Carbon::parse($to)->setTimezone('Asia/Karachi')->endOfDay();
            
            $count = $ingestor->ingestWindow($fromDate, $toDate);
            
            $this->info("✓ Successfully fetched {$count} attendance records!");
            
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error("API fetch failed: " . $e->getMessage());
            $this->line("");
            $this->line("Cookies obtained:");
            $this->line($cookieString);
            return Command::FAILURE;
        }
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







