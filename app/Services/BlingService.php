<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Exception;

/**
 * BlingService - Integration with Bling API v3
 * 
 * @see https://developer.bling.com.br/build/assets/openapi-Dyv70kbC.json
 */
class BlingService
{
    private string $baseUrl;
    private string $apiKey;
    private int $timeout;
    private array $rateLimit;
    private array $defaults;
    private bool $loggingEnabled;

    public function __construct()
    {
        $this->baseUrl = config('bling.api.base_url');
        $this->apiKey = config('bling.api.api_key');
        $this->timeout = config('bling.api.timeout');
        $this->rateLimit = config('bling.rate_limit');
        $this->defaults = config('bling.defaults');
        $this->loggingEnabled = config('bling.logging.enabled');

        if (!$this->apiKey) {
            throw new Exception('Bling API key not configured');
        }
    }

    /**
     * Busca notas fiscais com filtros opcionais
     */
    public function fetchInvoices(array $filters = []): array
    {
        try {
            $this->logRequest('fetchInvoices', $filters);

            $params = $this->buildInvoiceParams($filters);
            $response = $this->makeRequest('/notas-fiscais', $params);

            if (!$response['success']) {
                $this->logError('Failed to fetch invoices', [
                    'status' => $response['status_code'],
                    'response' => $response['body']
                ]);
                return [];
            }

            $data = json_decode($response['body'], true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->logError('Invalid JSON response', [
                    'error' => json_last_error_msg(),
                    'response' => $response['body']
                ]);
                return [];
            }

            $invoices = $data['data'] ?? [];
            $this->logResponse('fetchInvoices', count($invoices) . ' invoices found');

            return $invoices;

        } catch (Exception $e) {
            $this->logError('Exception in fetchInvoices', [
                'error' => $e->getMessage(),
                'filters' => $filters
            ]);
            return [];
        }
    }

    /**
     * Busca nota fiscal por número do pedido na loja
     */
    public function fetchInvoiceByOrderNumber(string $orderNumber): ?array
    {
        try {
            $this->logRequest('fetchInvoiceByOrderNumber', ['orderNumber' => $orderNumber]);

            $filters = [
                'numero' => $orderNumber,
                'limite' => 10
            ];

            $invoices = $this->fetchInvoices($filters);

            if (empty($invoices)) {
                $this->logInfo('No invoice found for order number: ' . $orderNumber);
                return null;
            }

            // Retorna a primeira nota encontrada (deve ser única por número do pedido)
            $invoice = $invoices[0];
            $this->logResponse('fetchInvoiceByOrderNumber', 'Invoice found: ' . $invoice['numero']);

            return $invoice;

        } catch (Exception $e) {
            $this->logError('Exception in fetchInvoiceByOrderNumber', [
                'error' => $e->getMessage(),
                'orderNumber' => $orderNumber
            ]);
            return null;
        }
    }

    /**
     * Busca notas fiscais por período
     */
    public function fetchInvoicesByDateRange(string $startDate, string $endDate, array $additionalFilters = []): array
    {
        try {
            $this->logRequest('fetchInvoicesByDateRange', [
                'startDate' => $startDate,
                'endDate' => $endDate,
                'additionalFilters' => $additionalFilters
            ]);

            $filters = array_merge([
                'dataEmissaoInicial' => $startDate,
                'dataEmissaoFinal' => $endDate,
                'limite' => $this->defaults['limit_per_page']
            ], $additionalFilters);

            $invoices = $this->fetchInvoices($filters);
            $this->logResponse('fetchInvoicesByDateRange', count($invoices) . ' invoices found in date range');

            return $invoices;

        } catch (Exception $e) {
            $this->logError('Exception in fetchInvoicesByDateRange', [
                'error' => $e->getMessage(),
                'startDate' => $startDate,
                'endDate' => $endDate
            ]);
            return [];
        }
    }

