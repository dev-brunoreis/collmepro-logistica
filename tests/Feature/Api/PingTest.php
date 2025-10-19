<?php

namespace Tests\Feature\Api;

use Tests\TestCase;

class PingTest extends TestCase
{
    public function test_ping_returns_pong_with_valid_user_agent(): void
    {
        $response = $this->withHeader('User-Agent', 'DevHub-HealthChecker/1.0')
            ->getJson('/api/ping');

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'pong'
            ]);
    }

    public function test_ping_returns_403_with_invalid_user_agent(): void
    {
        $response = $this->withHeader('User-Agent', 'InvalidUserAgent/1.0')
            ->getJson('/api/ping');

        $response->assertStatus(403)
            ->assertJson([
                'error' => 'Unauthorized'
            ]);
    }

    public function test_ping_returns_403_without_user_agent(): void
    {
        $response = $this->getJson('/api/ping');

        $response->assertStatus(403)
            ->assertJson([
                'error' => 'Unauthorized'
            ]);
    }
}

