<?php

namespace App\Console\Commands;

use App\Models\HccAttendanceTransaction;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class HccSyncViaCookie extends Command
{
    protected $signature = 'hcc:sync-cookie 
                            {--from= : Start date (Y-m-d)}
                            {--to= : End date (Y-m-d)}
                            {--yesterday : Fetch yesterday data}';

    protected $description = 'Sync HCC attendance using cookie (no browser needed)';

    public function handle()
    {
        $this->info("🔄 HCC Sync via Cookie");
        $this->line(str_repeat("=", 60));
        
        // Get cookie from env
        $cookie = config('hcc.cookie');
        if (!$cookie) {
            $this->error("❌ HCC_COOKIE not found in .env!");
            $this->warn("   Add HCC_COOKIE to your .env file");
            return Command::FAILURE;
        }
        
        // Determine date range
        $tz = config('hcc.timezone', 'Asia/Karachi');
        
        if ($this->option('yesterday')) {
            $from = Carbon::yesterday($tz);
            $to = Carbon::yesterday($tz);
        } else {
            $fromInput = $this->option('from');
            $toInput = $this->option('to');
            
            $from = $fromInput ? Carbon::parse($fromInput, $tz) : Carbon::today($tz);
            $to = $toInput ? Carbon::parse($toInput, $tz) : Carbon::today($tz);
        }
        
        $this->info("📅 Date range: {$from->toDateString()} to {$to->toDateString()}");
        $this->line("");
        
        // Build API request
        $endpoint = config('hcc.base_url') . config('hcc.endpoints.attendance_list');
        
        $fromDt = $from->startOfDay()->format('Y-m-d\TH:i:sP');
        $toDt = $to->endOfDay()->format('Y-m-d\TH:i:sP');
        
        $payload = [
            'page' => 1,
            'pageSize' => 100,
            'language' => 'en',
            'reportTypeId' => 1,
            'columnIdList' => [],
            'filterList' => [
                ['columnName' => 'fullName', 'operation' => 'LIKE', 'value' => ''],
                ['columnName' => 'personCode', 'operation' => 'LIKE', 'value' => ''],
                ['columnName' => 'groupId', 'operation' => 'IN', 'value' => ''],
                ['columnName' => 'clockStamp', 'operation' => 'BETWEEN', 'value' => "{$fromDt},{$toDt}"],
                ['columnName' => 'deviceId', 'operation' => 'IN', 'value' => ''],
            ],
        ];
        
        $this->info("📤 Sending API request...");
        
        // Make API call
        $response = Http::withHeaders([
            'Cookie' => $cookie,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)',
        ])->timeout(30)->post($endpoint, $payload);
        
        if (!$response->successful()) {
            $this->error("❌ API Error: " . $response->status());
            $this->line($response->body());
            return Command::FAILURE;
        }
        
        $data = $response->json();
        
        // Extract records from reportDataList
        $records = $data['data']['reportDataList'] ?? [];
        
        $this->info("📊 Found {$count} records", ['count' => count($records)]);
        
        if (empty($records)) {
            $this->warn("⚠️  No records found for this date range");
            return Command::SUCCESS;
        }
        
        // Save to database
        $saved = 0;
        $bar = $this->output->createProgressBar(count($records));
        $bar->start();
        
        foreach ($records as $record) {
            try {
                $this->saveRecord($record);
                $saved++;
                $bar->advance();
            } catch (\Exception $e) {
                $this->error("\n⚠️  Error: " . $e->getMessage());
            }
        }
        
        $bar->finish();
        
        $this->line("\n\n" . str_repeat("=", 60));
        $this->info("✅ Sync complete!");
        $this->line("   Saved: {$saved} records");
        $this->line("   Total in DB: " . HccAttendanceTransaction::count());
        $this->line(str_repeat("=", 60));
        
        return Command::SUCCESS;
    }
    
    protected function saveRecord(array $record)
    {
        // Convert date (DD-MM-YYYY to YYYY-MM-DD)
        $clockDate = $record['clockDate'] ?? '';
        $dateParts = explode('-', $clockDate);
        
        if (count($dateParts) === 3) {
            $attendanceDate = sprintf('%s-%s-%s', $dateParts[2], $dateParts[1], $dateParts[0]);
        } else {
            $attendanceDate = $clockDate;
        }
        
        $clockTime = $record['clockTime'] ?? '00:00';
        
        // Extract location
        $locationData = $record['location'] ?? [];
        $locationText = '';
        $latitude = null;
        $longitude = null;
        
        if (is_array($locationData)) {
            $latitude = $locationData['latitude'] ?? null;
            $longitude = $locationData['longitude'] ?? null;
            $detailAddress = $locationData['detailAddress'] ?? '';
            $briefAddress = $locationData['briefAddress'] ?? '';
            
            if (!empty($detailAddress)) {
                $locationText = $detailAddress;
            } elseif (!empty($briefAddress)) {
                $locationText = $briefAddress;
            } elseif ($latitude && $longitude) {
                $locationText = "Lat: {$latitude}, Lng: {$longitude}";
            }
        }
        
        // Save with ALL fields
        HccAttendanceTransaction::updateOrCreate(
            [
                'person_code' => $record['personCode'],
                'attendance_date' => $attendanceDate,
                'attendance_time' => $clockTime,
                'device_id' => $record['deviceId'] ?? null,
            ],
            [
                'full_name' => $record['fullName'] ?? '',
                'department' => $record['fullPath'] ?? null,
                'device_name' => $record['deviceName'] ?? null,
                'device_serial' => $record['deviceSerial'] ?? null,
                'weekday' => $record['week'] ?? null,
                'source_data' => [
                    'firstName' => $record['firstName'] ?? '',
                    'lastName' => $record['lastName'] ?? '',
                    'dataSource' => $record['dataSource'] ?? '',
                    
                    // Location details (COMPLETE)
                    'location' => $locationText,
                    'latitude' => $latitude,
                    'longitude' => $longitude,
                    'briefAddress' => $locationData['briefAddress'] ?? null,
                    'detailAddress' => $locationData['detailAddress'] ?? null,
                    
                    'remark' => $record['remark'] ?? '',
                    'handleType' => $record['handleType'] ?? '',
                    'clockStamp' => $record['clockStamp'] ?? null,
                    'checkInStamp' => $record['checkInStamp'] ?? null,
                    'checkOutStamp' => $record['checkOutStamp'] ?? null,
                    
                    // Complete raw record
                    'raw_api_record' => $record,
                ],
            ]
        );
    }
}

