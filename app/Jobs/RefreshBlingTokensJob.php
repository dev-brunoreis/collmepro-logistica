<?php

namespace App\Jobs;

use App\Models\BlingToken;
use App\Services\CollmeProBlingService;
use App\Services\CollmeAtacadistaBlingService;
use App\Services\BlingOAuthService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Exception;

class RefreshBlingTokensJob implements ShouldQueue
{
    use Queueable;

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::channel('token')->info('=== Starting automatic token refresh check ===');

        // Check and refresh Pro account token
        $this->refreshAccountToken('pro', CollmeProBlingService::class);

        // Check and refresh Atacadista account token
        $this->refreshAccountToken('atacadista', CollmeAtacadistaBlingService::class);

        Log::channel('token')->info('=== Token refresh check completed ===');
    }

    /**
     * Refresh token for a specific account
     */
    private function refreshAccountToken(string $accountType, string $serviceClass): void
    {
        try {
            Log::channel('token')->info("Checking {$accountType} account token...");

            // Get current token from database
            $token = BlingToken::where('account_type', $accountType)->first();

            if (!$token) {
                Log::channel('token')->warning("No token found for {$accountType} account. Manual authorization required.");
                return;
            }

            // Check if token is valid and not expiring soon
            $hoursUntilExpiry = $token->expires_at->diffInHours(now(), false);
            
            Log::channel('token')->info("{$accountType} token status", [
                'expires_at' => $token->expires_at->toDateTimeString(),
                'hours_until_expiry' => round($hoursUntilExpiry, 2),
                'is_expired' => $token->isExpired(),
                'is_expiring_soon' => $token->isExpiringSoon(),
            ]);

            // If token expires in less than 4 hours or is already expired, refresh it
            if ($hoursUntilExpiry < 4 || $token->isExpired()) {
                Log::channel('token')->info("Token for {$accountType} needs refresh (expires in " . round($hoursUntilExpiry, 2) . " hours)");

                if (!$token->refresh_token) {
                    Log::channel('token')->error("No refresh token available for {$accountType}. Manual authorization required.");
                    return;
                }

                // Get service instance to access credentials
                $service = app($serviceClass);
                $oauthService = app(BlingOAuthService::class);

                // Attempt to refresh the token
                $newAccessToken = $oauthService->refreshToken(
                    $token->refresh_token,
                    $service->getPublicClientId(),
                    $service->getPublicClientSecret(),
                    $accountType
                );

                if ($newAccessToken) {
                    Log::channel('token')->info("Token successfully refreshed for {$accountType} account");
                } else {
                    Log::channel('token')->error("Failed to refresh token for {$accountType} account. Manual authorization may be required.");
                }
            } else {
                Log::channel('token')->info("Token for {$accountType} is still valid (expires in " . round($hoursUntilExpiry, 2) . " hours). No refresh needed.");
            }

        } catch (Exception $e) {
            Log::channel('token')->error("Error checking/refreshing {$accountType} token: " . $e->getMessage(), [
                'exception' => get_class($e),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }
}

