<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

class HccFinalAutoCommand extends Command
{
    protected $signature = 'hcc:sync';

    protected $description = 'HCC Auto Sync (Production - uses working browser script)';

    public function handle()
    {
        $this->info("🤖 HCC Auto Sync");
        $this->line(str_repeat("=", 60));
        
        $pythonPath = config('hcc.python_path', 'python');
        $scriptPath = base_path('scripts/hcc_final_auto.py');
        
        if (!file_exists($scriptPath)) {
            $this->error("❌ Script not found: {$scriptPath}");
            return Command::FAILURE;
        }
        
        $this->info("🚀 Running sync...");
        $this->line("");
        
        try {
            $command = [$pythonPath, $scriptPath];
            
            $process = new Process($command, base_path('scripts'), [
                'PYTHONIOENCODING' => 'utf-8',
                'PYTHONUTF8' => '1',
            ], null, 600);
            
            $process->run(function ($type, $buffer) {
                echo $buffer;
            });
            
            if ($process->isSuccessful()) {
                $this->newLine();
                $this->info("✅ Sync completed!");
                
                // Show stats
                $count = \App\Models\HccAttendanceTransaction::count();
                $todayCount = \App\Models\HccAttendanceTransaction::whereDate('attendance_date', today())->count();
                
                $this->line("");
                $this->info("📊 Database Stats:");
                $this->line("   Total: {$count}");
                $this->line("   Today: {$todayCount}");
                
                return Command::SUCCESS;
            } else {
                $this->newLine();
                $this->error("❌ Sync failed!");
                return Command::FAILURE;
            }
            
        } catch (\Exception $e) {
            $this->error("❌ Error: " . $e->getMessage());
            return Command::FAILURE;
        }
    }
}

