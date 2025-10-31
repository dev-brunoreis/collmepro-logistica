<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\CollmeProBlingService;
use App\Services\CollmeAtacadistaBlingService;
use App\Services\BlingOAuthService;
use App\Models\BlingToken;

class BlingRefreshTokenCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'bling:refresh-token 
                            {account : Account to refresh token (pro|atacadista)}';

    /**
     * The console command description.
     */
    protected $description = 'Refresh Bling OAuth2 token using existing refresh_token';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $account = $this->argument('account');

        if (!in_array($account, ['pro', 'atacadista'])) {
            $this->error('❌ Invalid account. Use: pro or atacadista');
            return 1;
        }

        $this->info("🔄 Refreshing token for {$account} account...");
        $this->newLine();

        try {
            // Check if token exists
            $token = BlingToken::where('account_type', $account)->first();

            if (!$token) {
                $this->error("❌ No token found for {$account} account.");
                $this->line("   Run: php artisan bling:authorize {$account}");
                return 1;
            }

            if (!$token->refresh_token) {
                $this->error("❌ No refresh token found for {$account} account.");
                $this->line("   You need to re-authorize:");
                $this->line("   php artisan bling:authorize {$account}");
                return 1;
            }

            // Get service instance
            $service = $account === 'pro' 
                ? app(CollmeProBlingService::class)
                : app(CollmeAtacadistaBlingService::class);

            $oauthService = app(BlingOAuthService::class);

            // Refresh the token
            $this->line("   Current token expires at: {$token->expires_at->toDateTimeString()}");
            $this->line("   Using refresh token to get new access token...");
            $this->newLine();

            $newAccessToken = $oauthService->refreshToken(
                $token->refresh_token,
                $service->getPublicClientId(),
                $service->getPublicClientSecret(),
                $account
            );

            if ($newAccessToken) {
                // Get updated token from database
                $updatedToken = BlingToken::where('account_type', $account)->first();
                
                $this->info('✅ Token refreshed successfully!');
                $this->newLine();
                $this->info('📊 Token Information:');
                $this->line("  Account: {$account}");
                $this->line("  New Expires At: {$updatedToken->expires_at->toDateTimeString()}");
                $this->line("  Has Refresh Token: " . ($updatedToken->refresh_token ? 'Yes' : 'No'));
                
                $this->newLine();
                $this->info('🎉 Token is ready to use!');
                return 0;
            } else {
                $this->error("❌ Failed to refresh token. Check logs for details:");
                $this->line("   tail -f storage/logs/token.log");
                return 1;
            }

        } catch (\Exception $e) {
            $this->error("❌ Failed to refresh token: {$e->getMessage()}");
            $this->newLine();
            $this->line("💡 If refresh token is invalid, you may need to re-authorize:");
            $this->line("   php artisan bling:authorize {$account}");
            return 1;
        }
    }
}

