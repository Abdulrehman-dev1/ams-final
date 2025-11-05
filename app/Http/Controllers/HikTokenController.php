<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class HikTokenController extends Controller
{
    /**
     * POST /api/hik/token/refresh
     * 
     * Fetches a new access token from Hikvision Central Connect API
     * and updates the .env file with the new token.
     */
    public function refresh(Request $request)
    {
        try {
            // Get credentials from config or env
            $appKey = env('HIK_APP_KEY', 'Bq5yTaAKE9nRAEr9Z4qlsGGmxkCMM2yM');
            $secretKey = env('HIK_SECRET_KEY', 'D5mFPHzutkHLGKNRwVpwQDZAeuinT34n');
            $baseUrl = env('HIK_BASE_URL', 'https://isgp.hikcentralconnect.com/api/hccgw');

            // Call Hikvision token API
            $url = rtrim($baseUrl, '/') . '/platform/v1/token/get';
            
            Log::info('Fetching HIK token', ['url' => $url]);

            $response = Http::timeout(20)
                ->withHeaders(['Content-Type' => 'application/json'])
                ->post($url, [
                    'appKey' => $appKey,
                    'secretKey' => $secretKey,
                ]);

            if (!$response->successful()) {
                Log::error('HIK token fetch failed', [
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Failed to fetch token from Hikvision API',
                    'error' => $response->body()
                ], $response->status());
            }

            $data = $response->json();
            
            // Check for error code
            if (isset($data['errorCode']) && $data['errorCode'] !== '0') {
                Log::error('HIK token API returned error', ['response' => $data]);
                
                return response()->json([
                    'success' => false,
                    'message' => 'Hikvision API returned error',
                    'error' => $data
                ], 400);
            }

            // Extract token data
            $tokenData = $data['data'] ?? null;
            
            if (!$tokenData || !isset($tokenData['accessToken'])) {
                Log::error('Invalid HIK token response format', ['response' => $data]);
                
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid response format from Hikvision API',
                    'data' => $data
                ], 500);
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

            Log::info('HIK token refreshed successfully', [
                'expireTime' => $expireTime,
                'expireDate' => $expireTime ? date('Y-m-d H:i:s', $expireTime) : null
            ]);

            // Clear config cache to load new values
            if (function_exists('config_clear')) {
                config_clear();
            }

            return response()->json([
                'success' => true,
                'message' => 'Token refreshed successfully',
                'data' => [
                    'accessToken' => $accessToken,
                    'expireTime' => $expireTime,
                    'expireDate' => $expireTime ? date('Y-m-d H:i:s', $expireTime) : null,
                    'userId' => $userId,
                    'areaDomain' => $areaDomain,
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('HIK token refresh exception', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Exception occurred while refreshing token',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update or add a key-value pair in the .env file
     */
    private function updateEnvFile($key, $value)
    {
        $envPath = base_path('.env');

        if (!file_exists($envPath)) {
            Log::warning('.env file not found at ' . $envPath);
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
        
        Log::info("Updated .env: {$key}");
        
        return true;
    }
}

