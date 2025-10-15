<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class SendHourlyStatsJob implements ShouldQueue
{
    use Queueable;

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            $statsUrl = config('services.stats.url');
            $apiKey = config('services.stats.key');

            if (!$statsUrl || !$apiKey) {
                Log::channel('ping')->error('Missing required configuration', [
                    'has_url' => !empty($statsUrl),
                    'has_key' => !empty($apiKey),
                ]);
                return;
            }

            Log::channel('ping')->info('Sending stats request', [
                'url' => $statsUrl,
                'timestamp' => now()->toDateTimeString(),
            ]);

            $response = Http::withHeaders([
                'X-API-Key' => $apiKey,
            ])->post($statsUrl);

            if ($response->successful()) {
                Log::channel('ping')->info('Stats request successful', [
                    'status' => $response->status(),
                    'timestamp' => now()->toDateTimeString(),
                ]);
            } else {
                Log::channel('ping')->error('Stats request failed', [
                    'url' => $statsUrl,
                    'status' => $response->status(),
                    'body' => $response->body(),
                    'timestamp' => now()->toDateTimeString(),
                ]);
            }

        } catch (Exception $e) {
            Log::channel('ping')->error('Exception occurred', [
                'message' => $e->getMessage(),
                'exception' => get_class($e),
                'timestamp' => now()->toDateTimeString(),
            ]);
        }
    }
}

