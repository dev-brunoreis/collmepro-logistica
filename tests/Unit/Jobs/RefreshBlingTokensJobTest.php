<?php

namespace Tests\Unit\Jobs;

use Tests\TestCase;
use App\Jobs\RefreshBlingTokensJob;
use App\Models\BlingToken;
use App\Services\BlingOAuthService;
use App\Services\CollmeProBlingService;
use App\Services\CollmeAtacadistaBlingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class RefreshBlingTokensJobTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Mock the log channel to avoid file writing during tests
        Log::shouldReceive('channel')->andReturnSelf();
        Log::shouldReceive('info')->andReturnNull();
        Log::shouldReceive('warning')->andReturnNull();
        Log::shouldReceive('error')->andReturnNull();
    }

    public function test_job_handles_missing_tokens_gracefully(): void
    {
        // No tokens in database
        $this->assertDatabaseCount('bling_tokens', 0);

        // Job should not throw exception
        $job = new RefreshBlingTokensJob();
        $job->handle();

        $this->assertTrue(true); // Job completed without exception
    }

    public function test_job_skips_valid_tokens(): void
    {
        // Create a token that expires in 10 hours (no refresh needed)
        $token = BlingToken::create([
            'account_type' => 'pro',
            'access_token' => 'valid_token',
            'refresh_token' => 'refresh_token',
            'expires_at' => now()->addHours(10),
            'scope' => ['read', 'write'],
        ]);

        $job = new RefreshBlingTokensJob();
        $job->handle();

        // Token should remain unchanged
        $token->refresh();
        $this->assertEquals('valid_token', $token->access_token);
    }

    public function test_job_refreshes_expiring_tokens(): void
    {
        // Create a token that expires in 2 hours (needs refresh)
        BlingToken::create([
            'account_type' => 'pro',
            'access_token' => 'expiring_token',
            'refresh_token' => 'refresh_token',
            'expires_at' => now()->addHours(2),
            'scope' => ['read', 'write'],
        ]);

        // Mock services
        $oauthService = $this->createMock(BlingOAuthService::class);
        $oauthService->expects($this->once())
            ->method('refreshToken')
            ->with(
                $this->equalTo('refresh_token'),
                $this->anything(),
                $this->anything(),
                $this->equalTo('pro')
            )
            ->willReturn('new_access_token');

        $this->app->instance(BlingOAuthService::class, $oauthService);

        $job = new RefreshBlingTokensJob();
        $job->handle();

        $this->assertTrue(true); // Refresh was attempted
    }

    public function test_job_handles_both_accounts(): void
    {
        // Create tokens for both accounts
        BlingToken::create([
            'account_type' => 'pro',
            'access_token' => 'pro_token',
            'refresh_token' => 'pro_refresh',
            'expires_at' => now()->addHours(2),
            'scope' => ['read', 'write'],
        ]);

        BlingToken::create([
            'account_type' => 'atacadista',
            'access_token' => 'atacadista_token',
            'refresh_token' => 'atacadista_refresh',
            'expires_at' => now()->addHours(2),
            'scope' => ['read', 'write'],
        ]);

        $oauthService = $this->createMock(BlingOAuthService::class);
        $oauthService->expects($this->exactly(2))
            ->method('refreshToken')
            ->willReturn('new_token');

        $this->app->instance(BlingOAuthService::class, $oauthService);

        $job = new RefreshBlingTokensJob();
        $job->handle();

        $this->assertTrue(true); // Both accounts were processed
    }
}

