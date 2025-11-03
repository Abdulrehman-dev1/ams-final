<?php

namespace App\Console\Commands;

use App\Services\HccDuskScraper;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Laravel\Dusk\Browser;

class HccScrapeAttendance extends Command
{
    protected $signature = 'hcc:scrape:attendance {--from= : Start date (Y-m-d)} {--to= : End date (Y-m-d)}';
    protected $description = 'Scrape attendance data from HikCentral Connect using browser automation';

    public function handle()
    {
        $from = $this->option('from');
        $to = $this->option('to');

        if (!$from || !$to) {
            $this->error('Both --from and --to options are required');
            return Command::FAILURE;
        }

        try {
            $fromDate = Carbon::parse($from);
            $toDate = Carbon::parse($to);
        } catch (\Exception $e) {
            $this->error('Invalid date format. Use Y-m-d');
            return Command::FAILURE;
        }

        $this->info("Scraping attendance from {$from} to {$to}...");

        $scraper = app(HccDuskScraper::class);
        $totalSaved = 0;

        $this->browse(function (Browser $browser) use ($scraper, $fromDate, $toDate, &$totalSaved) {
            // Login
            $scraper->login($browser);

            // Navigate to attendance page
            $scraper->navigateToAttendance($browser);

            // Inject API capture
            $scraper->injectApiCapture($browser);

            // Set date range
            $scraper->setDateRange($browser, $fromDate, $toDate);

            // Wait for data to load
            $browser->pause(3000);

            // Extract data
            $records = $scraper->extractAttendanceData($browser);

            // Save records
            if (!empty($records)) {
                $totalSaved = $scraper->saveAttendanceRecords($records);
                $this->info("✓ Saved {$totalSaved} attendance records");
            } else {
                $this->warn("No records found for the specified date range");
            }
        });

        $this->info("Scraping completed! Total records: {$totalSaved}");

        return Command::SUCCESS;
    }
}
