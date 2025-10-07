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
                $refreshedToken = $this->refreshToken($existingToken->refresh_token, $clientId, $clientSecret);
                if ($refreshedToken) {
                    return $refreshedToken;
                }
            } catch (Exception $e) {
                Log::warning("Failed to refresh token for account {$accountType}: " . $e->getMessage());
            }
        }

        // If refresh failed or no refresh token, need manual authorization
        throw new Exception(
            "No valid token found for account '{$accountType}'. " .
            "Please run: php artisan bling:authorize --account={$accountType}"
        );
    }

    /**
     * Refresh an access token using refresh_token
     */
    public function refreshToken(string $refreshToken, string $clientId, string $clientSecret): ?string
    {
        try {
            $response = Http::withBasicAuth($clientId, $clientSecret)
                ->asForm()
                ->post(self::BLING_TOKEN_URL, [
                    'grant_type' => 'refresh_token',
                    'refresh_token' => $refreshToken,
                ]);

            if (!$response->successful()) {
                Log::error('Token refresh failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                return null;
            }

            $data = $response->json();
            
            // Save the new token
            BlingToken::updateOrCreateToken(
                $this->extractAccountTypeFromClientId($clientId),
                $data['access_token'],
                $data['refresh_token'] ?? null,
                $data['expires_in'],
                explode(' ', $data['scope'] ?? '')
            );

            Log::info('Token refreshed successfully');
            return $data['access_token'];

        } catch (Exception $e) {
            Log::error('Token refresh exception: ' . $e->getMessage());
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
        string $clientSecret
    ): array {
        // Validate state
        $storedState = cache()->get("bling_auth_state_{$accountType}");
        if (!$storedState || $storedState !== $state) {
            throw new Exception('Invalid state parameter');
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

            Log::info("Token generated successfully for account: {$accountType}");
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
