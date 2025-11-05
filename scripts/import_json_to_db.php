<?php
/**
 * Import table_scraped_data.json to database
 * Run: php import_json_to_db.php
 */

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\HccAttendanceTransaction;
use Carbon\Carbon;

echo "=" . str_repeat("=", 59) . "\n";
echo "💾 Importing table data to database...\n";
echo "=" . str_repeat("=", 59) . "\n\n";

$jsonFile = __DIR__ . '/table_scraped_data.json';

if (!file_exists($jsonFile)) {
    echo "❌ File not found: $jsonFile\n";
    echo "   Run the browser script first: python hcc_debug_browser.py\n";
    exit(1);
}

$tableData = json_decode(file_get_contents($jsonFile), true);

echo "📊 Found " . count($tableData) . " rows\n\n";

$saved = 0;
$errors = 0;

foreach ($tableData as $index => $row) {
    if (count($row) < 6) {
        continue; // Skip incomplete rows
    }
    
    // Map ALL table columns (based on HCC table structure)
    $firstName = $row[0] ?? '';
    $lastName = $row[1] ?? '';
    $personCode = $row[2] ?? '';
    $fullPath = $row[3] ?? '';           // Department
    $clockDate = $row[4] ?? '';
    $clockTime = $row[5] ?? '';
    $week = $row[6] ?? '';
    $dataSource = $row[7] ?? '';         // Mobile App / Device
    $deviceName = $row[8] ?? '';
    $deviceSerial = $row[9] ?? '';       // Device Serial No.
    $punchState = $row[10] ?? '';
    $location = $row[11] ?? '';          // Full address (from title attribute)
    $remark = $row[12] ?? '';
    
    $fullName = trim("$firstName $lastName");
    
    if (empty($personCode) || empty($clockDate)) {
        continue; // Skip if missing required fields
    }
    
    // Convert date format (DD-MM-YYYY to YYYY-MM-DD)
    try {
        $dateParts = explode('-', $clockDate);
        if (count($dateParts) === 3) {
            $attendanceDate = sprintf('%s-%s-%s', $dateParts[2], $dateParts[1], $dateParts[0]);
        } else {
            $attendanceDate = $clockDate;
        }
        
        $clockStamp = "$attendanceDate $clockTime";
        
        // Save with ALL fields
        HccAttendanceTransaction::updateOrCreate(
            [
                'person_code' => $personCode,
                'attendance_date' => $attendanceDate,
                'attendance_time' => $clockTime,
                'device_id' => $deviceName ?: null,
            ],
            [
                'full_name' => $fullName,
                'department' => $fullPath,
                'device_name' => $deviceName ?: null,
                'device_serial' => $deviceSerial ?: null,
                'weekday' => $week,
                'source_data' => [
                    'firstName' => $firstName,
                    'lastName' => $lastName,
                    'dataSource' => $dataSource,
                    'location' => $location,
                    'remark' => $remark,
                    'punchState' => $punchState,
                    'raw_row' => $row,
                ],
            ]
        );
        
        $saved++;
        
        if ($saved % 10 === 0) {
            echo "   Progress: $saved records saved...\n";
        }
        
    } catch (Exception $e) {
        $errors++;
        echo "   ⚠️ Error on row " . ($index + 1) . ": " . $e->getMessage() . "\n";
    }
}

echo "\n" . str_repeat("=", 60) . "\n";
echo "✅ Import complete!\n";
echo "   Saved: $saved records\n";
echo "   Errors: $errors\n";
echo "   Total in DB: " . HccAttendanceTransaction::count() . "\n";
echo str_repeat("=", 60) . "\n";

