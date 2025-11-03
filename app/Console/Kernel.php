<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        //
    ];

    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        // HCC Attendance Scraping - Every 5 minutes (using table scraper)
        $schedule->command('hcc:scrape-table --from=' . now()->subDays(1)->format('Y-m-d') . ' --to=' . now()->format('Y-m-d'))
            ->everyFiveMinutes()
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo(storage_path('logs/hcc-scraper.log'));

        // HikCentral Connect: Scrape devices daily at 3:05 AM (using Dusk)
        $schedule->command('hcc:scrape:devices')
            ->dailyAt('03:05')
            ->runInBackground()
            ->appendOutputTo(storage_path('logs/hcc-scraper.log'));

        // Fallback: API-based ingestion (if Dusk is not available)
        // $schedule->command('hcc:ingest:recent')
        //     ->everyFiveMinutes()
        //     ->withoutOverlapping()
        //     ->runInBackground()
        //     ->appendOutputTo(storage_path('logs/hcc-ingest.log'));
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