    /**
     * Busca notas fiscais por situação
     */
    public function fetchInvoicesByStatus(int $status, array $additionalFilters = []): array
    {
        try {
            $this->logRequest('fetchInvoicesByStatus', [
                'status' => $status,
                'additionalFilters' => $additionalFilters
            ]);

            $filters = array_merge([
                'situacao' => $status,
                'limite' => $this->defaults['limit_per_page']
            ], $additionalFilters);

            $invoices = $this->fetchInvoices($filters);
            $this->logResponse('fetchInvoicesByStatus', count($invoices) . ' invoices found with status: ' . $status);

            return $invoices;

        } catch (Exception $e) {
            $this->logError('Exception in fetchInvoicesByStatus', [
                'error' => $e->getMessage(),
                'status' => $status
            ]);
            return [];
        }
    }

    /**
     * Busca todas as notas fiscais autorizadas (situação 5)
     */
    public function fetchAuthorizedInvoices(array $additionalFilters = []): array
    {
        return $this->fetchInvoicesByStatus($this->defaults['invoice_status'], $additionalFilters);
    }

    /**
     * Busca múltiplas páginas de notas fiscais
     */
    public function fetchAllInvoices(array $filters = [], int $maxPages = null): array
    {
        try {
            $maxPages = $maxPages ?? $this->defaults['max_pages'];
            $allInvoices = [];
            $page = 1;

            do {
                $pageFilters = array_merge($filters, [
                    'pagina' => $page,
                    'limite' => $this->defaults['limit_per_page']
                ]);

                $invoices = $this->fetchInvoices($pageFilters);
                
                if (empty($invoices)) {
                    break;
                }

                $allInvoices = array_merge($allInvoices, $invoices);
                $page++;

                // Rate limiting: pausa entre páginas
                $this->enforceRateLimit();

            } while (count($invoices) === $this->defaults['limit_per_page'] && $page <= $maxPages);

            $this->logInfo('fetchAllInvoices completed', [
                'total_invoices' => count($allInvoices),
                'pages_fetched' => $page - 1
            ]);

            return $allInvoices;

        } catch (Exception $e) {
            $this->logError('Exception in fetchAllInvoices', [
                'error' => $e->getMessage(),
                'filters' => $filters
            ]);
            return [];
        }
    }

    /**
     * Obtém URL para download do PDF da nota fiscal
     */
    public function getInvoicePdfUrl(string $invoiceId): ?string
    {
        try {
            $this->logRequest('getInvoicePdfUrl', ['invoiceId' => $invoiceId]);

            $endpoint = "/notas-fiscais/{$invoiceId}/pdf";
            $response = $this->makeRequest($endpoint);

            if (!$response['success']) {
                $this->logError('Failed to get PDF URL from Bling API', [
                    'invoiceId' => $invoiceId,
                    'status_code' => $response['status_code'],
                    'body' => $response['body']
                ]);
                return null;
            }

            $data = json_decode($response['body'], true);
            
            if (isset($data['data']['url'])) {
                $pdfUrl = $data['data']['url'];
                $this->logInfo('PDF URL retrieved successfully', [
                    'invoiceId' => $invoiceId,
                    'pdfUrl' => $pdfUrl
                ]);
                return $pdfUrl;
            }

            $this->logInfo('PDF URL not found in response', [
                'invoiceId' => $invoiceId,
                'response' => $data
            ]);
            return null;

        } catch (Exception $e) {
            $this->logError('Exception in getInvoicePdfUrl', [
                'error' => $e->getMessage(),
                'invoiceId' => $invoiceId
            ]);
            return null;
        }
    }

    /**
     * Valida se uma nota fiscal é válida para sincronização
     */
    public function isValidInvoice(array $invoice): bool
    {
        $requiredFields = ['id', 'numero', 'situacao', 'dataEmissao'];
        
        foreach ($requiredFields as $field) {
            if (!isset($invoice[$field]) || empty($invoice[$field])) {
                return false;
            }
        }

        // Só processa notas autorizadas (situação 5)
        if ($invoice['situacao'] !== $this->defaults['invoice_status']) {
            return false;
        }

        return true;
    }

