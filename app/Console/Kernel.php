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
        // Refresh HIK Token - Every 3 hours
        $schedule->job(new \App\Jobs\RefreshHikTokenJob)
            ->everyThreeHours()
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo(storage_path('logs/hik-token-refresh.log'));

        // Sync HIK Employees - Every 6 hours
        $schedule->job(new \App\Jobs\SyncHikEmployeesJob)
            ->everySixHours()
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo(storage_path('logs/hik-employees-sync.log'));

        // HCC Attendance Sync - Every 5 minutes (Python Playwright)
        $schedule->command('hcc:sync')
            ->everyFiveMinutes()
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo(storage_path('logs/hcc-sync.log'));

        // HikCentral Connect: Sync devices daily at 3:05 AM (API-based)
        $schedule->command('hcc:sync:devices')
            ->dailyAt('03:05')
            ->runInBackground()
            ->appendOutputTo(storage_path('logs/hcc-devices.log'));
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
