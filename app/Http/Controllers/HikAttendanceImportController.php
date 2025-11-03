<?php

namespace App\Http\Controllers;

use App\Models\HccAttendanceTransaction;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class HikAttendanceImportController extends Controller
{
    public function import(Request $request)
    {
        $records = $request->input('records', []);
        $imported = 0;
        $errors = 0;

        Log::info("HCC Import: Received " . count($records) . " records");

        foreach ($records as $record) {
            try {
                $personCode = $record['personCode'] ?? null;
                $clockStamp = $record['clockStamp'] ?? null;

                if (!$personCode || !$clockStamp) {
                    $errors++;
                    continue;
                }

                $dt = Carbon::parse($clockStamp)->setTimezone('Asia/Karachi');

                HccAttendanceTransaction::updateOrCreate(
                    [
                        'person_code' => $personCode,
                        'attendance_date' => $dt->format('Y-m-d'),
                        'attendance_time' => $dt->format('H:i:s'),
                    ],
                    [
                        'full_name' => $record['fullName'] ?? null,
                        'department' => $record['fullPath'] ?? null,
                        'device_id' => $record['deviceId'] ?? null,
                        'device_name' => $record['deviceName'] ?? null,
                        'device_serial' => $record['deviceSerial'] ?? null,
                        'weekday' => $record['week'] ?? $dt->format('l'),
                        'source_data' => $record,
                    ]
                );
                $imported++;
            } catch (\Exception $e) {
                $errors++;
                Log::warning("Failed to import record", [
                    'error' => $e->getMessage(),
                    'record' => $record,
                ]);
            }
        }

        return response()->json([
            'success' => true,
            'imported' => $imported,
            'errors' => $errors,
            'total' => count($records),
        ]);
    }
}





