<?php

namespace App\Services;

class CarrierService
{
    /**
     * Carrier mapping array
     * Maps service type to formatted carrier display name
     */
    private const CARRIER_MAPPING = [
        'SEDEX' => 'Correios - Sedex',
        'SEDEX CONTRATO AG' => 'Correios - Sedex',
        'PAC' => 'Correios - PAC',
        'PAC CONTRATO PGTO ENTREGA' => 'Correios - PAC',
        'Expresso' => 'Total Express - Expresso',
        'LSV-Standard-BR PICKUP' => 'Shopify Shipping',
        'Coleta' => 'Retirada em Loja',
    ];

    /**
     * Get formatted carrier display name from service type
     *
     * @param string|null $serviceType
     * @return string
     */
    public static function getCarrierDisplay(?string $serviceType): string
    {
        if (empty($serviceType)) {
            return '-';
        }

        return self::CARRIER_MAPPING[$serviceType] ?? '-';
    }

    /**
     * Extract service type from raw tracking data
     *
     * @param array|null $rawTrackingData
     * @return string|null
     */
    public static function extractServiceType(?array $rawTrackingData): ?string
    {
        if (empty($rawTrackingData)) {
            return null;
        }

        return $rawTrackingData['servico']['nome'] ?? null;
    }

    /**
     * Get carrier display from raw tracking data
     *
     * @param array|null $rawTrackingData
     * @return string
     */
    public static function getCarrierDisplayFromRawData(?array $rawTrackingData): string
    {
        $serviceType = self::extractServiceType($rawTrackingData);
        return self::getCarrierDisplay($serviceType);
    }

    /**
     * Get all available carriers
     *
     * @return array
     */
    public static function getAllCarriers(): array
    {
        return array_values(array_unique(self::CARRIER_MAPPING));
    }

    /**
     * Get carrier mapping
     *
     * @return array
     */
    public static function getCarrierMapping(): array
    {
        return self::CARRIER_MAPPING;
    }

    /**
     * Check if a service type is mapped
     *
     * @param string|null $serviceType
     * @return bool
     */
    public static function isMapped(?string $serviceType): bool
    {
        if (empty($serviceType)) {
            return false;
        }

        return isset(self::CARRIER_MAPPING[$serviceType]);
    }
}

