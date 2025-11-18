<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;

class TransactionSyncController extends Controller
{
    /**
     * Trigger the transaction:build command via API.
     */
    public function sync(Request $request)
    {
        try {
            $exitCode = Artisan::call('transactions:build', [
                '--no-interaction' => true,
            ]);

            $output = trim(Artisan::output());

            $success = $exitCode === 0 && Str::contains($output, ['completed', 'success', 'Saved']);

            return response()->json([
                'ok' => $success,
                'exit_code' => $exitCode,
                'message' => $success
                    ? 'transactions:build finished successfully.'
                    : 'transactions:build completed with warnings. Check output.',
                'output' => $output,
            ], $success ? 200 : 500);
        } catch (\Throwable $e) {
            return response()->json([
                'ok' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}

