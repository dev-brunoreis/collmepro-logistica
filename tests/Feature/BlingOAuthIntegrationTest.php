<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\BlingToken;
use App\Services\BlingOAuthService;
use App\Services\CollmeProBlingService;
use App\Services\CollmeAtacadistaBlingService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

class BlingOAuthIntegrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        
        // RefreshDatabase trait handles database cleanup automatically
        Cache::flush();
        
        // Set test environment variables
        config([
            'services.bling.pro.client_id' => 'test_pro_client_id',
            'services.bling.pro.client_secret' => 'test_pro_client_secret',
            'services.bling.atacadista.client_id' => 'test_atacadista_client_id',
            'services.bling.atacadista.client_secret' => 'test_atacadista_client_secret',
        ]);
    }

    public function test_complete_oauth_flow_for_pro_account()
    {
        // Step 1: Generate authorization URL
        $proService = app(CollmeProBlingService::class);
        $authUrl = $proService->getAuthorizationUrl();
        
        $this->assertStringContainsString('https://bling.com.br/Api/v3/oauth/authorize', $authUrl);
        $this->assertStringContainsString('test_pro_client_id', $authUrl);
        
        // Step 2: Mock the token exchange
        Http::fake([
            'api.bling.com.br/Api/v3/oauth/token' => Http::response([
                'access_token' => 'pro_access_token_123',
                'refresh_token' => 'pro_refresh_token_123',
                'expires_in' => 3600,
                'token_type' => 'Bearer',
                'scope' => 'read write'
            ], 200)
        ]);
        
        // Step 3: Extract state from URL and exchange code
        preg_match('/state=([^&]+)/', $authUrl, $matches);
        $state = $matches[1];
        
        $oauthService = app(BlingOAuthService::class);
        $tokenData = $oauthService->exchangeCodeForToken(
            'auth_code_123',
            $state,
            'pro',
            'test_pro_client_id',
            'test_pro_client_secret'
        );
        
        // Step 4: Verify token was saved
        $savedToken = BlingToken::where('account_type', 'pro')->first();
        $this->assertNotNull($savedToken);
        $this->assertEquals('pro_access_token_123', $savedToken->access_token);
        $this->assertEquals('pro_refresh_token_123', $savedToken->refresh_token);
        $this->assertEquals(['read', 'write'], $savedToken->scope);
    }

    public function test_complete_oauth_flow_for_atacadista_account()
    {
        // Step 1: Generate authorization URL
        $atacadistaService = app(CollmeAtacadistaBlingService::class);
        $authUrl = $atacadistaService->getAuthorizationUrl();
        
        $this->assertStringContainsString('https://bling.com.br/Api/v3/oauth/authorize', $authUrl);
        $this->assertStringContainsString('test_atacadista_client_id', $authUrl);
        
        // Step 2: Mock the token exchange
        Http::fake([
            'api.bling.com.br/Api/v3/oauth/token' => Http::response([
                'access_token' => 'atacadista_access_token_123',
                'refresh_token' => 'atacadista_refresh_token_123',
                'expires_in' => 3600,
                'token_type' => 'Bearer',
                'scope' => 'read write'
            ], 200)
        ]);
        
        // Step 3: Extract state from URL and exchange code
        preg_match('/state=([^&]+)/', $authUrl, $matches);
        $state = $matches[1];
        
        $oauthService = app(BlingOAuthService::class);
        $tokenData = $oauthService->exchangeCodeForToken(
            'auth_code_456',
            $state,
            'atacadista',
            'test_atacadista_client_id',
            'test_atacadista_client_secret'
        );
        
        // Step 4: Verify token was saved
        $savedToken = BlingToken::where('account_type', 'atacadista')->first();
        $this->assertNotNull($savedToken);
        $this->assertEquals('atacadista_access_token_123', $savedToken->access_token);
        $this->assertEquals('atacadista_refresh_token_123', $savedToken->refresh_token);
        $this->assertEquals(['read', 'write'], $savedToken->scope);
    }

    public function test_token_refresh_flow()
    {
        // Step 1: Create an existing token that's about to expire
        $existingToken = BlingToken::create([
            'account_type' => 'pro',
            'access_token' => 'old_access_token',
            'refresh_token' => 'valid_refresh_token',
            'expires_at' => now()->addMinutes(2), // Expires soon
            'scope' => ['read', 'write'],
        ]);
        
        // Step 2: Mock successful refresh response
        Http::fake([
            'api.bling.com.br/Api/v3/oauth/token' => Http::response([
                'access_token' => 'new_access_token',
                'refresh_token' => 'new_refresh_token',
                'expires_in' => 3600,
                'token_type' => 'Bearer',
                'scope' => 'read write'
            ], 200)
        ]);
        
        // Step 3: Get valid token (should trigger refresh)
        $oauthService = app(BlingOAuthService::class);
        $validToken = $oauthService->getValidToken('pro', 'test_pro_client_id', 'test_pro_client_secret');
        
        // Step 4: Verify new token was returned and saved
        $this->assertEquals('new_access_token', $validToken);
        
        $updatedToken = BlingToken::where('account_type', 'pro')->first();
        $this->assertEquals('new_access_token', $updatedToken->access_token);
        $this->assertEquals('new_refresh_token', $updatedToken->refresh_token);
    }

    public function test_multiple_accounts_can_exist_simultaneously()
    {
        // Create tokens for both accounts
        BlingToken::create([
            'account_type' => 'pro',
            'access_token' => 'pro_token',
            'expires_at' => now()->addHour(),
        ]);
        
        BlingToken::create([
            'account_type' => 'atacadista',
            'access_token' => 'atacadista_token',
            'expires_at' => now()->addHour(),
        ]);
        
        // Verify both tokens exist and are independent
        $proToken = BlingToken::getValidToken('pro');
        $atacadistaToken = BlingToken::getValidToken('atacadista');
        
        $this->assertNotNull($proToken);
        $this->assertNotNull($atacadistaToken);
        $this->assertEquals('pro_token', $proToken->access_token);
        $this->assertEquals('atacadista_token', $atacadistaToken->access_token);
        $this->assertNotEquals($proToken->id, $atacadistaToken->id);
    }

    public function test_service_container_registration()
    {
        // Test that services are properly registered in the container
        $this->assertInstanceOf(BlingOAuthService::class, app(BlingOAuthService::class));
        $this->assertInstanceOf(CollmeProBlingService::class, app(CollmeProBlingService::class));
        $this->assertInstanceOf(CollmeAtacadistaBlingService::class, app(CollmeAtacadistaBlingService::class));
    }

    protected function tearDown(): void
    {
        Cache::flush();
        
        parent::tearDown();
    }
}
