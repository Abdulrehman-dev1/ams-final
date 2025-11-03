<?php

namespace App\Console\Commands;

use App\Exceptions\HccApiException;
use App\Services\HccDevicesSync;
use Illuminate\Console\Command;

class HccSyncDevices extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'hcc:sync:devices';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync devices from HikCentral Connect';

    protected HccDevicesSync $sync;

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct(HccDevicesSync $sync)
    {
        parent::__construct();
        $this->sync = $sync;
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info("HCC Device Sync: Starting...");

        try {
            $startTime = microtime(true);
            $count = $this->sync->sync();
            $duration = round(microtime(true) - $startTime, 2);

            $this->info("✓ HCC device sync: {$count} devices synced in {$duration}s");

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
