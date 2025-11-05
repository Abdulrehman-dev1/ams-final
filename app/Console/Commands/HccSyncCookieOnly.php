<?php

namespace App\Console\Commands;

use App\Models\HccAttendanceTransaction;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class HccSyncCookieOnly extends Command
{
    protected $signature = 'hcc:sync-live';

    protected $description = 'HCC Sync using cookie only (no browser - for production server)';

    public function handle()
    {
        $this->info("🔄 HCC Live Sync (Cookie-based API)");
        $this->line(str_repeat("=", 60));
        
        // Get cookie from env
        $cookie = config('hcc.cookie');
        if (!$cookie) {
            $this->error("❌ HCC_COOKIE not found in .env!");
            $this->warn("   Update cookie from Windows machine daily");
            return Command::FAILURE;
        }
        
        // Date range (today)
        $tz = config('hcc.timezone', 'Asia/Karachi');
        $today = Carbon::now($tz);
        
        $this->info("📅 Date: {$today->toDateString()}");
        $this->line("");
        
        // API endpoint
        $endpoint = config('hcc.base_url') . config('hcc.endpoints.attendance_list');
        
        $fromDt = $today->copy()->startOfDay()->format('Y-m-d\TH:i:sP');
        $toDt = $today->copy()->endOfDay()->format('Y-m-d\TH:i:sP');
        
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
        
        $this->info("📡 Fetching from API...");
        
        try {
            $response = Http::withHeaders([
                'Cookie' => $cookie,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)',
            ])->timeout(30)->post($endpoint, $payload);
            
            if (!$response->successful()) {
                $this->error("❌ API Error: " . $response->status());
                $body = $response->json();
                
                if (isset($body['errorCode']) && $body['errorCode'] === 'VMS002004') {
                    $this->error("🔑 Cookie expired! Update HCC_COOKIE in .env");
                    $this->warn("   Run on Windows: python scripts/hcc_debug_browser.py");
                    $this->warn("   Get cookie from browser console");
                }
                
                return Command::FAILURE;
            }
            
            $data = $response->json();
            $records = $data['data']['reportDataList'] ?? [];
            
            $this->info("✅ Fetched {$count} records", ['count' => count($records)]);
            
            if (empty($records)) {
                $this->warn("⚠️  No records for today yet");
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
                    Log::error("HCC Sync error", ['error' => $e->getMessage(), 'record' => $record]);
                }
            }
            
            $bar->finish();
            
            $this->newLine(2);
            $this->info("✅ Sync complete!");
            $this->line("   Saved: {$saved}");
            $this->line("   Total in DB: " . HccAttendanceTransaction::count());
            
            return Command::SUCCESS;
            
        } catch (\Exception $e) {
            $this->error("❌ Error: " . $e->getMessage());
            return Command::FAILURE;
        }
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
        
        // Save
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
                    'location' => $locationText,
                    'latitude' => $latitude,
                    'longitude' => $longitude,
                    'briefAddress' => $locationData['briefAddress'] ?? null,
                    'detailAddress' => $locationData['detailAddress'] ?? null,
                    'remark' => $record['remark'] ?? '',
                    'raw_record' => $record,
                ],
            ]
        );
    }
}

