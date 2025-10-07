<?php

namespace Tests\Unit\Models;

use Tests\TestCase;
use App\Models\BlingToken;
use Carbon\Carbon;

class BlingTokenTest extends TestCase
{
    public function test_can_create_bling_token()
    {
        $token = BlingToken::create([
            'account_type' => 'pro',
            'access_token' => 'test_access_token',
            'refresh_token' => 'test_refresh_token',
            'expires_at' => now()->addHours(6),
            'scope' => ['read', 'write'],
        ]);

        $this->assertInstanceOf(BlingToken::class, $token);
        $this->assertEquals('pro', $token->account_type);
        $this->assertEquals('test_access_token', $token->access_token);
        $this->assertEquals('test_refresh_token', $token->refresh_token);
        $this->assertIsArray($token->scope);
    }

    public function test_is_expired_returns_true_when_token_expired()
    {
        $token = BlingToken::create([
            'account_type' => 'pro',
            'access_token' => 'test_token',
            'expires_at' => now()->subHour(), // Expired 1 hour ago
        ]);

        $this->assertTrue($token->isExpired());
    }

    public function test_is_expired_returns_false_when_token_valid()
    {
        $token = BlingToken::create([
            'account_type' => 'pro',
            'access_token' => 'test_token',
            'expires_at' => now()->addHour(), // Valid for 1 hour
        ]);

        $this->assertFalse($token->isExpired());
    }

    public function test_is_expiring_soon_returns_true_when_expires_in_5_minutes()
    {
        $token = BlingToken::create([
            'account_type' => 'pro',
            'access_token' => 'test_token',
            'expires_at' => now()->addMinutes(3), // Expires in 3 minutes
        ]);

        $this->assertTrue($token->isExpiringSoon());
    }

    public function test_is_expiring_soon_returns_false_when_expires_later()
    {
        $token = BlingToken::create([
            'account_type' => 'pro',
            'access_token' => 'test_token',
            'expires_at' => now()->addMinutes(10), // Expires in 10 minutes
        ]);

        $this->assertFalse($token->isExpiringSoon());
    }

    public function test_get_valid_token_returns_token_when_valid()
    {
        BlingToken::create([
            'account_type' => 'pro',
            'access_token' => 'valid_token',
            'expires_at' => now()->addHour(),
        ]);

        $validToken = BlingToken::getValidToken('pro');

        $this->assertNotNull($validToken);
        $this->assertEquals('valid_token', $validToken->access_token);
    }

    public function test_get_valid_token_returns_null_when_expired()
    {
        BlingToken::create([
            'account_type' => 'pro',
            'access_token' => 'expired_token',
            'expires_at' => now()->subHour(),
        ]);

        $validToken = BlingToken::getValidToken('pro');

        $this->assertNull($validToken);
    }

    public function test_update_or_create_token_creates_new_token()
    {
        $token = BlingToken::updateOrCreateToken(
            'pro',
            'new_access_token',
            'new_refresh_token',
            3600, // 1 hour
            ['read', 'write']
        );

        $this->assertInstanceOf(BlingToken::class, $token);
        $this->assertEquals('pro', $token->account_type);
        $this->assertEquals('new_access_token', $token->access_token);
        $this->assertEquals('new_refresh_token', $token->refresh_token);
        $this->assertEquals(['read', 'write'], $token->scope);
    }

    public function test_update_or_create_token_updates_existing_token()
    {
        // Create initial token
        BlingToken::create([
            'account_type' => 'pro',
            'access_token' => 'old_token',
            'expires_at' => now()->addHour(),
        ]);

        // Update with new token
        $updatedToken = BlingToken::updateOrCreateToken(
            'pro',
            'new_access_token',
            'new_refresh_token',
            3600,
            ['read']
        );

        $this->assertEquals('new_access_token', $updatedToken->access_token);
        $this->assertEquals('new_refresh_token', $updatedToken->refresh_token);
        $this->assertEquals(['read'], $updatedToken->scope);

        // Verify only one token exists for this account
        $this->assertEquals(1, BlingToken::where('account_type', 'pro')->count());
    }

    protected function setUp(): void
    {
        parent::setUp();
        
        // RefreshDatabase trait handles cleanup automatically
    }
}
