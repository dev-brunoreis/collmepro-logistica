<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\OrderStatus;
use App\Models\OrderSummary;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KanbanStatusValidationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private OrderSummary $order;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a test user with admin role (has all permissions)
        $this->user = User::factory()->create([
            'role' => 'admin',
        ]);

        // Create a test order
        $this->order = OrderSummary::factory()->create([
            'bling_id' => 12345,
            'numero' => 'TEST-001',
            'data' => now(),
            'total' => 100.00,
            'contato_nome' => 'Test Customer',
        ]);
    }

    /**
     * Test cannot mark picking without invoice.
     */
    public function test_cannot_mark_picking_without_invoice(): void
    {
        $response = $this->withoutMiddleware()
            ->actingAs($this->user)
            ->postJson("/dashboard/painel/{$this->order->id}/status", [
                'status' => 'picking',
            ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Não é possível marcar picking sem nota fiscal. O pedido deve estar faturado primeiro.',
            ]);
    }

    /**
     * Test can mark picking with invoice.
     */
    public function test_can_mark_picking_with_invoice(): void
    {
        // Create invoice for the order
        Invoice::factory()->create([
            'order_summary_id' => $this->order->id,
            'numero' => 'NF-001',
        ]);

        $response = $this->withoutMiddleware()
            ->actingAs($this->user)
            ->postJson("/dashboard/painel/{$this->order->id}/status", [
                'status' => 'picking',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Status atualizado com sucesso',
            ]);

        $this->assertDatabaseHas('order_statuses', [
            'order_summary_id' => $this->order->id,
            'picking' => true,
        ]);
    }

    /**
     * Test cannot mark packing without picking.
     */
    public function test_cannot_mark_packing_without_picking(): void
    {
        // Create invoice but no picking status
        Invoice::factory()->create([
            'order_summary_id' => $this->order->id,
            'numero' => 'NF-001',
        ]);

        OrderStatus::create([
            'order_summary_id' => $this->order->id,
            'picking' => false,
            'packing' => false,
            'picked_up' => false,
        ]);

        $response = $this->withoutMiddleware()
            ->actingAs($this->user)
            ->postJson("/dashboard/painel/{$this->order->id}/status", [
                'status' => 'packing',
            ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Não é possível marcar packing sem ter picking marcado primeiro.',
            ]);
    }

    /**
     * Test can mark packing with picking.
     */
    public function test_can_mark_packing_with_picking(): void
    {
        // Create invoice and picking status
        Invoice::factory()->create([
            'order_summary_id' => $this->order->id,
            'numero' => 'NF-001',
        ]);

        OrderStatus::create([
            'order_summary_id' => $this->order->id,
            'picking' => true,
            'packing' => false,
            'picked_up' => false,
        ]);

        $response = $this->withoutMiddleware()
            ->actingAs($this->user)
            ->postJson("/dashboard/painel/{$this->order->id}/status", [
                'status' => 'packing',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Status atualizado com sucesso',
            ]);

        $this->assertDatabaseHas('order_statuses', [
            'order_summary_id' => $this->order->id,
            'packing' => true,
        ]);
    }

    /**
     * Test cannot mark picked_up without packing.
     */
    public function test_cannot_mark_picked_up_without_packing(): void
    {
        // Create invoice and picking status, but no packing
        Invoice::factory()->create([
            'order_summary_id' => $this->order->id,
            'numero' => 'NF-001',
        ]);

        OrderStatus::create([
            'order_summary_id' => $this->order->id,
            'picking' => true,
            'packing' => false,
            'picked_up' => false,
        ]);

        $response = $this->withoutMiddleware()
            ->actingAs($this->user)
            ->postJson("/dashboard/painel/{$this->order->id}/status", [
                'status' => 'picked_up',
            ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Não é possível marcar picked_up sem ter packing marcado primeiro.',
            ]);
    }

    /**
     * Test can mark picked_up with packing.
     */
    public function test_can_mark_picked_up_with_packing(): void
    {
        // Create invoice, picking, and packing status
        Invoice::factory()->create([
            'order_summary_id' => $this->order->id,
            'numero' => 'NF-001',
        ]);

        OrderStatus::create([
            'order_summary_id' => $this->order->id,
            'picking' => true,
            'packing' => true,
            'picked_up' => false,
        ]);

        $response = $this->withoutMiddleware()
            ->actingAs($this->user)
            ->postJson("/dashboard/painel/{$this->order->id}/status", [
                'status' => 'picked_up',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Status atualizado com sucesso',
            ]);

        $this->assertDatabaseHas('order_statuses', [
            'order_summary_id' => $this->order->id,
            'picked_up' => true,
        ]);
    }

    /**
     * Test cannot unmark picking if packing is marked.
     */
    public function test_cannot_unmark_picking_if_packing_is_marked(): void
    {
        // Create invoice and both picking and packing status
        Invoice::factory()->create([
            'order_summary_id' => $this->order->id,
            'numero' => 'NF-001',
        ]);

        OrderStatus::create([
            'order_summary_id' => $this->order->id,
            'picking' => true,
            'packing' => true,
            'picked_up' => false,
        ]);

        // Try to mark picking again (which would toggle it off)
        $response = $this->withoutMiddleware()
            ->actingAs($this->user)
            ->postJson("/dashboard/painel/{$this->order->id}/status", [
                'status' => 'picking',
            ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Não é possível desmarcar picking enquanto packing estiver marcado.',
            ]);
    }

    /**
     * Test cannot unmark packing if picked_up is marked.
     */
    public function test_cannot_unmark_packing_if_picked_up_is_marked(): void
    {
        // Create invoice and all status marked
        Invoice::factory()->create([
            'order_summary_id' => $this->order->id,
            'numero' => 'NF-001',
        ]);

        OrderStatus::create([
            'order_summary_id' => $this->order->id,
            'picking' => true,
            'packing' => true,
            'picked_up' => true,
        ]);

        // Try to mark packing again (which would toggle it off)
        $response = $this->withoutMiddleware()
            ->actingAs($this->user)
            ->postJson("/dashboard/painel/{$this->order->id}/status", [
                'status' => 'packing',
            ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Não é possível desmarcar packing enquanto picked_up estiver marcado.',
            ]);
    }

    /**
     * Test cannot complete order without invoice.
     */
    public function test_cannot_complete_order_without_invoice(): void
    {
        $response = $this->withoutMiddleware()
            ->actingAs($this->user)
            ->postJson("/dashboard/painel/{$this->order->id}/status", [
                'status' => 'completed',
            ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Não é possível completar pedido sem nota fiscal.',
            ]);
    }

    /**
     * Test can complete order with invoice.
     */
    public function test_can_complete_order_with_invoice(): void
    {
        // Create invoice
        Invoice::factory()->create([
            'order_summary_id' => $this->order->id,
            'numero' => 'NF-001',
        ]);

        $response = $this->withoutMiddleware()
            ->actingAs($this->user)
            ->postJson("/dashboard/painel/{$this->order->id}/status", [
                'status' => 'completed',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Status atualizado com sucesso',
            ]);

        $this->assertDatabaseHas('order_statuses', [
            'order_summary_id' => $this->order->id,
            'picking' => true,
            'packing' => true,
            'picked_up' => true,
        ]);
    }
}

