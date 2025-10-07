<?php

namespace App\Services;

class CollmeProBlingService extends BlingService
{
    /**
     * Get the account type for Collme Pro
     */
    protected function getAccountType(): string
    {
        return 'pro';
    }

    /**
     * Get the client ID for Collme Pro from environment
     */
    protected function getClientId(): string
    {
        $clientId = config('services.bling.pro.client_id');
        
        if (!$clientId) {
            throw new \Exception('BLING_PRO_CLIENT_ID not configured in environment');
        }

        return $clientId;
    }

    /**
     * Get the client secret for Collme Pro from environment
     */
    protected function getClientSecret(): string
    {
        $clientSecret = config('services.bling.pro.client_secret');
        
        if (!$clientSecret) {
            throw new \Exception('BLING_PRO_CLIENT_SECRET not configured in environment');
        }

        return $clientSecret;
    }

    /**
     * Override processOrder to handle Collme Pro specific logic
     */
    protected function processOrder(array $orderData): array
    {
        $result = parent::processOrder($orderData);
        
        // Add Collme Pro specific processing here
        // For example:
        // - Different order validation rules
        // - Specific product handling
        // - Custom status mapping
        // - Integration with Collme Pro specific systems
        
        // Example:
        // if (isset($orderData['cliente']['tipoPessoa']) && $orderData['cliente']['tipoPessoa'] === 'J') {
        //     // Handle business customer differently
        //     $this->processBusinessOrder($orderData);
        // }
        
        return $result;
    }
}
