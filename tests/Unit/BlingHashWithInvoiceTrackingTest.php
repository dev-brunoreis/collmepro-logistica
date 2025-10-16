<?php

namespace Tests\Unit;

use App\Services\CollmeProBlingService;
use Tests\TestCase;

class BlingHashWithInvoiceTrackingTest extends TestCase
{
    private CollmeProBlingService $blingService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->blingService = app(CollmeProBlingService::class);
    }

    /** @test */
    public function it_generates_consistent_hash_for_same_complete_order_data()
    {
        $completeOrderData = [
            'id' => 12345,
            'numero' => '1001',
            'total' => 100.50,
            'situacao' => ['id' => 1, 'valor' => 2],
            'dataAlteracao' => '2025-01-01 10:00:00',
            'contato' => ['nome' => 'Test Customer', 'numeroDocumento' => '12345678900'],
            'data' => '2025-01-01',
            'dataSaida' => '2025-01-02',
            'numeroLoja' => 'LOJA-001',
            'notaFiscal' => [
                'id' => 5001,
                'numero' => '12345',
                'chaveAcesso' => 'ABC123',
                'valorNota' => 100.50,
                'situacao' => 1,
            ],
            'transporte' => [
                'volumes' => [
                    [
                        'id' => 9001,
                        'servico' => ['id' => 1, 'nome' => 'PAC'],
                    ],
                ],
            ],
        ];

        $hash1 = $this->blingService->generateOrderHash($completeOrderData);
        $hash2 = $this->blingService->generateOrderHash($completeOrderData);

        $this->assertEquals($hash1, $hash2, 'Hash should be consistent for same data');
        $this->assertIsString($hash1);
        $this->assertEquals(32, strlen($hash1), 'Hash should be 32 characters (MD5)');
    }

    /** @test */
    public function it_generates_different_hash_when_invoice_added()
    {
        $orderWithoutInvoice = [
            'id' => 12345,
            'numero' => '1001',
            'total' => 100.50,
            'situacao' => ['id' => 1, 'valor' => 2],
            'dataAlteracao' => '2025-01-01 10:00:00',
            'contato' => ['nome' => 'Test Customer', 'numeroDocumento' => '12345678900'],
            'data' => '2025-01-01',
            'notaFiscal' => [],
            'transporte' => ['volumes' => []],
        ];

        $orderWithInvoice = array_merge($orderWithoutInvoice, [
            'notaFiscal' => [
                'id' => 5001,
                'numero' => '12345',
                'chaveAcesso' => 'ABC123',
                'valorNota' => 100.50,
                'situacao' => 1,
            ],
        ]);

        $hash1 = $this->blingService->generateOrderHash($orderWithoutInvoice);
        $hash2 = $this->blingService->generateOrderHash($orderWithInvoice);

        $this->assertNotEquals($hash1, $hash2, 'Hash should change when invoice is added');
    }

    /** @test */
    public function it_generates_different_hash_when_tracking_added()
    {
        $orderWithoutTracking = [
            'id' => 12345,
            'numero' => '1001',
            'total' => 100.50,
            'situacao' => ['id' => 1, 'valor' => 2],
            'dataAlteracao' => '2025-01-01 10:00:00',
            'contato' => ['nome' => 'Test Customer', 'numeroDocumento' => '12345678900'],
            'data' => '2025-01-01',
            'notaFiscal' => [
                'id' => 5001,
                'numero' => '12345',
            ],
            'transporte' => ['volumes' => []],
        ];

        $orderWithTracking = array_merge($orderWithoutTracking, [
            'transporte' => [
                'volumes' => [
                    [
                        'id' => 9001,
                        'servico' => ['id' => 1, 'nome' => 'PAC'],
                    ],
                ],
            ],
        ]);

        $hash1 = $this->blingService->generateOrderHash($orderWithoutTracking);
        $hash2 = $this->blingService->generateOrderHash($orderWithTracking);

        $this->assertNotEquals($hash1, $hash2, 'Hash should change when tracking is added');
    }

    /** @test */
    public function it_generates_different_hash_when_order_cancelled()
    {
        $normalOrder = [
            'id' => 12345,
            'numero' => '1001',
            'total' => 100.50,
            'situacao' => ['id' => 1, 'valor' => 2],
            'dataAlteracao' => '2025-01-01 10:00:00',
            'contato' => ['nome' => 'Test Customer', 'numeroDocumento' => '12345678900'],
            'data' => '2025-01-01',
            'notaFiscal' => [],
            'transporte' => ['volumes' => []],
        ];

        $cancelledOrder = array_merge($normalOrder, [
            'situacao' => ['id' => 12, 'valor' => 4], // Cancelled status
        ]);

        $hash1 = $this->blingService->generateOrderHash($normalOrder);
        $hash2 = $this->blingService->generateOrderHash($cancelledOrder);

        $this->assertNotEquals($hash1, $hash2, 'Hash should change when order is cancelled');
    }

    /** @test */
    public function it_generates_lightweight_hash_from_list_data()
    {
        $listData = [
            'id' => 12345,
            'numero' => '1001',
            'total' => 100.50,
            'situacao' => ['id' => 1, 'valor' => 2],
            'dataAlteracao' => '2025-01-01 10:00:00',
            'contato' => ['nome' => 'Test Customer', 'numeroDocumento' => '12345678900'],
            'notaFiscal' => ['id' => 5001],
        ];

        $hash = $this->blingService->generateLightweightHash($listData);

        $this->assertIsString($hash);
        $this->assertEquals(32, strlen($hash), 'Lightweight hash should be 32 characters (MD5)');
    }

    /** @test */
    public function it_generates_different_comprehensive_and_lightweight_hashes()
    {
        $listData = [
            'id' => 12345,
            'numero' => '1001',
            'total' => 100.50,
            'situacao' => ['id' => 1, 'valor' => 2],
            'dataAlteracao' => '2025-01-01 10:00:00',
            'contato' => ['nome' => 'Test Customer', 'numeroDocumento' => '12345678900'],
            'notaFiscal' => ['id' => 5001],
        ];

        $completeData = array_merge($listData, [
            'data' => '2025-01-01',
            'dataSaida' => '2025-01-02',
            'numeroLoja' => 'LOJA-001',
            'notaFiscal' => [
                'id' => 5001,
                'numero' => '12345',
                'chaveAcesso' => 'ABC123',
                'valorNota' => 100.50,
                'situacao' => 1,
            ],
            'transporte' => [
                'volumes' => [
                    [
                        'id' => 9001,
                        'servico' => ['id' => 1, 'nome' => 'PAC'],
                    ],
                ],
            ],
        ]);

        $lightweightHash = $this->blingService->generateLightweightHash($listData);
        $comprehensiveHash = $this->blingService->generateOrderHash($completeData);

        $this->assertNotEquals($lightweightHash, $comprehensiveHash, 
            'Lightweight and comprehensive hashes should be different due to different data included');
    }

    /** @test */
    public function it_handles_empty_optional_fields_gracefully()
    {
        $minimalOrder = [
            'id' => 12345,
            'numero' => '1001',
            'total' => 100.50,
            'situacao' => ['id' => 1, 'valor' => 2],
            'contato' => [],
            'notaFiscal' => [],
            'transporte' => [],
        ];

        $hash = $this->blingService->generateOrderHash($minimalOrder);

        $this->assertIsString($hash);
        $this->assertEquals(32, strlen($hash));
    }
}


