<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\CollmeProBlingService;
use App\Services\CollmeAtacadistaBlingService;
use App\Services\BlingOAuthService;

class BlingAuthorizeCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'bling:authorize 
                            {account : Account to authorize (pro|atacadista)}
                            {--code= : Authorization code from callback}
                            {--state= : State parameter from callback}
                            {--skip-state-validation : Skip state validation (use if cache expired)}';

    /**
     * The console command description.
     */
    protected $description = 'Authorize Bling OAuth2 access for accounts';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $account = $this->argument('account');
        $code = $this->option('code');
        $state = $this->option('state');

        if (!in_array($account, ['pro', 'atacadista'])) {
            $this->error('❌ Invalid account. Use: pro or atacadista');
            return 1;
        }

        try {
            if ($code && $state) {
                // Exchange code for token
                return $this->exchangeCodeForToken($account, $code, $state);
            } else {
                // Generate authorization URL
                return $this->generateAuthorizationUrl($account);
            }

        } catch (\Exception $e) {
            $this->error("❌ Authorization failed: {$e->getMessage()}");
            return 1;
        }
    }

    /**
     * Generate authorization URL for manual authorization
     */
    private function generateAuthorizationUrl(string $account): int
    {
        $this->info("🔐 Generating authorization URL for {$account} account...");

        try {
            $service = $account === 'pro' 
                ? app(CollmeProBlingService::class)
                : app(CollmeAtacadistaBlingService::class);

            $authUrl = $service->getAuthorizationUrl();

            $this->newLine();
            $this->info('📋 Follow these steps:');
            $this->newLine();
            $this->line('1. Open this URL in your browser:');
            $this->line("   {$authUrl}");
            $this->newLine();
            $this->line('2. Log in to your Bling account and authorize the application');
            $this->newLine();
            $this->line('3. After authorization, you\'ll be redirected to a URL like:');
            $this->line('   https://your-app.com/callback?code=XXXXX&state=YYYYY');
            $this->newLine();
            $this->line('4. Extract the code and state parameters from the URL');
            $this->newLine();
            $this->line('5. Run this command with the extracted values:');
            $this->line("   php artisan bling:authorize {$account} --code=XXXXX --state=YYYYY");

            return 0;

        } catch (\Exception $e) {
            $this->error("❌ Failed to generate authorization URL: {$e->getMessage()}");
            return 1;
        }
    }

    /**
     * Exchange authorization code for access token
     */
    private function exchangeCodeForToken(string $account, string $code, string $state): int
    {
        $this->info("🔄 Exchanging authorization code for access token...");

        try {
            $oauthService = app(BlingOAuthService::class);
            $service = $account === 'pro' 
                ? app(CollmeProBlingService::class)
                : app(CollmeAtacadistaBlingService::class);

            $skipStateValidation = $this->option('skip-state-validation');

            $tokenData = $oauthService->exchangeCodeForToken(
                $code,
                $state,
                $account,
                $service->getPublicClientId(),
                $service->getPublicClientSecret(),
                $skipStateValidation
            );

            $this->info('✅ Authorization successful!');
            $this->newLine();
            $this->info('📊 Token Information:');
            $this->line("  Account: {$account}");
            $this->line("  Token Type: {$tokenData['token_type']}");
            $this->line("  Expires In: {$tokenData['expires_in']} seconds");
            $this->line("  Scope: {$tokenData['scope']}");
            
            $this->newLine();
            $this->info('🎉 You can now run: php artisan bling:sync --account=' . $account);

            return 0;

        } catch (\Exception $e) {
            $this->error("❌ Failed to exchange code for token: {$e->getMessage()}");
            return 1;
        }
    }
}
