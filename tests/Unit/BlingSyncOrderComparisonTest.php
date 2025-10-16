<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\OrderSummary;
use App\Jobs\BlingSyncJob;
use ReflectionClass;

class BlingSyncOrderComparisonTest extends TestCase
{
    public function test_is_order_data_identical_detects_situacao_id_change()
    {
        // Create an existing order
        $existingOrder = OrderSummary::factory()->create([
            'bling_id' => 12345678,
            'numero' => 'TEST-001',
            'contato_nome' => 'Test Customer',
            'contato_documento' => '12345678900',
            'total' => 150.00,
            'situacao_id' => 6, // Em produção
            'situacao_valor' => 6,
        ]);

        // Mock Bling order with situacao_id changed to 12 (cancelled)
        $blingOrder = [
            'id' => 12345678,
            'numero' => 'TEST-001',
            'total' => 150.00,
            'contato' => [
                'nome' => 'Test Customer',
                'numeroDocumento' => '12345678900',
            ],
            'situacao' => [
                'id' => 12, // Changed to CANCELADO
                'valor' => 6, // Same
            ],
        ];

        // Use reflection to access the private method
        $job = new BlingSyncJob();
        $reflection = new ReflectionClass($job);
        $method = $reflection->getMethod('isOrderDataIdentical');
        $method->setAccessible(true);

        // Act: Call the method
        $result = $method->invoke($job, $existingOrder, $blingOrder);

        // Assert: Should return FALSE because situacao_id changed
        $this->assertFalse($result, 'isOrderDataIdentical should return FALSE when situacao_id changes from 6 to 12');
    }

    public function test_is_order_data_identical_returns_true_when_nothing_changed()
    {
        // Create an existing order
        $existingOrder = OrderSummary::factory()->create([
            'bling_id' => 87654321,
            'numero' => 'TEST-002',
            'contato_nome' => 'Test Customer 2',
            'contato_documento' => '98765432100',
            'total' => 200.00,
            'situacao_id' => 6,
            'situacao_valor' => 6,
        ]);

        // Mock Bling order with NOTHING changed
        $blingOrder = [
            'id' => 87654321,
            'numero' => 'TEST-002',
            'total' => 200.00,
            'contato' => [
                'nome' => 'Test Customer 2',
                'numeroDocumento' => '98765432100',
            ],
            'situacao' => [
                'id' => 6, // Same
                'valor' => 6, // Same
            ],
        ];

        // Use reflection to access the private method
        $job = new BlingSyncJob();
        $reflection = new ReflectionClass($job);
        $method = $reflection->getMethod('isOrderDataIdentical');
        $method->setAccessible(true);

        // Act: Call the method
        $result = $method->invoke($job, $existingOrder, $blingOrder);

        // Assert: Should return TRUE because nothing changed
        $this->assertTrue($result, 'isOrderDataIdentical should return TRUE when nothing changed');
    }

    public function test_is_order_data_identical_detects_situacao_id_change_even_if_other_fields_same()
    {
        // This is the KEY test for the bug fix
        // Before the fix, this would return TRUE (identical) because situacao_id wasn't checked
        // After the fix, it should return FALSE (different) because situacao_id changed

        $existingOrder = OrderSummary::factory()->create([
            'bling_id' => 99999999,
            'numero' => 'TEST-003',
            'contato_nome' => 'Test Customer 3',
            'contato_documento' => '99999999999',
            'total' => 300.00,
            'situacao_id' => 9, // Aprovado
            'situacao_valor' => 9,
        ]);

        $blingOrder = [
            'id' => 99999999,
            'numero' => 'TEST-003',
            'total' => 300.00,
            'contato' => [
                'nome' => 'Test Customer 3',
                'numeroDocumento' => '99999999999',
            ],
            'situacao' => [
                'id' => 12, // Changed to CANCELADO
                'valor' => 9, // Same - valor did NOT change!
            ],
        ];

        $job = new BlingSyncJob();
        $reflection = new ReflectionClass($job);
        $method = $reflection->getMethod('isOrderDataIdentical');
        $method->setAccessible(true);

        $result = $method->invoke($job, $existingOrder, $blingOrder);

        // This is the critical assertion - should be FALSE after our fix
        $this->assertFalse(
            $result,
            'isOrderDataIdentical should detect situacao_id change even when situacao_valor stays the same'
        );
    }
}

