<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;

class HccPlaywrightCommand extends Command
{
    protected $signature = 'hcc:playwright 
                            {action : Action to perform (get-cookies, fetch-today, fetch-range, fetch-recent)}
                            {--from= : Start date (Y-m-d)}
                            {--to= : End date (Y-m-d)}
                            {--headless : Run in headless mode}';

    protected $description = 'Run HCC Playwright scraper for attendance data';

    public function handle()
    {
        $action = $this->argument('action');
        $fromDate = $this->option('from');
        $toDate = $this->option('to');

        // Get Python executable path from config
        $pythonPath = config('hcc.python_path', 'python');
        $scriptPath = base_path('scripts/hcc_playwright_scraper.py');

        // Check if Python script exists
        if (!file_exists($scriptPath)) {
            $this->error("❌ Python script not found: {$scriptPath}");
            return Command::FAILURE;
        }

        // Build command array
        $command = [$pythonPath, $scriptPath, $action];

        // Add date arguments if provided
        if ($action === 'fetch-range') {
            if (!$fromDate || !$toDate) {
                $this->error('❌ --from and --to options are required for fetch-range');
                return Command::FAILURE;
            }
            $command[] = "--from={$fromDate}";
            $command[] = "--to={$toDate}";
        }

        $this->info("🚀 Running Playwright scraper: {$action}");
        $this->line("Command: " . implode(' ', $command));
        $this->line("");

        // Run Python script (Laravel 8 compatible)
        try {
            // Set environment variables for UTF-8 encoding (Windows fix)
            $env = [
                'PYTHONIOENCODING' => 'utf-8',
                'PYTHONUTF8' => '1',
            ];
            
            $process = new Process($command, base_path('scripts'), $env, null, 300);
            $process->run(function ($type, $buffer) {
                echo $buffer;
            });

            if ($process->isSuccessful()) {
                $this->newLine();
                $this->info("✅ Playwright scraper completed successfully!");
                return Command::SUCCESS;
            } else {
                $this->newLine();
                $this->error("❌ Playwright scraper failed!");
                $this->error($process->getErrorOutput());
                return Command::FAILURE;
            }
        } catch (\Exception $e) {
            $this->error("❌ Error running Python script: " . $e->getMessage());
            $this->line("");
            $this->warn("💡 Make sure Python is installed and in your PATH");
            $this->warn("💡 Run: cd scripts && pip install -r requirements.txt");
            return Command::FAILURE;
        }
    }
}

