<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class RefreshHikTokenJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 3;

    /**
     * The number of seconds the job can run before timing out.
     *
     * @var int
     */
    public $timeout = 60;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        try {
            Log::info('RefreshHikTokenJob: Starting token refresh');

            // Get credentials from config or env
            $appKey = env('HIK_APP_KEY', 'Bq5yTaAKE9nRAEr9Z4qlsGGmxkCMM2yM');
            $secretKey = env('HIK_SECRET_KEY', 'D5mFPHzutkHLGKNRwVpwQDZAeuinT34n');
            $baseUrl = env('HIK_BASE_URL', 'https://isgp.hikcentralconnect.com/api/hccgw');

            // Call Hikvision token API
            $url = rtrim($baseUrl, '/') . '/platform/v1/token/get';

            $response = Http::timeout(20)
                ->withHeaders(['Content-Type' => 'application/json'])
                ->post($url, [
                    'appKey' => $appKey,
                    'secretKey' => $secretKey,
                ]);

            if (!$response->successful()) {
                Log::error('RefreshHikTokenJob: Token fetch failed', [
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);
                
                // Retry the job
                throw new \Exception('Failed to fetch token from Hikvision API');
            }

            $data = $response->json();

            // Check for error code
            if (isset($data['errorCode']) && $data['errorCode'] !== '0') {
                Log::error('RefreshHikTokenJob: API returned error', ['response' => $data]);
                throw new \Exception('Hikvision API returned error code: ' . $data['errorCode']);
            }

            // Extract token data
            $tokenData = $data['data'] ?? null;

            if (!$tokenData || !isset($tokenData['accessToken'])) {
                Log::error('RefreshHikTokenJob: Invalid response format', ['response' => $data]);
                throw new \Exception('Invalid response format from Hikvision API');
            }

            $accessToken = $tokenData['accessToken'];
            $expireTime = $tokenData['expireTime'] ?? null;
            $userId = $tokenData['userId'] ?? null;
            $areaDomain = $tokenData['areaDomain'] ?? null;

            // Update .env file
            $this->updateEnvFile('HIK_TOKEN', $accessToken);

            // Optionally store other values
            if ($areaDomain) {
                $this->updateEnvFile('HIK_AREA_DOMAIN', $areaDomain);
            }
            if ($userId) {
                $this->updateEnvFile('HIK_USER_ID', $userId);
            }

            Log::info('RefreshHikTokenJob: Token refreshed successfully', [
                'expireTime' => $expireTime,
                'expireDate' => $expireTime ? date('Y-m-d H:i:s', $expireTime) : null,
                'userId' => $userId
            ]);

            // Clear config cache
            if (function_exists('config_clear')) {
                config_clear();
            }

        } catch (\Exception $e) {
            Log::error('RefreshHikTokenJob: Exception occurred', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            // Re-throw to trigger retry mechanism
            throw $e;
        }
    }

    /**
     * Update or add a key-value pair in the .env file
     */
    private function updateEnvFile($key, $value)
    {
        $envPath = base_path('.env');

        if (!file_exists($envPath)) {
            Log::warning('RefreshHikTokenJob: .env file not found at ' . $envPath);
            return false;
        }

        $envContent = file_get_contents($envPath);

        // Escape special characters in value
        $value = str_replace('"', '\"', $value);

        // Check if key exists
        $pattern = "/^{$key}=.*/m";

        if (preg_match($pattern, $envContent)) {
            // Key exists, update it
            $newContent = preg_replace($pattern, "{$key}=\"{$value}\"", $envContent);
        } else {
            // Key doesn't exist, append it
            $newContent = $envContent . "\n{$key}=\"{$value}\"";
        }

        file_put_contents($envPath, $newContent);

        Log::info("RefreshHikTokenJob: Updated .env: {$key}");

        return true;
    }

    /**
     * Handle a job failure.
     *
     * @param  \Throwable  $exception
     * @return void
     */
    public function failed(\Throwable $exception)
    {
        Log::error('RefreshHikTokenJob: Job failed after all retries', [
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString()
        ]);

        // Optionally: Send notification to admin
        // Notification::route('mail', config('mail.admin_email'))
        //     ->notify(new HikTokenRefreshFailed($exception));
    }
}

