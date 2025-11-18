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
        
        // Diagnostic information
        $this->line("🔍 Diagnostic Information:");
        $this->line("   OS: " . PHP_OS);
        $this->line("   Working Directory: " . base_path());
        $this->line("   PHP Binary: " . PHP_BINARY);
        $this->line("");
        
        $pythonPath = $this->findPythonPath();
        $scriptPath = base_path('scripts/hcc_final_auto.py');
        
        if (!$pythonPath) {
            $this->error("❌ Python not found. Please set PYTHON_PATH in .env or install Python.");
            $this->line("");
            $this->line("   Common paths on Windows: 'py', 'python3', 'python', or full path like 'C:\\Python\\python.exe'");
            $this->line("");
            $this->line("   Attempted paths:");
            $this->showSearchResults();
            return Command::FAILURE;
        }
        
        if (!file_exists($scriptPath)) {
            $this->error("❌ Script not found: {$scriptPath}");
            $this->line("   Absolute path: " . realpath($scriptPath) ?: 'NOT FOUND');
            $scriptsDir = base_path('scripts');
            $this->line("   Scripts directory exists: " . (is_dir($scriptsDir) ? 'YES' : 'NO'));
            if (is_dir($scriptsDir)) {
                $this->line("   Files in scripts directory: " . implode(', ', array_slice(scandir($scriptsDir), 0, 10)));
            }
            return Command::FAILURE;
        }
        
        $this->info("🚀 Running sync...");
        $this->line("   Python: {$pythonPath}");
        $this->line("   Script: {$scriptPath}");
        $this->line("   Script exists: " . (file_exists($scriptPath) ? 'YES' : 'NO'));
        $this->line("");
        
        try {
            $command = [$pythonPath, $scriptPath];
            $workingDir = base_path('scripts');
            
            $this->line("📋 Command details:");
            $this->line("   Command: " . implode(' ', $command));
            $this->line("   Working Directory: {$workingDir}");
            $this->line("   Working Directory exists: " . (is_dir($workingDir) ? 'YES' : 'NO'));
            $this->line("");
            
            $process = new Process($command, $workingDir, [
                'PYTHONIOENCODING' => 'utf-8',
                'PYTHONUTF8' => '1',
            ], null, 600);
            
            $errorOutput = '';
            $process->run(function ($type, $buffer) use (&$errorOutput) {
                if ($type === Process::ERR) {
                    $errorOutput .= $buffer;
                    $this->error($buffer);
                } else {
                    echo $buffer;
                }
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
                $this->line("");
                $this->error("Exit Code: " . $process->getExitCode());
                
                $fullErrorOutput = $errorOutput ?: $process->getErrorOutput();
                $this->error("Error Output:");
                $this->line($fullErrorOutput);
                
                // Check for common errors and provide helpful messages
                if (str_contains($fullErrorOutput, 'ModuleNotFoundError') || str_contains($fullErrorOutput, 'No module named')) {
                    $this->line("");
                    $this->warn("⚠️  Python dependencies are missing!");
                    $this->line("");
                    $this->info("To fix this, install the required Python packages:");
                    $this->line("");
                    $this->line("   1. Navigate to the scripts directory:");
                    $this->line("      cd " . base_path('scripts'));
                    $this->line("");
                    $this->line("   2. Install the requirements:");
                    $this->line("      pip install -r requirements.txt");
                    $this->line("");
                    $this->line("   3. Install Playwright browsers (if needed):");
                    $this->line("      playwright install");
                    $this->line("");
                    $this->line("   Or use Python Launcher on Windows:");
                    $this->line("      py -m pip install -r requirements.txt");
                    $this->line("      py -m playwright install");
                }
                
                $this->line("");
                $this->line("Full command that failed:");
                $this->line("   " . implode(' ', array_map(function($arg) {
                    return escapeshellarg($arg);
                }, $command)));
                return Command::FAILURE;
            }
            
        } catch (\Exception $e) {
            $this->error("❌ Exception occurred: " . $e->getMessage());
            $this->line("");
            $this->line("Stack trace:");
            $this->line($e->getTraceAsString());
            \Log::error('HCC Sync Exception', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'python_path' => $pythonPath ?? null,
                'script_path' => $scriptPath ?? null,
            ]);
            return Command::FAILURE;
        }
    }
    
    /**
     * Find Python executable path
     * Tries common paths on Windows/Linux/Mac
     */
    private function findPythonPath(): ?string
    {
        // First, try config
        $configPath = config('hcc.python_path');
        if ($configPath && $configPath !== 'python') {
            $this->line("   Checking configured path: {$configPath}");
            if ($this->isPythonExecutable($configPath)) {
                $this->line("   ✅ Found Python at configured path: {$configPath}");
                return $configPath;
            } else {
                $this->line("   ❌ Configured path invalid: {$configPath}");
            }
        }
        
        // Common Python command names to try
        $candidates = ['py', 'python3', 'python'];
        
        // On Windows, also try common installation paths
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            $commonPaths = [
                'C:\\Python\\python.exe',
                'C:\\Python3\\python.exe',
                'C:\\Python39\\python.exe',
                'C:\\Python310\\python.exe',
                'C:\\Python311\\python.exe',
                'C:\\Python312\\python.exe',
                'C:\\Program Files\\Python\\python.exe',
                'C:\\Program Files\\Python3\\python.exe',
                'C:\\Program Files (x86)\\Python\\python.exe',
                'C:\\Program Files (x86)\\Python3\\python.exe',
            ];
            
            foreach ($commonPaths as $path) {
                $this->line("   Checking: {$path}");
                if (file_exists($path)) {
                    $this->line("      File exists: YES");
                    if ($this->isPythonExecutable($path)) {
                        $this->line("      ✅ Valid Python: {$path}");
                        return $path;
                    } else {
                        $this->line("      ❌ Not a valid Python executable");
                    }
                } else {
                    $this->line("      File exists: NO");
                }
            }
        }
        
        // Try command names
        foreach ($candidates as $cmd) {
            $this->line("   Checking command: {$cmd}");
            if ($this->isPythonExecutable($cmd)) {
                $this->line("      ✅ Found: {$cmd}");
                return $cmd;
            } else {
                $this->line("      ❌ Not found or invalid");
            }
        }
        
        return null;
    }
    
    /**
     * Show search results for debugging
     */
    private function showSearchResults(): void
    {
        // This is already called from findPythonPath, so we just add a note
        $this->line("   (See above for all attempted paths)");
    }
    
    /**
     * Check if a command/path is a valid Python executable
     */
    private function isPythonExecutable($path): bool
    {
        try {
            // For file paths, check if file exists first
            if (str_contains($path, '\\') || str_contains($path, '/')) {
                if (!file_exists($path)) {
                    return false;
                }
            }
            
            $process = new Process([$path, '--version'], base_path(), null, null, 5);
            $process->run();
            
            $output = $process->getOutput() . $process->getErrorOutput();
            $isValid = $process->isSuccessful() && (
                str_contains($output, 'Python') || 
                str_contains(strtolower($output), 'python')
            );
            
            if (!$isValid && $process->getExitCode() !== 0) {
                // Log the actual error for debugging
                \Log::debug("Python check failed for: {$path}", [
                    'exit_code' => $process->getExitCode(),
                    'output' => $output,
                    'error_output' => $process->getErrorOutput(),
                ]);
            }
            
            return $isValid;
        } catch (\Exception $e) {
            \Log::debug("Python check exception for: {$path}", [
                'exception' => $e->getMessage(),
            ]);
            return false;
        }
    }
}

