<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\BlingToken;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;

class BlingCommandsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        
        // RefreshDatabase trait handles database cleanup automatically
        
        // Set test environment variables
        config([
            'services.bling.pro.client_id' => 'test_pro_client_id',
            'services.bling.pro.client_secret' => 'test_pro_client_secret',
            'services.bling.atacadista.client_id' => 'test_atacadista_client_id',
            'services.bling.atacadista.client_secret' => 'test_atacadista_client_secret',
        ]);
    }

    public function test_bling_authorize_command_generates_url()
    {
        $this->artisan('bling:authorize pro')
            ->expectsOutput('🔐 Generating authorization URL for pro account...')
            ->expectsOutput('📋 Follow these steps:')
            ->assertExitCode(0);
    }

    public function test_bling_authorize_command_with_code_and_state()
    {
        // Mock successful token exchange
        Http::fake([
            'api.bling.com.br/Api/v3/oauth/token' => Http::response([
                'access_token' => 'test_access_token',
                'refresh_token' => 'test_refresh_token',
                'expires_in' => 3600,
                'token_type' => 'Bearer',
                'scope' => 'read write'
            ], 200)
        ]);
        
        // First generate URL to get state
        $output = $this->artisan('bling:authorize pro');
        $output->assertExitCode(0);
        
        // Get the state from cache (simulating the flow)
        $state = 'test_state_123';
        cache()->put('bling_auth_state_pro', $state, 600);
        
        // Now test with code and state
        $this->artisan('bling:authorize pro --code=auth_code_123 --state=' . $state)
            ->expectsOutput('🔄 Exchanging authorization code for access token...')
            ->expectsOutput('✅ Authorization successful!')
            ->expectsOutput('📊 Token Information:')
            ->expectsOutput('🎉 You can now run: php artisan bling:sync --account=pro')
            ->assertExitCode(0);
    }

    public function test_bling_authorize_command_invalid_account()
    {
        $this->artisan('bling:authorize invalid')
            ->expectsOutput('❌ Invalid account. Use: pro or atacadista')
            ->assertExitCode(1);
    }

    public function test_bling_sync_command_test_connection()
    {
        $this->artisan('bling:sync --test')
            ->expectsOutput('🔌 Testing Bling API connections...')
            ->assertExitCode(0);
    }

    public function test_bling_sync_command_invalid_account()
    {
        $this->artisan('bling:sync --account=invalid')
            ->expectsOutput('❌ Invalid account. Use: pro, atacadista, or all')
            ->assertExitCode(1);
    }

    public function test_bling_sync_command_without_authorization_throws_exception()
    {
        // Mock API responses for when no token exists
        Http::fake([
            'api.bling.com.br/Api/v3/*' => Http::response([
                'error' => 'No valid token found'
            ], 401)
        ]);
        
        $this->artisan('bling:sync --account=pro')
            ->expectsOutput('🚀 Bling Synchronization System')
            ->expectsOutput('🔄 Starting synchronization for pro account...')
            ->assertExitCode(1);
    }

    public function test_bling_sync_command_with_valid_token()
    {
        // Create a valid token
        BlingToken::create([
            'account_type' => 'pro',
            'access_token' => 'valid_token',
            'expires_at' => now()->addHour(),
        ]);
        
        // Mock successful API response - CORRECTED ENDPOINT
        Http::fake([
            'api.bling.com.br/Api/v3/pedidos/vendas' => Http::response([
                'data' => [
                    ['id' => '1', 'numero' => 'PED001'],
                    ['id' => '2', 'numero' => 'PED002'],
                ]
            ], 200)
        ]);
        
        $this->artisan('bling:sync --account=pro')
            ->expectsOutput('🚀 Bling Synchronization System')
            ->expectsOutput('🔄 Starting synchronization for pro account...')
            ->expectsOutput('✅ pro synchronization completed')
            ->expectsOutput('📊 Synchronization Summary:')
            ->assertExitCode(0);
    }

    public function test_bling_sync_command_all_accounts()
    {
        // Create valid tokens for both accounts
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
        
        // Mock successful API responses - CORRECTED ENDPOINT
        Http::fake([
            'api.bling.com.br/Api/v3/pedidos/vendas' => Http::response([
                'data' => [
                    ['id' => '1', 'numero' => 'PED001'],
                ]
            ], 200)
        ]);
        
        $this->artisan('bling:sync')
            ->expectsOutput('🚀 Bling Synchronization System')
            ->expectsOutput('🔄 Starting synchronization for pro account...')
            ->expectsOutput('✅ pro synchronization completed')
            ->expectsOutput('🔄 Starting synchronization for atacadista account...')
            ->expectsOutput('✅ atacadista synchronization completed')
            ->expectsOutput('📊 Synchronization Summary:')
            ->assertExitCode(0);
    }

    public function test_bling_sync_command_force_option()
    {
        // Create a valid token
        BlingToken::create([
            'account_type' => 'pro',
            'access_token' => 'valid_token',
            'expires_at' => now()->addHour(),
        ]);
        
        // Mock successful API response - CORRECTED ENDPOINT
        Http::fake([
            'api.bling.com.br/Api/v3/pedidos/vendas' => Http::response([
                'data' => [
                    ['id' => '1', 'numero' => 'PED001'],
                ]
            ], 200)
        ]);
        
        $this->artisan('bling:sync --account=pro --force')
            ->expectsOutput('🚀 Bling Synchronization System')
            ->expectsOutput('⚠️  Force update enabled - existing orders will be updated')
            ->expectsOutput('🔄 Starting synchronization for pro account...')
            ->assertExitCode(0);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
    }
}