    /**
     * Extrai dados relevantes da nota fiscal para o sistema
     */
    public function extractInvoiceData(array $invoice): array
    {
        return [
            'bling_id' => $invoice['id'],
            'invoice_number' => $invoice['numero'],
            'access_key' => $invoice['chaveAcesso'] ?? null,
            'issue_date' => $invoice['dataEmissao'],
            'operation_date' => $invoice['dataOperacao'] ?? $invoice['dataEmissao'],
            'status' => $invoice['situacao'],
            'type' => $invoice['tipo'],
            'contact' => $invoice['contato'] ?? null,
            'store' => $invoice['loja'] ?? null,
            'raw_data' => $invoice
        ];
    }

    /**
     * Faz requisição HTTP para a API do Bling
     */
    private function makeRequest(string $endpoint, array $params = []): array
    {
        $this->enforceRateLimit();

        $url = $this->baseUrl . $endpoint;
        if (!empty($params)) {
            $url .= '?' . http_build_query($params);
        }

        $headers = [
            'Authorization' => 'Bearer ' . $this->apiKey,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'User-Agent' => 'Collmepro-Logistics/1.0'
        ];

        if (config('bling.logging.log_requests')) {
            $this->logInfo('Making request to Bling API', [
                'url' => $url,
                'params' => $params
            ]);
        }

        $response = Http::timeout($this->timeout)
            ->withHeaders($headers)
            ->get($url);

        $result = [
            'status_code' => $response->status(),
            'body' => $response->body(),
            'success' => $response->successful()
        ];

        if (config('bling.logging.log_responses')) {
            $this->logInfo('Bling API response', [
                'status' => $result['status_code'],
                'body' => $result['body']
            ]);
        }

        return $result;
    }

    /**
     * Aplica rate limiting entre requisições
     */
    private function enforceRateLimit(): void
    {
        $cacheKey = 'bling_rate_limit_last_request';
        $lastRequest = Cache::get($cacheKey, 0);
        $currentTime = microtime(true);
        
        $timeSinceLastRequest = $currentTime - $lastRequest;
        $minInterval = 1 / $this->rateLimit['requests_per_second'];
        
        if ($timeSinceLastRequest < $minInterval) {
            $sleepTime = $minInterval - $timeSinceLastRequest;
            usleep($sleepTime * 1000000); // Converte para microsegundos
        }
        
        Cache::put($cacheKey, microtime(true), 1); // Cache por 1 segundo
    }

    /**
     * Build parameters for invoice search
     */
    private function buildInvoiceParams(array $filters): array
    {
        $params = array_merge([
            'limite' => $this->defaults['limit_per_page'],
            'situacao' => $this->defaults['invoice_status'],
            'tipo' => $this->defaults['invoice_type']
        ], $filters);

        // Remove parâmetros vazios
        return array_filter($params, function($value) {
            return $value !== null && $value !== '';
        });
    }

    /**
     * Log de requisições
     */
    private function logRequest(string $method, array $data): void
    {
        if (!$this->loggingEnabled) return;
        
        Log::info("BlingService: $method", $data);
    }

    /**
     * Log de respostas
     */
    private function logResponse(string $method, string $message): void
    {
        if (!$this->loggingEnabled) return;
        
        Log::info("BlingService: $method - $message");
    }

    /**
     * Log de informações
     */
    private function logInfo(string $message, array $context = []): void
    {
        if (!$this->loggingEnabled) return;
        
        Log::info("BlingService: $message", $context);
    }

    /**
     * Log de erros
     */
    private function logError(string $message, array $context = []): void
    {
        Log::error("BlingService: $message", $context);
    }
}
