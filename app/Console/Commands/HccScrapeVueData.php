<?php

namespace App\Console\Commands;

use App\Models\HccAttendanceTransaction;
use App\Services\HccDuskScraper;
use Carbon\Carbon;
use Facebook\WebDriver\Chrome\ChromeOptions;
use Facebook\WebDriver\Remote\DesiredCapabilities;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Laravel\Dusk\Browser;

class HccScrapeVueData extends Command
{
    protected $signature = 'hcc:scrape-vue {--from=} {--to=}';
    protected $description = 'Scrape HCC by extracting Vue.js app data from the page';

    public function handle()
    {
        $from = $this->option('from') ?: Carbon::yesterday()->format('Y-m-d');
        $to = $this->option('to') ?: Carbon::today()->format('Y-m-d');

        $this->info("Scraping HCC attendance from {$from} to {$to}...");

        $scraper = app(HccDuskScraper::class);
        $driver = $this->createDriver();
        $browser = new Browser($driver);

        try {
            // Login
            $this->info("Step 1: Logging in...");
            $browser->visit(config('hcc.dusk_login_url'));
            $scraper->login($browser);
            $browser->pause(3000);

            // Navigate to attendance
            $this->info("Step 2: Navigating to attendance page...");
            $scraper->navigateToAttendance($browser);
            $browser->pause(15000); // Wait for Vue app and data to load

            // Extract data from Vue component
            $this->info("Step 3: Extracting data from page...");
            $vueData = $browser->script("
                // Try multiple ways to get the data

                // Method 1: Check if there's a Vue instance
                if (window.__INITIAL_STATE__) {
                    return window.__INITIAL_STATE__;
                }

                // Method 2: Try to get data from Vue app
                var app = document.querySelector('#app');
                if (app && app.__vue__) {
                    return app.__vue__.\$data;
                }

                // Method 3: Look for data in the table rows
                var tableData = [];
                var rows = document.querySelectorAll('table tbody tr, .el-table__body tr');

                for (var i = 0; i < rows.length; i++) {
                    var cells = rows[i].querySelectorAll('td');
                    if (cells.length > 0) {
                        var rowData = {};
                        // Extract text from each cell
                        for (var j = 0; j < cells.length; j++) {
                            rowData['col' + j] = cells[j].textContent.trim();
                        }
                        tableData.push(rowData);
                    }
                }

                return {
                    method: 'table_extraction',
                    rows: tableData,
                    rowCount: tableData.length
                };
            ");

            $this->line("Extracted data: " . json_encode($vueData, JSON_PRETTY_PRINT));

            if (isset($vueData['rows']) && count($vueData['rows']) > 0) {
                $this->info("✓ Found " . count($vueData['rows']) . " rows!");
                $this->line("Sample: " . json_encode(array_slice($vueData['rows'], 0, 2), JSON_PRETTY_PRINT));
            } else {
                $this->warn("No data found. Let me check the page structure...");

                // Debug page structure
                $pageInfo = $browser->script("
                    return {
                        url: window.location.href,
                        title: document.title,
                        hasTable: document.querySelector('table') ? true : false,
                        tableCount: document.querySelectorAll('table').length,
                        rowCount: document.querySelectorAll('tr').length,
                        elTableRows: document.querySelectorAll('.el-table__row').length
                    };
                ");

                $this->line("Page info: " . json_encode($pageInfo, JSON_PRETTY_PRINT));
            }

        } catch (\Exception $e) {
            $this->error("Error: " . $e->getMessage());
            Log::error('HCC Vue scrape failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
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
            '--headless=new',
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






