<?php

namespace App\Jobs;

use App\Services\CollmeProBlingLabelService;
use App\Services\CollmeAtacadistaBlingLabelService;
use App\Services\BlingOAuthService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Exception;

class LabelSyncJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $timeout = 300; // 5 minutes

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::info('Starting label synchronization job');

        $oauthService = app(BlingOAuthService::class);
        $results = [];

        // Sync Pro account labels
        try {
            Log::info('Syncing labels for Collme Pro account');
            $proService = new CollmeProBlingLabelService($oauthService);
            $results['pro'] = $proService->syncLabels();
            Log::info('Collme Pro labels sync completed', $results['pro']);
        } catch (Exception $e) {
            Log::error('Failed to sync labels for Collme Pro account: ' . $e->getMessage());
            $results['pro'] = ['error' => $e->getMessage()];
        }

        // Sync Atacadista account labels
        try {
            Log::info('Syncing labels for Collme Atacadista account');
            $atacadistaService = new CollmeAtacadistaBlingLabelService($oauthService);
            $results['atacadista'] = $atacadistaService->syncLabels();
            Log::info('Collme Atacadista labels sync completed', $results['atacadista']);
        } catch (Exception $e) {
            Log::error('Failed to sync labels for Collme Atacadista account: ' . $e->getMessage());
            $results['atacadista'] = ['error' => $e->getMessage()];
        }

        Log::info('Label synchronization job completed', $results);
    }

    /**
     * Handle a job failure.
     */
    public function failed(?Exception $exception): void
    {
        Log::error('Label synchronization job failed', [
            'error' => $exception?->getMessage(),
            'trace' => $exception?->getTraceAsString(),
        ]);
    }
}

