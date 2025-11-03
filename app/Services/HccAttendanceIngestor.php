<?php

namespace App\Services;

use App\Exceptions\HccApiException;
use App\Models\HccAttendanceTransaction;
use App\Models\HccDevice;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class HccAttendanceIngestor
{
    protected HccClient $client;
    protected string $timezone;

    public function __construct(HccClient $client)
    {
        $this->client = $client;
        $this->timezone = config('hcc.timezone', 'Asia/Karachi');
    }

    /**
     * Ingest attendance records for a given time window.
     *
     * @param Carbon $from Start datetime
     * @param Carbon $to End datetime
     * @return int Number of records upserted
     * @throws HccApiException
     */
    public function ingestWindow(Carbon $from, Carbon $to): int
    {
        $startTime = microtime(true);
        $totalUpserted = 0;
        $totalFetched = 0;
        $duplicatesSkipped = 0;
        $page = 1;
        $pageSize = config('hcc.page_size', 100);

        // Convert to timezone-aware ISO8601 format
        $fromIso = $from->copy()->setTimezone($this->timezone)->format('Y-m-d\TH:i:sP');
        $toIso = $to->copy()->setTimezone($this->timezone)->format('Y-m-d\TH:i:sP');

        Log::info("HCC Attendance Ingestion started", [
            'from' => $fromIso,
            'to' => $toIso,
        ]);

        try {
            do {
                $response = $this->client->attendanceList($page, $pageSize, $fromIso, $toIso);

                // Debug: Log the raw response structure
                if ($page === 1) {
                    Log::info("HCC API Response Structure (page 1)", [
                        'keys' => array_keys($response),
                        'response_sample' => json_encode($response, JSON_PRETTY_PRINT),
                    ]);
                }

                // Extract records from response (adjust based on actual API response structure)
                $records = $this->extractRecordsFromResponse($response);
                $recordCount = count($records);
                $totalFetched += $recordCount;

                if ($recordCount > 0) {
                    $upserted = $this->upsertRecords($records);
                    $totalUpserted += $upserted;
                    $duplicatesSkipped += ($recordCount - $upserted);

                    Log::debug("HCC page {$page} processed", [
                        'fetched' => $recordCount,
                        'upserted' => $upserted,
                    ]);
                }

                $page++;

                // Continue if we got a full page (more records likely available)
            } while ($recordCount === $pageSize);

            $duration = round(microtime(true) - $startTime, 2);

            Log::info("HCC Attendance Ingestion completed", [
                'fetched' => $totalFetched,
                'upserts' => $totalUpserted,
                'duplicates_skipped' => $duplicatesSkipped,
                'duration_seconds' => $duration,
            ]);

            return $totalUpserted;
        } catch (HccApiException $e) {
            if ($e->isAuthError()) {
                Log::error("HCC Authentication failed. Please update HCC_BEARER_TOKEN or HCC_COOKIE in .env", [
                    'error' => $e->getMessage(),
                    'status' => $e->getResponse()?->status(),
                ]);
            } else {
                Log::error("HCC API error during ingestion", [
                    'error' => $e->getMessage(),
                    'context' => $e->getContext(),
                ]);
            }

            throw $e;
        }
    }

    /**
     * Extract records array from API response.
     *
     * @param array $response
     * @return array
     */
    protected function extractRecordsFromResponse(array $response): array
    {
        // Adjust based on actual API response structure
        // Common patterns: $response['data'], $response['list'], $response['records']
        if (isset($response['data']['list'])) {
            return $response['data']['list'];
        }

        if (isset($response['data'])) {
            return is_array($response['data']) ? $response['data'] : [];
        }

        if (isset($response['list'])) {
            return $response['list'];
        }

        if (isset($response['records'])) {
            return $response['records'];
        }

        // If response is directly an array of records
        if (isset($response[0]) && is_array($response[0])) {
            return $response;
        }

        return [];
    }

    /**
     * Upsert attendance records.
     *
     * @param array $records
     * @return int Number of records actually inserted/updated
     */
    protected function upsertRecords(array $records): int
    {
        $upsertedCount = 0;

        foreach ($records as $record) {
            $normalized = $this->normalizeRecord($record);

            if (!$normalized) {
                continue; // Skip invalid records
            }

            try {
                // Use updateOrCreate for upsert behavior
                HccAttendanceTransaction::updateOrCreate(
                    [
                        'person_code' => $normalized['person_code'],
                        'attendance_date' => $normalized['attendance_date'],
                        'attendance_time' => $normalized['attendance_time'],
                        'device_id' => $normalized['device_id'],
                    ],
                    $normalized
                );

                $upsertedCount++;
            } catch (\Exception $e) {
                Log::warning("Failed to upsert attendance record", [
                    'error' => $e->getMessage(),
                    'record' => $normalized,
                ]);
            }
        }

        // Enrich with device information
        if ($upsertedCount > 0) {
            $this->enrichWithDeviceInfo();
        }

        return $upsertedCount;
    }

    /**
     * Normalize a single attendance record.
     *
     * @param array $record
     * @return array|null
     */
    protected function normalizeRecord(array $record): ?array
    {
        // Required fields
        if (!isset($record['personCode']) || !isset($record['clockStamp'])) {
            return null;
        }

        // Parse clockStamp to date and time
        try {
            $clockStamp = Carbon::parse($record['clockStamp'])->setTimezone($this->timezone);
        } catch (\Exception $e) {
            Log::warning("Failed to parse clockStamp", [
                'clockStamp' => $record['clockStamp'] ?? null,
                'error' => $e->getMessage(),
            ]);
            return null;
        }

        return [
            'person_code' => $record['personCode'],
            'full_name' => $record['fullName'] ?? $record['personName'] ?? 'Unknown',
            'department' => $record['department'] ?? $record['groupName'] ?? null,
            'attendance_date' => $clockStamp->format('Y-m-d'),
            'attendance_time' => $clockStamp->format('H:i:s'),
            'device_id' => $record['deviceId'] ?? null,
            'device_name' => null, // Will be enriched later
            'device_serial' => null, // Will be enriched later
            'weekday' => $record['weekday'] ?? $clockStamp->format('l'),
            'source_data' => $record,
        ];
    }

    /**
     * Enrich attendance records with device information.
     */
    protected function enrichWithDeviceInfo(): void
    {
        // Update records that have device_id but missing device info
        DB::statement("
            UPDATE hcc_attendance_transactions a
            JOIN hcc_devices d ON a.device_id = d.device_id
            SET a.device_name = d.name,
                a.device_serial = d.serial_no
            WHERE a.device_id IS NOT NULL
            AND (a.device_name IS NULL OR a.device_serial IS NULL)
        ");
    }
}

