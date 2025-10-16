<?php

namespace Tests\Unit\Services;

use App\Services\CarrierService;
use Tests\TestCase;

class CarrierServiceTest extends TestCase
{
    /**
     * Test Correios - Sedex mapping
     */
    public function test_sedex_maps_to_correios_sedex(): void
    {
        $result = CarrierService::getCarrierDisplay('SEDEX');
        $this->assertEquals('Correios - Sedex', $result);
    }

    /**
     * Test Correios - Sedex Contrato AG mapping
     */
    public function test_sedex_contrato_ag_maps_to_correios_sedex(): void
    {
        $result = CarrierService::getCarrierDisplay('SEDEX CONTRATO AG');
        $this->assertEquals('Correios - Sedex', $result);
    }

    /**
     * Test Correios - PAC mapping
     */
    public function test_pac_maps_to_correios_pac(): void
    {
        $result = CarrierService::getCarrierDisplay('PAC');
        $this->assertEquals('Correios - PAC', $result);
    }

    /**
     * Test Total Express - Expresso mapping
     */
    public function test_expresso_maps_to_total_express_expresso(): void
    {
        $result = CarrierService::getCarrierDisplay('Expresso');
        $this->assertEquals('Total Express - Expresso', $result);
    }

    /**
     * Test Shopify Shipping mapping
     */
    public function test_lsv_standard_maps_to_shopify_shipping(): void
    {
        $result = CarrierService::getCarrierDisplay('LSV-Standard-BR PICKUP');
        $this->assertEquals('Shopify Shipping', $result);
    }

    /**
     * Test Retirada em Loja mapping
     */
    public function test_coleta_maps_to_retirada_em_loja(): void
    {
        $result = CarrierService::getCarrierDisplay('Coleta');
        $this->assertEquals('Retirada em Loja', $result);
    }

    /**
     * Test null service type returns dash
     */
    public function test_null_service_type_returns_dash(): void
    {
        $result = CarrierService::getCarrierDisplay(null);
        $this->assertEquals('-', $result);
    }

    /**
     * Test empty string returns dash
     */
    public function test_empty_string_returns_dash(): void
    {
        $result = CarrierService::getCarrierDisplay('');
        $this->assertEquals('-', $result);
    }

    /**
     * Test unknown service type returns dash
     */
    public function test_unknown_service_type_returns_dash(): void
    {
        $result = CarrierService::getCarrierDisplay('Unknown Carrier');
        $this->assertEquals('-', $result);
    }

    /**
     * Test extract service type from raw tracking data
     */
    public function test_extract_service_type_from_raw_tracking_data(): void
    {
        $rawTrackingData = [
            'servico' => [
                'nome' => 'SEDEX',
                'codigo' => '2'
            ]
        ];

        $result = CarrierService::extractServiceType($rawTrackingData);
        $this->assertEquals('SEDEX', $result);
    }

    /**
     * Test extract service type returns null for empty data
     */
    public function test_extract_service_type_returns_null_for_empty_data(): void
    {
        $result = CarrierService::extractServiceType(null);
        $this->assertNull($result);
    }

    /**
     * Test extract service type returns null when servico is missing
     */
    public function test_extract_service_type_returns_null_when_servico_missing(): void
    {
        $rawTrackingData = [
            'other' => 'data'
        ];

        $result = CarrierService::extractServiceType($rawTrackingData);
        $this->assertNull($result);
    }

    /**
     * Test get carrier display from raw data
     */
    public function test_get_carrier_display_from_raw_data(): void
    {
        $rawTrackingData = [
            'servico' => [
                'nome' => 'PAC',
                'codigo' => '1'
            ]
        ];

        $result = CarrierService::getCarrierDisplayFromRawData($rawTrackingData);
        $this->assertEquals('Correios - PAC', $result);
    }

    /**
     * Test get carrier display from raw data with null data
     */
    public function test_get_carrier_display_from_raw_data_with_null(): void
    {
        $result = CarrierService::getCarrierDisplayFromRawData(null);
        $this->assertEquals('-', $result);
    }

