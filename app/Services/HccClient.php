<?php

namespace App\Services;

use App\Exceptions\HccApiException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class HccClient
{
    protected string $baseUrl;
    protected int $timeout;
    protected int $retryTimes;
    protected int $retrySleep;
    protected ?string $bearerToken;
    protected ?string $cookie;

    public function __construct()
    {
        $this->baseUrl = config('hcc.base_url');
        $this->timeout = config('hcc.timeout');
        $this->retryTimes = config('hcc.retry_times');
        $this->retrySleep = config('hcc.retry_sleep');
        $this->bearerToken = config('hcc.bearer_token');
        $this->cookie = config('hcc.cookie');
    }

    /**
     * Get configured HTTP client with authentication.
     */
    protected function client(): PendingRequest
    {
        $client = Http::baseUrl($this->baseUrl)
            ->timeout($this->timeout)
            ->retry($this->retryTimes, $this->retrySleep, function ($exception, $request) {
                // Retry on 429 (rate limit) and 5xx errors
                if ($exception instanceof \Illuminate\Http\Client\RequestException) {
                    $status = $exception->response->status();
                    return $status === 429 || $status >= 500;
                }
                return false;
            })
            ->withHeaders([
                'Content-Type' => 'application/json',
                'Accept' => 'application/json, text/plain, */*',
                'Origin' => 'https://www.hik-connect.com',
                'Referer' => 'https://www.hik-connect.com/',
                'clientsource' => '0',
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36',
            ]);

        // Auth strategy: Bearer token takes precedence
        if ($this->bearerToken) {
            $client->withToken($this->bearerToken);
        } elseif ($this->cookie) {
            $client->withHeaders([
                'Cookie' => $this->cookie,
            ]);
        }

        return $client;
    }

    /**
     * Fetch attendance list with pagination.
     *
     * @param int $page Page number (1-based)
     * @param int $pageSize Number of records per page
     * @param string $fromIso Start datetime in ISO8601 format with timezone
     * @param string $toIso End datetime in ISO8601 format with timezone
     * @param array $optionalFilters Additional filters to merge
     * @return array Response data
     * @throws HccApiException
     */
    public function attendanceList(
        int $page,
        int $pageSize,
        string $fromIso,
        string $toIso,
        array $optionalFilters = []
    ): array {
        $endpoint = config('hcc.endpoints.attendance_list');

        $body = [
            'page' => $page,
            'pageSize' => $pageSize,
            'language' => 'en',
            'reportTypeId' => 1,
            'columnIdList' => [],
            'filterList' => array_merge([
                ['columnName' => 'fullName', 'operation' => 'LIKE', 'value' => ''],
                ['columnName' => 'personCode', 'operation' => 'LIKE', 'value' => ''],
                ['columnName' => 'groupId', 'operation' => 'IN', 'value' => ''],
                ['columnName' => 'clockStamp', 'operation' => 'BETWEEN', 'value' => "{$fromIso},{$toIso}"],
                ['columnName' => 'deviceId', 'operation' => 'IN', 'value' => ''],
            ], $optionalFilters),
        ];

        $context = [
            'endpoint' => $endpoint,
            'page' => $page,
            'pageSize' => $pageSize,
            'dateRange' => "{$fromIso} to {$toIso}",
        ];

        try {
            $response = $this->client()->post($endpoint, $body);

            if (!$response->successful()) {
                throw new HccApiException(
                    "HCC API request failed: {$endpoint}",
                    $response,
                    $context,
                    $response->status()
                );
            }

            return $response->json();
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            throw new HccApiException(
                "HCC API connection failed: {$endpoint}",
                null,
                $context,
                0,
                $e
            );
        }
    }

    /**
     * Fetch devices list.
     *
     * @return array Response data
     * @throws HccApiException
     */
    public function deviceBriefSearch(): array
    {
        $endpoint = config('hcc.endpoints.devices_search');

        $body = [
            'devicesRequest' => [
                'pageIndex' => -1,
                'pageSize' => -1,
                'field' => '',
                'deviceCategories' => [2002, 2008],
                'searchCriteria' => [
                    'tagInfo' => new \stdClass(), // Empty object
                    'match' => '',
                    'filterSingleChannel' => '',
                    'operation' => 2,
                    'physicalMatchType' => 4,
                ],
            ],
        ];

        $context = [
            'endpoint' => $endpoint,
        ];

        try {
            $response = $this->client()->post($endpoint, $body);

            if (!$response->successful()) {
                throw new HccApiException(
                    "HCC API request failed: {$endpoint}",
                    $response,
                    $context,
                    $response->status()
                );
            }

            return $response->json();
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            throw new HccApiException(
                "HCC API connection failed: {$endpoint}",
                null,
                $context,
                0,
                $e
            );
        }
    }
}


