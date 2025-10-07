<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Services\CollmeAtacadistaBlingService;
use App\Services\BlingOAuthService;

class CollmeAtacadistaBlingServiceTest extends TestCase
{
    private CollmeAtacadistaBlingService $service;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Mock the OAuth service
        $oauthService = $this->createMock(BlingOAuthService::class);
        $this->service = new CollmeAtacadistaBlingService($oauthService);
        
        // Set test environment variables
        config([
            'services.bling.atacadista.client_id' => 'test_atacadista_client_id',
            'services.bling.atacadista.client_secret' => 'test_atacadista_client_secret',
        ]);
    }

    public function test_get_account_type_returns_atacadista()
    {
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('getAccountType');
        $method->setAccessible(true);
        
        $result = $method->invoke($this->service);
        
        $this->assertEquals('atacadista', $result);
    }

    public function test_get_client_id_returns_configured_value()
    {
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('getClientId');
        $method->setAccessible(true);
        
        $result = $method->invoke($this->service);
        
        $this->assertEquals('test_atacadista_client_id', $result);
    }

    public function test_get_client_secret_returns_configured_value()
    {
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('getClientSecret');
        $method->setAccessible(true);
        
        $result = $method->invoke($this->service);
        
        $this->assertEquals('test_atacadista_client_secret', $result);
    }

    public function test_get_client_id_throws_exception_when_not_configured()
    {
        config(['services.bling.atacadista.client_id' => null]);
        
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('getClientId');
        $method->setAccessible(true);
        
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('BLING_ATACADISTA_CLIENT_ID not configured in environment');
        
        $method->invoke($this->service);
    }

    public function test_get_client_secret_throws_exception_when_not_configured()
    {
        config(['services.bling.atacadista.client_secret' => null]);
        
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('getClientSecret');
        $method->setAccessible(true);
        
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('BLING_ATACADISTA_CLIENT_SECRET not configured in environment');
        
        $method->invoke($this->service);
    }

    public function test_get_public_client_id_returns_client_id()
    {
        $result = $this->service->getPublicClientId();
        
        $this->assertEquals('test_atacadista_client_id', $result);
    }

    public function test_get_public_client_secret_returns_client_secret()
    {
        $result = $this->service->getPublicClientSecret();
        
        $this->assertEquals('test_atacadista_client_secret', $result);
    }

    public function test_get_authorization_url_calls_oauth_service()
    {
        $oauthService = $this->createMock(BlingOAuthService::class);
        $oauthService->expects($this->once())
            ->method('getAuthorizationUrl')
            ->with('atacadista', 'test_atacadista_client_id')
            ->willReturn('https://bling.com.br/Api/v3/oauth/authorize?test=123');

        $service = new CollmeAtacadistaBlingService($oauthService);
        
        $result = $service->getAuthorizationUrl();
        
        $this->assertEquals('https://bling.com.br/Api/v3/oauth/authorize?test=123', $result);
    }
}
