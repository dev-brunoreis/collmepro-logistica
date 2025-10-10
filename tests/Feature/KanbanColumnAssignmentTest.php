<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\OrderStatus;
use App\Models\OrderSummary;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KanbanColumnAssignmentTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a test user with admin role (has all permissions)
        $this->user = User::factory()->create([
            'role' => 'admin',
        ]);
    }

    /**
     * Test order without invoice goes to Faturamento column.
     */
    public function test_order_without_invoice_goes_to_faturamento(): void
    {
        $order = OrderSummary::factory()->create();

        $response = $this->withoutMiddleware()
            ->actingAs($this->user)
            ->getJson('/dashboard/painel/orders');

        $response->assertStatus(200);
        $kanbanData = $response->json('kanbanData');

        // Find the order in faturamento column
        $orderInFaturamento = collect($kanbanData['faturamento'] ?? [])
            ->firstWhere('id', $order->id);

        $this->assertNotNull($orderInFaturamento, 'Order should be in Faturamento column');
    }

    /**
     * Test order with invoice but no picking goes to Checkout column.
     */
    public function test_order_with_invoice_no_picking_goes_to_checkout(): void
    {
        $order = OrderSummary::factory()->create();
        Invoice::factory()->create([
            'order_summary_id' => $order->id,
            'numero' => 'NF-001',
        ]);

        $response = $this->withoutMiddleware()
            ->actingAs($this->user)
            ->getJson('/dashboard/painel/orders');

        $response->assertStatus(200);
        $kanbanData = $response->json('kanbanData');

        // Find the order in checkout column
        $orderInCheckout = collect($kanbanData['checkout'] ?? [])
            ->firstWhere('id', $order->id);

        $this->assertNotNull($orderInCheckout, 'Order should be in Checkout column');
    }

    /**
     * Test order with invoice and picking but no packing goes to Embalagem column.
     */
    public function test_order_with_invoice_and_picking_goes_to_embalagem(): void
    {
        $order = OrderSummary::factory()->create();
        Invoice::factory()->create([
            'order_summary_id' => $order->id,
            'numero' => 'NF-001',
        ]);
        OrderStatus::create([
            'order_summary_id' => $order->id,
            'picking' => true,
            'packing' => false,
            'picked_up' => false,
        ]);

        $response = $this->withoutMiddleware()
            ->actingAs($this->user)
            ->getJson('/dashboard/painel/orders');

        $response->assertStatus(200);
        $kanbanData = $response->json('kanbanData');

        // Find the order in embalagem column
        $orderInEmbalagem = collect($kanbanData['embalagem'] ?? [])
            ->firstWhere('id', $order->id);

        $this->assertNotNull($orderInEmbalagem, 'Order should be in Embalagem column');
    }

    /**
     * Test order with invoice, picking, packing but no tracking goes to Etiqueta column.
     */
    public function test_order_with_picking_and_packing_no_tracking_goes_to_etiqueta(): void
    {
        $order = OrderSummary::factory()->create();
        Invoice::factory()->create([
            'order_summary_id' => $order->id,
            'numero' => 'NF-001',
        ]);
        OrderStatus::create([
            'order_summary_id' => $order->id,
            'picking' => true,
            'packing' => true,
            'picked_up' => false,
        ]);

        $response = $this->withoutMiddleware()
            ->actingAs($this->user)
            ->getJson('/dashboard/painel/orders');

        $response->assertStatus(200);
        $kanbanData = $response->json('kanbanData');

        // Find the order in etiqueta column
        $orderInEtiqueta = collect($kanbanData['etiqueta'] ?? [])
            ->firstWhere('id', $order->id);

        $this->assertNotNull($orderInEtiqueta, 'Order should be in Etiqueta column');
    }

    /**
     * Test order with invoice, picking, packing and tracking goes to Coleta Aguardando column.
     */
    public function test_order_with_all_status_and_tracking_goes_to_coleta_aguardando(): void
    {
        $order = OrderSummary::factory()->create([
            'tracking_code' => 'BR123456789BR',
        ]);
        Invoice::factory()->create([
            'order_summary_id' => $order->id,
            'numero' => 'NF-001',
        ]);
        OrderStatus::create([
            'order_summary_id' => $order->id,
            'picking' => true,
            'packing' => true,
            'picked_up' => false,
        ]);

        $response = $this->withoutMiddleware()
            ->actingAs($this->user)
            ->getJson('/dashboard/painel/orders');

        $response->assertStatus(200);
        $kanbanData = $response->json('kanbanData');

        // Find the order in coleta_aguardando column
        $orderInColetaAguardando = collect($kanbanData['coleta_aguardando'] ?? [])
            ->firstWhere('id', $order->id);

        $this->assertNotNull($orderInColetaAguardando, 'Order should be in Coleta Aguardando column');
    }

    /**
     * Test order with picked_up status goes to Coleta Realizada column.
     */
    public function test_order_with_picked_up_goes_to_coleta_realizada(): void
    {
        $order = OrderSummary::factory()->create([
            'tracking_code' => 'BR123456789BR',
        ]);
        Invoice::factory()->create([
            'order_summary_id' => $order->id,
            'numero' => 'NF-001',
        ]);
        OrderStatus::create([
            'order_summary_id' => $order->id,
            'picking' => true,
            'packing' => true,
            'picked_up' => true,
        ]);

        $response = $this->withoutMiddleware()
            ->actingAs($this->user)
            ->getJson('/dashboard/painel/orders');

        $response->assertStatus(200);
        $kanbanData = $response->json('kanbanData');

        // Find the order in coleta_realizada column
        $orderInColetaRealizada = collect($kanbanData['coleta_realizada'] ?? [])
            ->firstWhere('id', $order->id);

        $this->assertNotNull($orderInColetaRealizada, 'Order should be in Coleta Realizada column');
    }

    /**
     * Test sequential flow: order moves through all columns correctly.
     */
    public function test_order_moves_through_all_columns_sequentially(): void
    {
        $order = OrderSummary::factory()->create();

        // 1. Start in Faturamento (no invoice)
        $response = $this->withoutMiddleware()
            ->actingAs($this->user)
            ->getJson('/dashboard/painel/orders');
        $kanbanData = $response->json('kanbanData');
        $this->assertNotNull(
            collect($kanbanData['faturamento'] ?? [])->firstWhere('id', $order->id),
            'Order should start in Faturamento'
        );

        // 2. Add invoice -> should move to Checkout
        Invoice::factory()->create([
            'order_summary_id' => $order->id,
            'numero' => 'NF-001',
        ]);
        $order->refresh();
        
        $response = $this->withoutMiddleware()
            ->actingAs($this->user)
            ->getJson('/dashboard/painel/orders');
        $kanbanData = $response->json('kanbanData');
        $this->assertNotNull(
            collect($kanbanData['checkout'] ?? [])->firstWhere('id', $order->id),
            'Order should move to Checkout after invoice'
        );

        // 3. Mark picking -> should move to Embalagem
        OrderStatus::create([
            'order_summary_id' => $order->id,
            'picking' => true,
            'packing' => false,
            'picked_up' => false,
        ]);
        
        $response = $this->withoutMiddleware()
            ->actingAs($this->user)
            ->getJson('/dashboard/painel/orders');
        $kanbanData = $response->json('kanbanData');
        $this->assertNotNull(
            collect($kanbanData['embalagem'] ?? [])->firstWhere('id', $order->id),
            'Order should move to Embalagem after picking'
        );

        // 4. Mark packing -> should move to Etiqueta
        $order->orderStatus->update(['packing' => true]);
        
        $response = $this->withoutMiddleware()
            ->actingAs($this->user)
            ->getJson('/dashboard/painel/orders');
        $kanbanData = $response->json('kanbanData');
        $this->assertNotNull(
            collect($kanbanData['etiqueta'] ?? [])->firstWhere('id', $order->id),
            'Order should move to Etiqueta after packing'
        );

        // 5. Add tracking -> should move to Coleta Aguardando
        $order->update(['tracking_code' => 'BR123456789BR']);
        
        $response = $this->withoutMiddleware()
            ->actingAs($this->user)
            ->getJson('/dashboard/painel/orders');
        $kanbanData = $response->json('kanbanData');
        $this->assertNotNull(
            collect($kanbanData['coleta_aguardando'] ?? [])->firstWhere('id', $order->id),
            'Order should move to Coleta Aguardando after tracking'
        );

        // 6. Mark picked_up -> should move to Coleta Realizada
        $order->orderStatus->update(['picked_up' => true]);
        
        $response = $this->withoutMiddleware()
            ->actingAs($this->user)
            ->getJson('/dashboard/painel/orders');
        $kanbanData = $response->json('kanbanData');
        $this->assertNotNull(
            collect($kanbanData['coleta_realizada'] ?? [])->firstWhere('id', $order->id),
            'Order should move to Coleta Realizada after picked_up'
        );
    }
}

