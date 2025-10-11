<?php

namespace App\Services;

use Exception;

class CollmeAtacadistaBlingLabelService extends CollmeProBlingLabelService
{
    /**
     * Get the account type for Collme Atacadista
     */
    protected function getAccountType(): string
    {
        return 'atacadista';
    }

    /**
     * Get the client ID for Collme Atacadista from environment
     */
    protected function getClientId(): string
    {
        $clientId = config('services.bling.atacadista.client_id');
        
        if (!$clientId) {
            throw new Exception('BLING_ATACADISTA_CLIENT_ID not configured in environment');
        }

        return $clientId;
    }

    /**
     * Get the client secret for Collme Atacadista from environment
     */
    protected function getClientSecret(): string
    {
        $clientSecret = config('services.bling.atacadista.client_secret');
        
        if (!$clientSecret) {
            throw new Exception('BLING_ATACADISTA_CLIENT_SECRET not configured in environment');
        }

        return $clientSecret;
    }
}

