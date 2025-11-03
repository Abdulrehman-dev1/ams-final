<?php

namespace App\Console\Commands;

use App\Exceptions\HccApiException;
use App\Services\HccAttendanceIngestor;
use Carbon\Carbon;
use Illuminate\Console\Command;

class HccIngestRecent extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'hcc:ingest:recent';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Ingest recent attendance records from HikCentral Connect (last 10 minutes with look-back)';

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
        $timezone = config('hcc.timezone', 'Asia/Karachi');
        $lookbackMinutes = config('hcc.lookback_minutes', 10);

        $now = Carbon::now($timezone);
        $from = $now->copy()->subMinutes($lookbackMinutes);

        $this->info("HCC Ingestion: Fetching attendance from {$from->toDateTimeString()} to {$now->toDateTimeString()}");

        try {
            $startTime = microtime(true);
            $count = $this->ingestor->ingestWindow($from, $now);
            $duration = round(microtime(true) - $startTime, 2);

            $this->info("✓ HCC ingest: {$count} records upserted in {$duration}s");

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
