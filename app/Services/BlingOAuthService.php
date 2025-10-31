<?php

namespace App\Services;

use App\Models\BlingToken;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class BlingOAuthService
{
    private const BLING_AUTH_URL = 'https://bling.com.br/Api/v3/oauth/authorize';
    private const BLING_TOKEN_URL = 'https://api.bling.com.br/Api/v3/oauth/token';

    /**
     * Get a valid access token for the given account type
     * Automatically refreshes or generates new token if needed
     */
    public function getValidToken(string $accountType, string $clientId, string $clientSecret): string
    {
        // Try to get existing valid token
        $existingToken = BlingToken::getValidToken($accountType);
        
        if ($existingToken && !$existingToken->isExpiringSoon()) {
            return $existingToken->access_token;
        }

        // If we have a refresh token, try to refresh
        if ($existingToken && $existingToken->refresh_token) {
            try {
                $refreshedToken = $this->refreshToken($existingToken->refresh_token, $clientId, $clientSecret, $accountType);
                if ($refreshedToken) {
                    return $refreshedToken;
                }
            } catch (Exception $e) {
                Log::channel('token')->warning("Failed to refresh token for account {$accountType}: " . $e->getMessage());
            }
        }

        // If refresh failed or no refresh token, need manual authorization
        Log::channel('token')->error("No valid token found for account '{$accountType}'. Manual authorization required.");
        throw new Exception(
            "No valid token found for account '{$accountType}'. " .
            "Please run: php artisan bling:authorize {$accountType}"
        );
    }

    /**
     * Refresh an access token using refresh_token
     */
    public function refreshToken(string $refreshToken, string $clientId, string $clientSecret, string $accountType): ?string
    {
        try {
            Log::channel('token')->info("Attempting to refresh token for account: {$accountType}");
            
            $response = Http::withBasicAuth($clientId, $clientSecret)
                ->asForm()
                ->post(self::BLING_TOKEN_URL, [
                    'grant_type' => 'refresh_token',
                    'refresh_token' => $refreshToken,
                ]);

            if (!$response->successful()) {
                Log::channel('token')->error('Token refresh failed', [
                    'account' => $accountType,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                return null;
            }

            $data = $response->json();
            
            // Save the new token
            $token = BlingToken::updateOrCreateToken(
                $accountType,
                $data['access_token'],
                $data['refresh_token'] ?? null,
                $data['expires_in'],
                explode(' ', $data['scope'] ?? '')
            );

            Log::channel('token')->info('Token refreshed successfully', [
                'account' => $accountType,
                'expires_at' => $token->expires_at->toDateTimeString(),
                'expires_in_hours' => round($token->expires_at->diffInHours(now()), 2),
            ]);
            
            return $data['access_token'];

        } catch (Exception $e) {
            Log::channel('token')->error('Token refresh exception', [
                'account' => $accountType,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Generate authorization URL for manual token generation
     */
    public function getAuthorizationUrl(string $accountType, string $clientId): string
    {
        $state = bin2hex(random_bytes(16));
        
        // Store state temporarily for validation (you might want to use cache/session)
        cache()->put("bling_auth_state_{$accountType}", $state, 600); // 10 minutes

        $params = http_build_query([
            'response_type' => 'code',
            'client_id' => $clientId,
            'state' => $state,
        ]);

        return self::BLING_AUTH_URL . '?' . $params;
    }

    /**
     * Exchange authorization code for access token
     */
    public function exchangeCodeForToken(
        string $code,
        string $state,
        string $accountType,
        string $clientId,
        string $clientSecret,
        bool $skipStateValidation = false
    ): array {
        // Validate state (skip if explicitly requested)
        if (!$skipStateValidation) {
            $storedState = cache()->get("bling_auth_state_{$accountType}");
            if (!$storedState || $storedState !== $state) {
                throw new Exception(
                    'Invalid state parameter. If the cache expired, use --skip-state-validation flag. ' .
                    'Original command: php artisan bling:authorize ' . $accountType . 
                    ' --code=' . $code . ' --state=' . $state . ' --skip-state-validation'
                );
            }
        }

        // Remove state from cache
        cache()->forget("bling_auth_state_{$accountType}");

        try {
            $response = Http::withBasicAuth($clientId, $clientSecret)
                ->asForm()
                ->post(self::BLING_TOKEN_URL, [
                    'grant_type' => 'authorization_code',
                    'code' => $code,
                ]);

            if (!$response->successful()) {
                throw new Exception('Failed to exchange code for token: ' . $response->body());
            }

            $data = $response->json();
            
            // Save the token
            BlingToken::updateOrCreateToken(
                $accountType,
                $data['access_token'],
                $data['refresh_token'] ?? null,
                $data['expires_in'],
                explode(' ', $data['scope'] ?? '')
            );

            Log::channel('token')->info("Token generated successfully for account: {$accountType}", [
                'expires_at' => now()->addSeconds($data['expires_in'])->toDateTimeString(),
                'expires_in_hours' => round($data['expires_in'] / 3600, 2),
            ]);
            return $data;

        } catch (Exception $e) {
            Log::error('Token exchange failed: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Extract account type from client ID (you can customize this logic)
     */
    private function extractAccountTypeFromClientId(string $clientId): string
    {
        // This is a simple approach - you might want to store this mapping differently
        if (str_contains($clientId, 'pro') || str_contains($clientId, 'PRO')) {
            return 'pro';
        }
        
        if (str_contains($clientId, 'atacadista') || str_contains($clientId, 'ATACADISTA')) {
            return 'atacadista';
        }

        // Default fallback - you might want to make this configurable
        return 'unknown';
    }
}
