<?php

namespace App\Console\Commands;

use App\Jobs\SyncHikEmployeesJob;
use Illuminate\Console\Command;
use App\Models\DailyEmployee;

class TestSyncHikEmployees extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'hik:sync-employees {action=run : Action to perform (run|stats|clear)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync employees from Hikvision API or view stats';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $action = $this->argument('action');

        switch ($action) {
            case 'run':
                return $this->runSync();
            case 'stats':
                return $this->showStats();
            case 'clear':
                return $this->clearData();
            default:
                $this->error("Invalid action: {$action}");
                $this->info("Available actions: run, stats, clear");
                return self::FAILURE;
        }
    }

    /**
     * Run the sync job
     */
    private function runSync()
    {
        $this->info('🚀 Starting HIK Employees Sync...');
        $this->newLine();

        // Check token
        $token = env('HIK_TOKEN');
        if (!$token) {
            $this->error('❌ HIK_TOKEN not set in .env file');
            return self::FAILURE;
        }

        $this->info('Token: ' . substr($token, 0, 20) . '...');
        $this->newLine();

        $before = DailyEmployee::count();
        $this->info("Current employee count: {$before}");
        $this->newLine();

        try {
            $this->info('Running SyncHikEmployeesJob...');
            $this->newLine();

            $job = new SyncHikEmployeesJob();
            $job->handle();

            $after = DailyEmployee::count();
            $diff = $after - $before;

            $this->newLine();
            $this->info('✅ Sync completed successfully!');
            $this->newLine();

            $this->table(
                ['Metric', 'Value'],
                [
                    ['Before Count', $before],
                    ['After Count', $after],
                    ['Difference', $diff > 0 ? "+{$diff}" : $diff],
                ]
            );

            return self::SUCCESS;

        } catch (\Exception $e) {
            $this->newLine();
            $this->error('❌ Sync failed: ' . $e->getMessage());
            $this->error($e->getTraceAsString());
            return self::FAILURE;
        }
    }

    /**
     * Show employee statistics
     */
    private function showStats()
    {
        $this->info('📊 HIK Employees Statistics');
        $this->newLine();

        $total = DailyEmployee::count();
        $withPhoto = DailyEmployee::whereNotNull('head_pic_url')->count();
        $withPhone = DailyEmployee::whereNotNull('phone')->count();
        $withEmail = DailyEmployee::whereNotNull('email')->count();

        $latest = DailyEmployee::latest('updated_at')->first();
        $oldest = DailyEmployee::oldest('created_at')->first();

        $this->table(
            ['Metric', 'Count'],
            [
                ['Total Employees', $total],
                ['With Photo', "{$withPhoto} (" . ($total > 0 ? round($withPhoto/$total*100, 1) : 0) . "%)"],
                ['With Phone', "{$withPhone} (" . ($total > 0 ? round($withPhone/$total*100, 1) : 0) . "%)"],
                ['With Email', "{$withEmail} (" . ($total > 0 ? round($withEmail/$total*100, 1) : 0) . "%)"],
            ]
        );

        if ($latest) {
            $this->newLine();
            $this->info('Latest Updated Employee:');
            $this->line("  Name: {$latest->display_name}");
            $this->line("  Person Code: {$latest->person_code}");
            $this->line("  Updated: {$latest->updated_at->diffForHumans()}");
        }

        if ($oldest) {
            $this->newLine();
            $this->info('First Synced Employee:');
            $this->line("  Name: {$oldest->display_name}");
            $this->line("  Person Code: {$oldest->person_code}");
            $this->line("  Created: {$oldest->created_at->diffForHumans()}");
        }

        // Group statistics
        $this->newLine();
        $groups = DailyEmployee::whereNotNull('group_name')
            ->selectRaw('group_name, COUNT(*) as count')
            ->groupBy('group_name')
            ->orderBy('count', 'desc')
            ->limit(10)
            ->get();

        if ($groups->count() > 0) {
            $this->info('Top 10 Groups:');
            $groupData = [];
            foreach ($groups as $group) {
                $groupData[] = [$group->group_name ?: 'Unknown', $group->count];
            }
            $this->table(['Group Name', 'Employees'], $groupData);
        }

        return self::SUCCESS;
    }

    /**
     * Clear all employee data
     */
    private function clearData()
    {
        $count = DailyEmployee::count();

        if ($count === 0) {
            $this->info('No employees to clear.');
            return self::SUCCESS;
        }

        $this->warn("⚠️  This will delete {$count} employee records!");
        
        if (!$this->confirm('Are you sure you want to continue?')) {
            $this->info('Cancelled.');
            return self::SUCCESS;
        }

        DailyEmployee::truncate();

        $this->info("✅ Successfully deleted {$count} employee records.");

        return self::SUCCESS;
    }
}

