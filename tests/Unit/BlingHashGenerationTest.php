<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\CollmeProBlingService;

class BlingHashGenerationTest extends TestCase
{
    private CollmeProBlingService $blingService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->blingService = app(CollmeProBlingService::class);
    }

    public function test_generates_consistent_hash_for_same_data()
    {
        $blingOrder = [
            'id' => 12345,
            'numero' => 'TEST-001',
            'total' => 150.00,
            'situacao' => [
                'id' => 6,
                'valor' => 6,
            ],
            'dataAlteracao' => '2025-10-11',
            'contato' => [
                'nome' => 'Test Customer',
                'numeroDocumento' => '12345678900',
            ],
            'notaFiscal' => ['id' => null],
            'transporte' => ['rastreamento' => null],
        ];

        $hash1 = $this->blingService->generateOrderHash($blingOrder);
        $hash2 = $this->blingService->generateOrderHash($blingOrder);

        $this->assertEquals($hash1, $hash2, 'Hash should be consistent for same data');
        $this->assertEquals(32, strlen($hash1), 'Hash should be 32 characters (MD5)');
    }

    public function test_generates_different_hash_for_different_data()
    {
        $blingOrder1 = [
            'id' => 12345,
            'numero' => 'TEST-001',
            'total' => 150.00,
            'situacao' => ['id' => 6, 'valor' => 6],
            'dataAlteracao' => '2025-10-11',
            'contato' => ['nome' => 'Test Customer', 'numeroDocumento' => '12345678900'],
            'notaFiscal' => ['id' => null],
            'transporte' => ['rastreamento' => null],
        ];

        $blingOrder2 = [
            'id' => 12345,
            'numero' => 'TEST-001',
            'total' => 200.00, // Different total
            'situacao' => ['id' => 6, 'valor' => 6],
            'dataAlteracao' => '2025-10-11',
            'contato' => ['nome' => 'Test Customer', 'numeroDocumento' => '12345678900'],
            'notaFiscal' => ['id' => null],
            'transporte' => ['rastreamento' => null],
        ];

        $hash1 = $this->blingService->generateOrderHash($blingOrder1);
        $hash2 = $this->blingService->generateOrderHash($blingOrder2);

        $this->assertNotEquals($hash1, $hash2, 'Hash should be different for different data');
    }

    public function test_detects_situacao_id_change_in_hash()
    {
        $blingOrder1 = [
            'id' => 12345,
            'numero' => 'TEST-001',
            'total' => 150.00,
            'situacao' => ['id' => 6, 'valor' => 6],
            'dataAlteracao' => '2025-10-11',
            'contato' => ['nome' => 'Test Customer', 'numeroDocumento' => '12345678900'],
            'notaFiscal' => ['id' => null],
            'transporte' => ['rastreamento' => null],
        ];

        $blingOrder2 = [
            'id' => 12345,
            'numero' => 'TEST-001',
            'total' => 150.00,
            'situacao' => ['id' => 12, 'valor' => 6], // Changed to CANCELLED
            'dataAlteracao' => '2025-10-11',
            'contato' => ['nome' => 'Test Customer', 'numeroDocumento' => '12345678900'],
            'notaFiscal' => ['id' => null],
            'transporte' => ['rastreamento' => null],
        ];

        $hash1 = $this->blingService->generateOrderHash($blingOrder1);
        $hash2 = $this->blingService->generateOrderHash($blingOrder2);

        $this->assertNotEquals($hash1, $hash2, 'Hash should detect situacao_id change (cancellation)');
    }

    public function test_detects_invoice_addition_in_hash()
    {
        $blingOrder1 = [
            'id' => 12345,
            'numero' => 'TEST-001',
            'total' => 150.00,
            'situacao' => ['id' => 6, 'valor' => 6],
            'dataAlteracao' => '2025-10-11',
            'contato' => ['nome' => 'Test Customer', 'numeroDocumento' => '12345678900'],
            'notaFiscal' => ['id' => null],
            'transporte' => ['rastreamento' => null],
        ];

        $blingOrder2 = [
            'id' => 12345,
            'numero' => 'TEST-001',
            'total' => 150.00,
            'situacao' => ['id' => 6, 'valor' => 6],
            'dataAlteracao' => '2025-10-11',
            'contato' => ['nome' => 'Test Customer', 'numeroDocumento' => '12345678900'],
            'notaFiscal' => ['id' => 987654], // Invoice added
            'transporte' => ['rastreamento' => null],
        ];

        $hash1 = $this->blingService->generateOrderHash($blingOrder1);
        $hash2 = $this->blingService->generateOrderHash($blingOrder2);

        $this->assertNotEquals($hash1, $hash2, 'Hash should detect invoice addition');
    }

    public function test_detects_tracking_addition_in_hash()
    {
        // Use complete order data structure (not list endpoint structure)
        $blingOrder1 = [
            'id' => 12345,
            'numero' => 'TEST-001',
            'total' => 150.00,
            'situacao' => ['id' => 6, 'valor' => 6],
            'dataAlteracao' => '2025-10-11',
            'contato' => ['nome' => 'Test Customer', 'numeroDocumento' => '12345678900'],
            'data' => '2025-10-11',
            'dataSaida' => null,
            'numeroLoja' => 'TEST',
            'notaFiscal' => [],
            'transporte' => ['volumes' => []], // No tracking
        ];

        $blingOrder2 = [
            'id' => 12345,
            'numero' => 'TEST-001',
            'total' => 150.00,
            'situacao' => ['id' => 6, 'valor' => 6],
            'dataAlteracao' => '2025-10-11',
            'contato' => ['nome' => 'Test Customer', 'numeroDocumento' => '12345678900'],
            'data' => '2025-10-11',
            'dataSaida' => null,
            'numeroLoja' => 'TEST',
            'notaFiscal' => [],
            'transporte' => [
                'volumes' => [
                    [
                        'id' => 9001,
                        'servico' => ['id' => 1, 'nome' => 'PAC'],
                    ],
                ],
            ], // Tracking added
        ];

        $hash1 = $this->blingService->generateOrderHash($blingOrder1);
        $hash2 = $this->blingService->generateOrderHash($blingOrder2);

        $this->assertNotEquals($hash1, $hash2, 'Hash should detect tracking code addition');
    }

    public function test_hash_handles_missing_fields_gracefully()
    {
        $minimalOrder = [
            'id' => 12345,
            'numero' => 'TEST-001',
            // Missing most fields
        ];

        $hash = $this->blingService->generateOrderHash($minimalOrder);

        $this->assertEquals(32, strlen($hash), 'Hash should handle missing fields gracefully');
        $this->assertMatchesRegularExpression('/^[a-f0-9]{32}$/', $hash, 'Hash should be valid MD5');
    }
}

