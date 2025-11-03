<?php

namespace App\Console\Commands;

use App\Exceptions\HccApiException;
use App\Services\HccAttendanceIngestor;
use Carbon\Carbon;
use Illuminate\Console\Command;

class HccIngestRange extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'hcc:ingest:range {--from= : Start date (YYYY-MM-DD)} {--to= : End date (YYYY-MM-DD)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Backfill attendance records for a date range from HikCentral Connect';

    protected HccAttendanceIngestor $ingestor;

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct(HccAttendanceIngestor $ingestor)
    {
        parent::__construct();
        $this->ingestor = $ingestor;
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $fromDate = $this->option('from');
        $toDate = $this->option('to');

        if (!$fromDate || !$toDate) {
            $this->error("Both --from and --to options are required (format: YYYY-MM-DD)");
            return Command::FAILURE;
        }

        try {
            $timezone = config('hcc.timezone', 'Asia/Karachi');
            $from = Carbon::createFromFormat('Y-m-d', $fromDate, $timezone)->startOfDay();
            $to = Carbon::createFromFormat('Y-m-d', $toDate, $timezone)->endOfDay();
        } catch (\Exception $e) {
            $this->error("Invalid date format. Use YYYY-MM-DD");
            return Command::FAILURE;
        }

        if ($from->gt($to)) {
            $this->error("Start date must be before or equal to end date");
            return Command::FAILURE;
        }

        $this->info("HCC Ingestion Range: {$from->toDateString()} to {$to->toDateString()}");

        $totalUpserted = 0;
        $current = $from->copy();

        try {
            while ($current->lte($to)) {
                $dayStart = $current->copy()->startOfDay();
                $dayEnd = $current->copy()->endOfDay();

                // Don't go beyond the end date
                if ($dayEnd->gt($to)) {
                    $dayEnd = $to->copy();
                }

                $this->line("Processing {$current->toDateString()}...");

                $count = $this->ingestor->ingestWindow($dayStart, $dayEnd);
                $totalUpserted += $count;

                $this->info("  → {$count} records upserted");

                $current->addDay();
            }

            $this->info("✓ Total records upserted: {$totalUpserted}");

            return Command::SUCCESS;
        } catch (HccApiException $e) {
            if ($e->isAuthError()) {
                $this->error("✗ Authentication failed. Please update HCC_BEARER_TOKEN or HCC_COOKIE in .env");
            } else {
                $this->error("✗ HCC API error: " . $e->getMessage());
            }

            return Command::FAILURE;
        } catch (\Exception $e) {
            $this->error("✗ Unexpected error: " . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
