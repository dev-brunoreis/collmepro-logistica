<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

/**
 * BlingLogisticsService - Service for Bling Logistics API
 * 
 * Handles tracking information and logistics data from Bling
 */
class BlingLogisticsService
{
    private string $baseUrl;
    private string $bearerToken;
    private int $timeout;
    private int $rateLimit;

    public function __construct()
    {
        $this->baseUrl = config('bling.api.base_url');
        $this->bearerToken = config('bling.api.bearer_token');
        $this->timeout = config('bling.api.timeout', 30);
        $this->rateLimit = config('bling.api.rate_limit', 1000);

        if (empty($this->bearerToken)) {
            throw new Exception('Bling bearer token not configured');
        }
    }

    /**
     * Busca pacotes por código de rastreamento
     */
    public function fetchPackages(array $filters = []): array
    {
        try {
            Log::info('BlingLogisticsService: fetchPackages', $filters);

            $params = array_merge([
                'limite' => 50,
                'pagina' => 1
            ], $filters);

            $response = $this->makeRequest('/logisticas/pacotes', $params);

            if (!$response['success']) {
                Log::error('Failed to fetch packages from Bling', [
                    'status' => $response['status_code'],
                    'response' => $response['body']
                ]);
                return [];
            }

            $data = json_decode($response['body'], true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                Log::error('Invalid JSON response from Bling logistics', [
                    'error' => json_last_error_msg(),
                    'response' => $response['body']
                ]);
                return [];
            }

            $packages = $data['data'] ?? [];
            Log::info('BlingLogisticsService: fetchPackages - ' . count($packages) . ' packages found');

            return $packages;

        } catch (Exception $e) {
            Log::error('Exception in fetchPackages', [
                'error' => $e->getMessage(),
                'filters' => $filters
            ]);
            return [];
        }
    }

    /**
     * Busca informações de rastreamento por código
     */
    public function getTrackingInfo(string $trackingCode): ?array
    {
        try {
            Log::info('BlingLogisticsService: getTrackingInfo', ['trackingCode' => $trackingCode]);

            // Primeiro busca o pacote pelo código de rastreamento
            $packages = $this->fetchPackages([
                'codigoRastreamento' => $trackingCode,
                'limite' => 1
            ]);

            if (empty($packages)) {
                Log::info('No package found for tracking code', ['trackingCode' => $trackingCode]);
                return null;
            }

            $package = $packages[0];
            $packageId = $package['id'];

            // Busca informações detalhadas do pacote
            $packageDetails = $this->fetchPackageDetails($packageId);
            
            if (!$packageDetails) {
                return null;
            }

            // Extrai informações relevantes
            $trackingInfo = [
                'tracking_code' => $trackingCode,
                'situacao' => $packageDetails['situacao'] ?? 'unknown',
                'transportadora' => $packageDetails['transportadora'] ?? null,
                'dataAtualizacao' => $packageDetails['dataAtualizacao'] ?? null,
                'dataPrevistaEntrega' => $packageDetails['dataPrevistaEntrega'] ?? null,
                'destinatario' => $packageDetails['destinatario'] ?? null,
                'origem' => $packageDetails['origem'] ?? null,
                'destino' => $packageDetails['destino'] ?? null,
                'peso' => $packageDetails['peso'] ?? null,
                'dimensoes' => $packageDetails['dimensoes'] ?? null,
                'raw_data' => $packageDetails
            ];

            Log::info('BlingLogisticsService: getTrackingInfo - Tracking info retrieved', [
                'trackingCode' => $trackingCode,
                'status' => $trackingInfo['situacao'],
                'carrier' => $trackingInfo['transportadora']
            ]);

            return $trackingInfo;

        } catch (Exception $e) {
            Log::error('Exception in getTrackingInfo', [
                'error' => $e->getMessage(),
                'trackingCode' => $trackingCode
            ]);
            return null;
        }
    }

