<?php

namespace App\Services;

use App\Exceptions\HccApiException;
use App\Models\HccDevice;
use Illuminate\Support\Facades\Log;

class HccDevicesSync
{
    protected HccClient $client;

    public function __construct(HccClient $client)
    {
        $this->client = $client;
    }

    /**
     * Sync devices from HCC API to local database.
     *
     * @return int Number of devices synced
     * @throws HccApiException
     */
    public function sync(): int
    {
        $startTime = microtime(true);

        Log::info("HCC Devices sync started");

        try {
            $response = $this->client->deviceBriefSearch();

            // Extract devices from response
            $devices = $this->extractDevicesFromResponse($response);
            $deviceCount = count($devices);

            if ($deviceCount === 0) {
                Log::warning("No devices returned from HCC API");
                return 0;
            }

            $syncedCount = 0;

            foreach ($devices as $device) {
                $normalized = $this->normalizeDevice($device);

                if (!$normalized) {
                    continue; // Skip invalid devices
                }

                try {
                    HccDevice::updateOrCreate(
                        ['device_id' => $normalized['device_id']],
                        $normalized
                    );

                    $syncedCount++;
                } catch (\Exception $e) {
                    Log::warning("Failed to sync device", [
                        'error' => $e->getMessage(),
                        'device' => $normalized,
                    ]);
                }
            }

            $duration = round(microtime(true) - $startTime, 2);

            Log::info("HCC Devices sync completed", [
                'total' => $deviceCount,
                'synced' => $syncedCount,
                'duration_seconds' => $duration,
            ]);

            return $syncedCount;
        } catch (HccApiException $e) {
            if ($e->isAuthError()) {
                Log::error("HCC Authentication failed. Please update HCC_BEARER_TOKEN or HCC_COOKIE in .env", [
                    'error' => $e->getMessage(),
                    'status' => $e->getResponse()?->status(),
                ]);
            } else {
                Log::error("HCC API error during device sync", [
                    'error' => $e->getMessage(),
                    'context' => $e->getContext(),
                ]);
            }

            throw $e;
        }
    }

    /**
     * Extract devices array from API response.
     *
     * @param array $response
     * @return array
     */
    protected function extractDevicesFromResponse(array $response): array
    {
        // Adjust based on actual API response structure
        if (isset($response['data']['list'])) {
            return $response['data']['list'];
        }

        if (isset($response['data']['devices'])) {
            return $response['data']['devices'];
        }

        if (isset($response['data'])) {
            return is_array($response['data']) ? $response['data'] : [];
        }

        if (isset($response['list'])) {
            return $response['list'];
        }

        if (isset($response['devices'])) {
            return $response['devices'];
        }

        return [];
    }

    /**
     * Normalize a single device record.
     *
     * @param array $device
     * @return array|null
     */
    protected function normalizeDevice(array $device): ?array
    {
        // deviceId is required
        $deviceId = $device['deviceId'] ?? $device['id'] ?? $device['device_id'] ?? null;

        if (!$deviceId) {
            return null;
        }

        return [
            'device_id' => (string) $deviceId,
            'name' => $device['name'] ?? $device['deviceName'] ?? null,
            'serial_no' => $device['serialNo'] ?? $device['serial'] ?? $device['serialNumber'] ?? null,
            'category' => $device['category'] ?? $device['deviceCategory'] ?? null,
            'raw' => $device,
        ];
    }
}








