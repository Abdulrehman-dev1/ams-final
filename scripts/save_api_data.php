<?php
/**
 * Save API data (reportDataList) to database
 * Better than table scraping - has complete location data
 */

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\HccAttendanceTransaction;
use Carbon\Carbon;

echo "=" . str_repeat("=", 70) . "\n";
echo "💾 Saving API data (reportDataList) to database...\n";
echo "=" . str_repeat("=", 70) . "\n\n";

// Read the API data (reportDataList) from Python script output
$apiDataFile = __DIR__ . '/api_reportDataList.json';

if (!file_exists($apiDataFile)) {
    echo "⚠️  File not found: api_reportDataList.json\n\n";
    echo "💡 Run Python script first:\n";
    echo "   python hcc_debug_browser.py\n\n";
    exit(1);
}

$apiData = json_decode(file_get_contents($apiDataFile), true);

echo "📊 Found " . count($apiData) . " records\n\n";

$saved = 0;
$errors = 0;
$skipped = 0;

foreach ($apiData as $index => $record) {
    // Required fields check
    if (empty($record['personCode']) || empty($record['clockDate'])) {
        $skipped++;
        continue;
    }
    
    // Convert date (DD-MM-YYYY to YYYY-MM-DD)
    $dateParts = explode('-', $record['clockDate']);
    if (count($dateParts) === 3) {
        $attendanceDate = sprintf('%s-%s-%s', $dateParts[2], $dateParts[1], $dateParts[0]);
    } else {
        $attendanceDate = $record['clockDate'];
    }
    
    $clockTime = $record['clockTime'] ?? '00:00';
    
    // Extract location details
    $locationData = $record['location'] ?? [];
    $locationText = '';
    $latitude = null;
    $longitude = null;
    
    if (is_array($locationData)) {
        $latitude = $locationData['latitude'] ?? null;
        $longitude = $locationData['longitude'] ?? null;
        $briefAddress = $locationData['briefAddress'] ?? '';
        $detailAddress = $locationData['detailAddress'] ?? '';
        
        // Build location text
        if (!empty($detailAddress)) {
            $locationText = $detailAddress;
        } elseif (!empty($briefAddress)) {
            $locationText = $briefAddress;
        } elseif ($latitude && $longitude) {
            $locationText = "Lat: $latitude, Lng: $longitude";
        }
    }
    
    try {
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
                    'handleType' => $record['handleType'] ?? '',
                    'complete_record' => $record,
                ],
            ]
        );
        
        $saved++;
        
        if ($saved % 20 === 0) {
            echo "   Progress: $saved records saved...\n";
        }
        
    } catch (Exception $e) {
        $errors++;
        echo "   ⚠️ Error on record " . ($index + 1) . ": " . $e->getMessage() . "\n";
    }
}

echo "\n" . str_repeat("=", 70) . "\n";
echo "✅ Import complete!\n";
echo "   Saved: $saved records\n";
echo "   Skipped: $skipped (missing required fields)\n";
echo "   Errors: $errors\n";
echo "   Total in DB: " . HccAttendanceTransaction::count() . "\n";
echo str_repeat("=", 70) . "\n\n";

echo "📊 Sample record check:\n";
$sample = HccAttendanceTransaction::latest()->first();
if ($sample) {
    echo "   Name: {$sample->full_name}\n";
    echo "   Code: {$sample->person_code}\n";
    echo "   Date: {$sample->attendance_date}\n";
    echo "   Device: {$sample->device_name}\n";
    echo "   Source: " . ($sample->source_data['dataSource'] ?? 'N/A') . "\n";
    echo "   Location: " . ($sample->source_data['location'] ?? 'N/A') . "\n";
}