    /**
     * Test get all carriers returns unique values
     */
    public function test_get_all_carriers_returns_unique_values(): void
    {
        $carriers = CarrierService::getAllCarriers();
        
        // Should contain all unique carriers
        $this->assertContains('Correios - Sedex', $carriers);
        $this->assertContains('Correios - PAC', $carriers);
        $this->assertContains('Total Express - Expresso', $carriers);
        $this->assertContains('Shopify Shipping', $carriers);
        $this->assertContains('Retirada em Loja', $carriers);
        
        // Should not contain duplicates (SEDEX and SEDEX CONTRATO AG both map to Correios - Sedex)
        $uniqueCount = count(array_unique($carriers));
        $this->assertEquals($uniqueCount, count($carriers));
    }

    /**
     * Test get carrier mapping returns correct structure
     */
    public function test_get_carrier_mapping_returns_array(): void
    {
        $mapping = CarrierService::getCarrierMapping();
        
        $this->assertIsArray($mapping);
        $this->assertArrayHasKey('SEDEX', $mapping);
        $this->assertArrayHasKey('PAC', $mapping);
        $this->assertArrayHasKey('Expresso', $mapping);
        $this->assertEquals('Correios - Sedex', $mapping['SEDEX']);
    }

    /**
     * Test is mapped returns true for known service types
     */
    public function test_is_mapped_returns_true_for_known_types(): void
    {
        $this->assertTrue(CarrierService::isMapped('SEDEX'));
        $this->assertTrue(CarrierService::isMapped('PAC'));
        $this->assertTrue(CarrierService::isMapped('Expresso'));
    }

    /**
     * Test is mapped returns false for unknown service types
     */
    public function test_is_mapped_returns_false_for_unknown_types(): void
    {
        $this->assertFalse(CarrierService::isMapped('Unknown'));
        $this->assertFalse(CarrierService::isMapped(null));
        $this->assertFalse(CarrierService::isMapped(''));
    }

    /**
     * Test case sensitivity for service types
     */
    public function test_service_type_is_case_sensitive(): void
    {
        // Exact match should work
        $this->assertEquals('Correios - Sedex', CarrierService::getCarrierDisplay('SEDEX'));
        
        // Different case should return dash (case sensitive)
        $this->assertEquals('-', CarrierService::getCarrierDisplay('sedex'));
        $this->assertEquals('-', CarrierService::getCarrierDisplay('Sedex'));
    }

    /**
     * Test real-world raw tracking data from Correios
     */
    public function test_real_world_correios_data(): void
    {
        $rawTrackingData = [
            'id' => 15834775180,
            'servico' => [
                'id' => 14895386668,
                'nome' => 'SEDEX',
                'codigo' => '2'
            ],
            'rastreamento' => [
                'codigo' => 'AB583163060BR',
                'descricao' => 'Objeto postado'
            ]
        ];

        $serviceType = CarrierService::extractServiceType($rawTrackingData);
        $this->assertEquals('SEDEX', $serviceType);
        
        $carrierDisplay = CarrierService::getCarrierDisplayFromRawData($rawTrackingData);
        $this->assertEquals('Correios - Sedex', $carrierDisplay);
    }

    /**
     * Test real-world raw tracking data from Total Express
     */
    public function test_real_world_total_express_data(): void
    {
        $rawTrackingData = [
            'id' => 15834738505,
            'servico' => [
                'id' => 14895762423,
                'nome' => 'Expresso',
                'codigo' => 'EXP'
            ],
            'rastreamento' => [
                'codigo' => 'TXAS746528678tx',
                'descricao' => ''
            ]
        ];

        $serviceType = CarrierService::extractServiceType($rawTrackingData);
        $this->assertEquals('Expresso', $serviceType);
        
        $carrierDisplay = CarrierService::getCarrierDisplayFromRawData($rawTrackingData);
        $this->assertEquals('Total Express - Expresso', $carrierDisplay);
    }
}

