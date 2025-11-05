<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

class HccAutoSyncCommand extends Command
{
    protected $signature = 'hcc:auto-sync 
                            {--yesterday : Sync yesterday data}
                            {--from= : Start date (Y-m-d)}
                            {--to= : End date (Y-m-d)}';

    protected $description = 'Automated HCC sync (browser scrape + database save)';

    public function handle()
    {
        $this->info("🤖 HCC Auto Sync");
        $this->line(str_repeat("=", 60));
        
        // Determine date range
        if ($this->option('yesterday')) {
            $date = \Carbon\Carbon::yesterday()->toDateString();
            $this->info("📅 Syncing: Yesterday ({$date})");
        } elseif ($this->option('from')) {
            $from = $this->option('from');
            $to = $this->option('to') ?: $from;
            $this->info("📅 Syncing: {$from} to {$to}");
        } else {
            $date = \Carbon\Carbon::today()->toDateString();
            $this->info("📅 Syncing: Today ({$date})");
        }
        
        $this->line("");
        
        // Run Python automation script
        $pythonPath = config('hcc.python_path', 'python');
        $scriptPath = base_path('scripts/hcc_auto_sync.py');
        
        if (!file_exists($scriptPath)) {
            $this->error("❌ Script not found: {$scriptPath}");
            return Command::FAILURE;
        }
        
        $this->info("🚀 Running automation...");
        $this->line("");
        
        try {
            $command = [$pythonPath, $scriptPath];
            
            $process = new Process($command, base_path('scripts'), [
                'PYTHONIOENCODING' => 'utf-8',
                'PYTHONUTF8' => '1',
            ], null, 600); // 10 minutes timeout
            
            $process->run(function ($type, $buffer) {
                echo $buffer;
            });
            
            if ($process->isSuccessful()) {
                $this->newLine();
                $this->info("✅ Auto sync completed successfully!");
                
                // Show database stats
                $count = \App\Models\HccAttendanceTransaction::count();
                $todayCount = \App\Models\HccAttendanceTransaction::whereDate('attendance_date', today())->count();
                
                $this->line("");
                $this->info("📊 Database Stats:");
                $this->line("   Total records: {$count}");
                $this->line("   Today's records: {$todayCount}");
                
                return Command::SUCCESS;
            } else {
                $this->newLine();
                $this->error("❌ Auto sync failed!");
                $this->error($process->getErrorOutput());
                return Command::FAILURE;
            }
            
        } catch (\Exception $e) {
            $this->error("❌ Error: " . $e->getMessage());
            $this->line("");
            $this->warn("💡 Make sure Python and Playwright are installed");
            $this->warn("💡 Run: cd scripts && pip install -r requirements.txt");
            return Command::FAILURE;
        }
    }
}

