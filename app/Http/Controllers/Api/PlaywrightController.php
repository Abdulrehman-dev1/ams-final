<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\HccAttendanceTransaction;
use App\Models\HccDevice;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PlaywrightController extends Controller
{
    /**
     * Save attendance records from Playwright scraper
     *
     * POST /api/playwright/save-attendance
     */
    public function saveAttendance(Request $request)
    {
        $records = $request->input('records', []);

        if (empty($records)) {
            return response()->json([
                'ok' => false,
                'message' => 'No records provided',
                'saved' => 0,
            ], 422);
        }

        $saved = 0;
        $errors = [];

        foreach ($records as $record) {
            try {
                $normalized = $this->normalizeAttendanceRecord($record);

                if ($normalized) {
                    HccAttendanceTransaction::updateOrCreate(
                        [
                            'person_code' => $normalized['person_code'],
                            'attendance_date' => $normalized['attendance_date'],
                            'attendance_time' => $normalized['attendance_time'],
                            'device_id' => $normalized['device_id'],
                        ],
                        $normalized
                    );
                    $saved++;
                }
            } catch (\Exception $e) {
                $errors[] = [
                    'record' => $record,
                    'error' => $e->getMessage(),
                ];
                Log::warning("Playwright: Failed to save attendance record", [
                    'record' => $record,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return response()->json([
            'ok' => true,
            'saved' => $saved,
            'total' => count($records),
            'errors' => count($errors),
            'error_details' => $errors,
        ]);
    }

    /**
     * Save device records from Playwright scraper
     *
     * POST /api/playwright/save-devices
     */
    public function saveDevices(Request $request)
    {
        $devices = $request->input('devices', []);

        if (empty($devices)) {
            return response()->json([
                'ok' => false,
                'message' => 'No devices provided',
                'saved' => 0,
            ], 422);
        }

        $saved = 0;

        foreach ($devices as $device) {
            try {
                HccDevice::updateOrCreate(
                    ['device_id' => $device['device_id'] ?? $device['deviceId']],
                    [
                        'name' => $device['name'] ?? $device['deviceName'] ?? null,
                        'serial_no' => $device['serial_no'] ?? $device['serialNo'] ?? null,
                        'category' => $device['category'] ?? null,
                        'raw' => $device,
                    ]
                );
                $saved++;
            } catch (\Exception $e) {
                Log::warning("Playwright: Failed to save device", [
                    'device' => $device,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return response()->json([
            'ok' => true,
            'saved' => $saved,
            'total' => count($devices),
        ]);
    }

    /**
     * Normalize attendance record from various formats
     */
    protected function normalizeAttendanceRecord(array $record): ?array
    {
        // Handle different field naming conventions
        $personCode = $record['personCode'] ?? $record['person_code'] ?? null;
        $fullName = $record['fullName'] ?? $record['full_name'] ?? $record['personName'] ?? 'Unknown';
        $clockStamp = $record['clockStamp'] ?? $record['attendance_datetime'] ?? $record['timestamp'] ?? null;

        if (!$personCode || !$clockStamp) {
            return null;
        }

        try {
            $dt = Carbon::parse($clockStamp)->setTimezone(config('hcc.timezone', 'Asia/Karachi'));
        } catch (\Exception $e) {
            Log::warning("Playwright: Failed to parse clockStamp", [
                'clockStamp' => $clockStamp,
                'error' => $e->getMessage(),
            ]);
            return null;
        }

        return [
            'person_code' => $personCode,
            'full_name' => $fullName,
            'department' => $record['department'] ?? $record['groupName'] ?? null,
            'attendance_date' => $dt->format('Y-m-d'),
            'attendance_time' => $dt->format('H:i:s'),
            'device_id' => $record['deviceId'] ?? $record['device_id'] ?? $record['device'] ?? null,
            'device_name' => $record['deviceName'] ?? $record['device_name'] ?? null,
            'device_serial' => $record['deviceSerial'] ?? $record['serial_no'] ?? null,
            'weekday' => $record['weekday'] ?? $dt->format('l'),
            'source_data' => $record,
        ];
    }
}

