<?php

namespace App\Console\Commands;

use App\Jobs\RefreshHikTokenJob;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TestHikToken extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'hik:test-token {action=fetch : Action to perform (fetch|check|job)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test Hikvision token functionality (fetch, check current token, or run job)';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $action = $this->argument('action');

        switch ($action) {
            case 'fetch':
                return $this->fetchToken();
            case 'check':
                return $this->checkCurrentToken();
            case 'job':
                return $this->runJob();
            default:
                $this->error("Invalid action: {$action}");
                $this->info("Available actions: fetch, check, job");
                return self::FAILURE;
        }
    }

    /**
     * Fetch a new token from Hikvision API
     */
    private function fetchToken()
    {
        $this->info('🔄 Fetching new token from Hikvision...');
        $this->newLine();

        $appKey = env('HIK_APP_KEY');
        $secretKey = env('HIK_SECRET_KEY');
        $baseUrl = env('HIK_BASE_URL', 'https://isgp.hikcentralconnect.com/api/hccgw');

        if (!$appKey || !$secretKey) {
            $this->error('❌ HIK_APP_KEY or HIK_SECRET_KEY not set in .env');
            return self::FAILURE;
        }

        $this->info("Base URL: {$baseUrl}");
        $this->info("App Key: " . substr($appKey, 0, 10) . '...');
        $this->newLine();

        try {
            $url = rtrim($baseUrl, '/') . '/platform/v1/token/get';

            $response = Http::timeout(20)
                ->withHeaders(['Content-Type' => 'application/json'])
                ->post($url, [
                    'appKey' => $appKey,
                    'secretKey' => $secretKey,
                ]);

            if (!$response->successful()) {
                $this->error('❌ Failed to fetch token');
                $this->error('Status: ' . $response->status());
                $this->error('Response: ' . $response->body());
                return self::FAILURE;
            }

            $data = $response->json();

            if (isset($data['errorCode']) && $data['errorCode'] !== '0') {
                $this->error('❌ Hikvision API returned error');
                $this->error('Error Code: ' . $data['errorCode']);
                $this->line(json_encode($data, JSON_PRETTY_PRINT));
                return self::FAILURE;
            }

            $tokenData = $data['data'] ?? null;

            if (!$tokenData || !isset($tokenData['accessToken'])) {
                $this->error('❌ Invalid response format');
                $this->line(json_encode($data, JSON_PRETTY_PRINT));
                return self::FAILURE;
            }

            $this->info('✅ Token fetched successfully!');
            $this->newLine();

            $this->table(
                ['Field', 'Value'],
                [
                    ['Access Token', substr($tokenData['accessToken'], 0, 30) . '...'],
                    ['User ID', $tokenData['userId'] ?? 'N/A'],
                    ['Area Domain', $tokenData['areaDomain'] ?? 'N/A'],
                    ['Expire Time', $tokenData['expireTime'] ?? 'N/A'],
                    ['Expire Date', isset($tokenData['expireTime']) ? date('Y-m-d H:i:s', $tokenData['expireTime']) : 'N/A'],
                    ['Time Until Expiry', isset($tokenData['expireTime']) ? $this->getTimeUntilExpiry($tokenData['expireTime']) : 'N/A'],
                ]
            );

            $saveToken = true;
            if ($this->input->isInteractive()) {
                $saveToken = $this->confirm('Do you want to save this token to .env?', true);
            }

            if ($saveToken) {
                $this->updateEnvFile('HIK_TOKEN', $tokenData['accessToken']);
                if (!empty($tokenData['areaDomain'])) {
                    $this->updateEnvFile('HIK_AREA_DOMAIN', $tokenData['areaDomain']);
                }
                if (!empty($tokenData['userId'])) {
                    $this->updateEnvFile('HIK_USER_ID', $tokenData['userId']);
                }
                $this->info('✅ Token saved to .env successfully!');
            }

            return self::SUCCESS;

        } catch (\Exception $e) {
            $this->error('❌ Exception occurred: ' . $e->getMessage());
            $this->error($e->getTraceAsString());
            return self::FAILURE;
        }
    }

    /**
     * Check current token in .env
     */
    private function checkCurrentToken()
    {
        $this->info('🔍 Checking current token...');
        $this->newLine();

        $token = env('HIK_TOKEN');
        $areaDomain = env('HIK_AREA_DOMAIN');
        $userId = env('HIK_USER_ID');

        if (!$token) {
            $this->warn('⚠️  No token found in .env (HIK_TOKEN is empty)');
            return self::FAILURE;
        }

        $this->table(
            ['Field', 'Value'],
            [
                ['Token (first 30 chars)', substr($token, 0, 30) . '...'],
                ['Token (last 10 chars)', '...' . substr($token, -10)],
                ['User ID', $userId ?: 'Not set'],
                ['Area Domain', $areaDomain ?: 'Not set'],
            ]
        );

        $shouldTest = $this->input->isInteractive()
            ? $this->confirm('Do you want to test this token with a sample API call?', true)
            : false;

        if ($shouldTest) {
            $this->testTokenWithApi($token);
        }

        return self::SUCCESS;
    }

    /**
     * Run the RefreshHikTokenJob
     */
    private function runJob()
    {
        $this->info('🚀 Running RefreshHikTokenJob...');
        $this->newLine();

        try {
            $job = new RefreshHikTokenJob();
            $job->handle();

            $this->info('✅ Job completed successfully!');
            $this->newLine();
            
            // Show updated token
            $this->checkCurrentToken();

            return self::SUCCESS;

        } catch (\Exception $e) {
            $this->error('❌ Job failed: ' . $e->getMessage());
            $this->error($e->getTraceAsString());
            return self::FAILURE;
        }
    }

    /**
     * Test token with a sample API call
     */
    private function testTokenWithApi($token)
    {
        $this->info('Testing token with Hikvision API...');

        $baseUrl = env('HIK_BASE_URL', 'https://isgp.hikcentralconnect.com/api/hccgw');
        $url = rtrim($baseUrl, '/') . '/person/v1/persons/list';

        try {
            $response = Http::timeout(20)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'Token' => $token,
                ])
                ->post($url, [
                    'pageIndex' => 1,
                    'pageSize' => 1,
                    'filter' => (object)[],
                ]);

            if ($response->successful()) {
                $this->info('✅ Token is valid! API call successful.');
                $data = $response->json();
                $totalCount = $data['data']['totalCount'] ?? 0;
                $this->info("Total persons in system: {$totalCount}");
            } else {
                $this->error('❌ Token might be invalid or expired');
                $this->error('Status: ' . $response->status());
                $this->error('Response: ' . $response->body());
            }
        } catch (\Exception $e) {
            $this->error('❌ Failed to test token: ' . $e->getMessage());
        }
    }

    /**
     * Get human-readable time until expiry
     */
    private function getTimeUntilExpiry($expireTime)
    {
        $now = time();
        $diff = $expireTime - $now;

        if ($diff < 0) {
            return '❌ EXPIRED';
        }

        $days = floor($diff / 86400);
        $hours = floor(($diff % 86400) / 3600);
        $minutes = floor(($diff % 3600) / 60);

        $parts = [];
        if ($days > 0) $parts[] = "{$days} days";
        if ($hours > 0) $parts[] = "{$hours} hours";
        if ($minutes > 0) $parts[] = "{$minutes} minutes";

        return implode(', ', $parts) ?: 'Less than a minute';
    }

    /**
     * Update .env file
     */
    private function updateEnvFile($key, $value)
    {
        $envPath = base_path('.env');

        if (!file_exists($envPath)) {
            $this->error(".env file not found at {$envPath}");
            return false;
        }

        $envContent = file_get_contents($envPath);
        $value = str_replace('"', '\"', $value);
        $pattern = "/^{$key}=.*/m";

        if (preg_match($pattern, $envContent)) {
            $newContent = preg_replace($pattern, "{$key}=\"{$value}\"", $envContent);
        } else {
            $newContent = $envContent . "\n{$key}=\"{$value}\"";
        }

        file_put_contents($envPath, $newContent);

        return true;
    }
}