    /**
     * Busca detalhes de um pacote específico
     */
    public function fetchPackageDetails(string $packageId): ?array
    {
        try {
            Log::info('BlingLogisticsService: fetchPackageDetails', ['packageId' => $packageId]);

            $endpoint = "/logisticas/pacotes/{$packageId}";
            $response = $this->makeRequest($endpoint);

            if (!$response['success']) {
                Log::error('Failed to fetch package details', [
                    'packageId' => $packageId,
                    'status_code' => $response['status_code'],
                    'body' => $response['body']
                ]);
                return null;
            }

            $data = json_decode($response['body'], true);
            
            if (isset($data['data'])) {
                $package = $data['data'];
                Log::info('BlingLogisticsService: fetchPackageDetails - Package details retrieved', [
                    'packageId' => $packageId,
                    'status' => $package['situacao'] ?? 'unknown'
                ]);
                return $package;
            }

            Log::info('Package data not found in response', [
                'packageId' => $packageId,
                'response' => $data
            ]);
            return null;

        } catch (Exception $e) {
            Log::error('Exception in fetchPackageDetails', [
                'error' => $e->getMessage(),
                'packageId' => $packageId
            ]);
            return null;
        }
    }

    /**
     * Busca histórico de rastreamento de um pacote
     */
    public function getTrackingHistory(string $packageId): array
    {
        try {
            Log::info('BlingLogisticsService: getTrackingHistory', ['packageId' => $packageId]);

            $endpoint = "/logisticas/pacotes/{$packageId}/rastreamento";
            $response = $this->makeRequest($endpoint);

            if (!$response['success']) {
                Log::error('Failed to fetch tracking history', [
                    'packageId' => $packageId,
                    'status_code' => $response['status_code'],
                    'body' => $response['body']
                ]);
                return [];
            }

            $data = json_decode($response['body'], true);
            
            $history = $data['data'] ?? [];
            Log::info('BlingLogisticsService: getTrackingHistory - ' . count($history) . ' events found');

            return $history;

        } catch (Exception $e) {
            Log::error('Exception in getTrackingHistory', [
                'error' => $e->getMessage(),
                'packageId' => $packageId
            ]);
            return [];
        }
    }

    /**
     * Busca informações de rastreamento por número do pedido
     * Usado quando a nota tem DANFE para encontrar o código de rastreamento
     */
    public function getTrackingByOrderNumber(string $orderNumber): ?array
    {
        try {
            Log::info('BlingLogisticsService: getTrackingByOrderNumber', ['orderNumber' => $orderNumber]);

            // Busca pacotes relacionados ao pedido
            $packages = $this->fetchPackages([
                'numeroPedido' => $orderNumber,
                'limite' => 10
            ]);

            if (empty($packages)) {
                Log::info('No packages found for order number', ['orderNumber' => $orderNumber]);
                return null;
            }

            // Retorna informações do primeiro pacote encontrado
            $package = $packages[0];
            
            $trackingInfo = [
                'tracking_code' => $package['codigoRastreamento'] ?? null,
                'situacao' => $package['situacao'] ?? 'unknown',
                'transportadora' => $package['transportadora'] ?? null,
                'dataAtualizacao' => $package['dataAtualizacao'] ?? null,
                'dataPrevistaEntrega' => $package['dataPrevistaEntrega'] ?? null,
                'package_id' => $package['id'],
                'raw_data' => $package
            ];

            Log::info('BlingLogisticsService: getTrackingByOrderNumber - Tracking found', [
                'orderNumber' => $orderNumber,
                'trackingCode' => $trackingInfo['tracking_code'],
                'status' => $trackingInfo['situacao']
            ]);

            return $trackingInfo;

        } catch (Exception $e) {
            Log::error('Exception in getTrackingByOrderNumber', [
                'error' => $e->getMessage(),
                'orderNumber' => $orderNumber
            ]);
            return null;
        }
    }

    /**
     * Faz requisição HTTP para a API do Bling
     */
    private function makeRequest(string $endpoint, array $params = []): array
    {
        $this->enforceRateLimit();

        $url = $this->baseUrl . $endpoint;
        
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->bearerToken,
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ])
            ->timeout($this->timeout)
            ->get($url, $params);

            return [
                'success' => $response->successful(),
                'status_code' => $response->status(),
                'body' => $response->body(),
                'headers' => $response->headers()
            ];

        } catch (Exception $e) {
            Log::error('HTTP request failed', [
                'url' => $url,
                'params' => $params,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'status_code' => 0,
                'body' => $e->getMessage(),
                'headers' => []
            ];
        }
    }

    /**
     * Enforce rate limiting
     */
    private function enforceRateLimit(): void
    {
        // Simple rate limiting - sleep for a short time
        usleep(100000); // 0.1 second delay
    }
}
