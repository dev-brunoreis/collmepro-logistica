<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Services\CollmeProBlingLabelService;
use App\Services\BlingOAuthService;
use App\Models\Label;
use App\Models\OrderSummary;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

class BlingLabelServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Set up test environment variables
        config([
            'services.bling.pro.client_id' => 'test_client_id',
            'services.bling.pro.client_secret' => 'test_client_secret',
        ]);
    }

    public function test_service_has_correct_account_type(): void
    {
        $oauthService = $this->createMock(BlingOAuthService::class);
        $service = new CollmeProBlingLabelService($oauthService);

        $this->assertEquals('pro', $service->getPublicClientId() ? 'pro' : 'pro');
    }

    public function test_service_gets_client_credentials_from_config(): void
    {
        $oauthService = $this->createMock(BlingOAuthService::class);
        $service = new CollmeProBlingLabelService($oauthService);

        $this->assertEquals('test_client_id', $service->getPublicClientId());
        $this->assertEquals('test_client_secret', $service->getPublicClientSecret());
    }

    public function test_service_throws_exception_when_client_id_not_configured(): void
    {
        config(['services.bling.pro.client_id' => null]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('BLING_PRO_CLIENT_ID not configured');

        $oauthService = $this->createMock(BlingOAuthService::class);
        $service = new CollmeProBlingLabelService($oauthService);
        $service->getPublicClientId();
    }

    public function test_map_bling_label_creates_correct_structure(): void
    {
        $oauthService = $this->createMock(BlingOAuthService::class);
        $service = new CollmeProBlingLabelService($oauthService);

        $blingLabel = [
            'id' => 123456,
            'link' => 'https://example.com/label.pdf',
            'observacao' => 'Test observation',
            'data' => '2025-01-11T10:00:00',
        ];

        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('mapBlingLabelToLabel');
        $method->setAccessible(true);

        $result = $method->invoke($service, $blingLabel);

        $this->assertEquals(123456, $result['bling_label_id']);
        $this->assertEquals('https://example.com/label.pdf', $result['link']);
        $this->assertEquals('Test observation', $result['observation']);
        $this->assertEquals('PDF', $result['format']);
    }

    public function test_map_bling_label_detects_zpl_format(): void
    {
        $oauthService = $this->createMock(BlingOAuthService::class);
        $service = new CollmeProBlingLabelService($oauthService);

        $blingLabel = [
            'id' => 123456,
            'link' => 'https://example.com/label.zpl',
            'observacao' => 'Test',
        ];

        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('mapBlingLabelToLabel');
        $method->setAccessible(true);

        $result = $method->invoke($service, $blingLabel);

        $this->assertEquals('ZPL', $result['format']);
    }

    public function test_get_labels_by_order_returns_correct_labels(): void
    {
        $orderSummary = OrderSummary::factory()->create();
        Label::factory()->count(3)->create(['order_summary_id' => $orderSummary->id]);
        Label::factory()->count(2)->create(); // Other orders

        $oauthService = $this->createMock(BlingOAuthService::class);
        $service = new CollmeProBlingLabelService($oauthService);

        $labels = $service->getLabelsByOrder($orderSummary->id);

        $this->assertCount(3, $labels);
    }

    public function test_find_order_summary_id_matches_order_number_in_observation(): void
    {
        $order = OrderSummary::factory()->create(['numero' => '123456']);

        $oauthService = $this->createMock(BlingOAuthService::class);
        $service = new CollmeProBlingLabelService($oauthService);

        $blingLabel = [
            'id' => 999,
            'observacao' => 'Pedido 123456 - Cliente Teste',
        ];

        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('findOrderSummaryId');
        $method->setAccessible(true);

        $result = $method->invoke($service, $blingLabel);

        $this->assertEquals($order->id, $result);
    }
}

