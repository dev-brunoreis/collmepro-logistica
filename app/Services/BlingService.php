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
    private string $bearerToken;
    private int $timeout;
    private array $rateLimit;
    private array $defaults;
    private bool $loggingEnabled;

    public function __construct()
    {
        $this->baseUrl = config('bling.api.base_url');
        $this->timeout = config('bling.api.timeout');
        $this->rateLimit = config('bling.rate_limit');
        $this->defaults = config('bling.defaults');
        $this->loggingEnabled = config('bling.logging.enabled');

        $bearerToken = config('bling.api.bearer_token');
        if (!$bearerToken) {
            throw new Exception('Bling Bearer Token not configured');
        }
        
        $this->bearerToken = $bearerToken;
    }

    /**
     * Busca notas fiscais com filtros opcionais
     */
    public function fetchInvoices(array $filters = []): array
    {
        try {
            $this->logRequest('fetchInvoices', $filters);

            $params = $this->buildInvoiceParams($filters);
            $response = $this->makeRequest('/nfe', $params);

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
     * Formato: numeroLoja = "id_id" onde id é o número do pedido
     */
    public function fetchInvoiceByOrderNumber(string $orderNumber): ?array
    {
        try {
            $this->logRequest('fetchInvoiceByOrderNumber', ['orderNumber' => $orderNumber]);

            // Formato correto: id_id (ex: 8175_8175)
            $numeroLoja = $orderNumber . '_' . $orderNumber;
            
            $filters = [
                'numeroLoja' => $numeroLoja,
                'limite' => 10
            ];

            $this->logInfo('Searching with numeroLoja: ' . $numeroLoja);

            $invoices = $this->fetchInvoices($filters);

            if (empty($invoices)) {
                $this->logInfo('No invoice found for order number: ' . $orderNumber . ' (numeroLoja: ' . $numeroLoja . ')');
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
     * Busca detalhes completos de uma nota fiscal por ID
     */
    public function fetchInvoiceById(string $invoiceId): ?array
    {
        try {
            $this->logRequest('fetchInvoiceById', ['invoiceId' => $invoiceId]);

            $endpoint = "/nfe/{$invoiceId}";
            $response = $this->makeRequest($endpoint);

            if (!$response['success']) {
                $this->logError('Failed to fetch invoice details', [
                    'invoiceId' => $invoiceId,
                    'status_code' => $response['status_code'],
                    'body' => $response['body']
                ]);
                return null;
            }

            $data = json_decode($response['body'], true);
            
            if (isset($data['data'])) {
                $invoice = $data['data'];
                $this->logResponse('fetchInvoiceById', 'Invoice details retrieved: ' . ($invoice['numero'] ?? 'unknown'));
                return $invoice;
            }

            $this->logInfo('Invoice data not found in response', [
                'invoiceId' => $invoiceId,
                'response' => $data
            ]);
            return null;

        } catch (Exception $e) {
            $this->logError('Exception in fetchInvoiceById', [
                'error' => $e->getMessage(),
                'invoiceId' => $invoiceId
            ]);
            return null;
        }
    }

    /**
     * Obtém URL para download do PDF da nota fiscal
     * Agora busca os detalhes completos e extrai o linkPDF
     */
    public function getInvoicePdfUrl(string $invoiceId): ?string
    {
        try {
            $this->logRequest('getInvoicePdfUrl', ['invoiceId' => $invoiceId]);

            // Fallback: tenta buscar por endpoint específico de PDF primeiro
            $endpoint = "/nfe/{$invoiceId}/pdf";
            $response = $this->makeRequest($endpoint);

            if ($response['success']) {
                $data = json_decode($response['body'], true);
                if (isset($data['data']['url'])) {
                    $pdfUrl = $data['data']['url'];
                    $this->logInfo('PDF URL retrieved from PDF endpoint', [
                        'invoiceId' => $invoiceId,
                        'pdfUrl' => $pdfUrl
                    ]);
                    return $pdfUrl;
                }
            }

            // Busca detalhes completos da nota fiscal
            $invoice = $this->fetchInvoiceById($invoiceId);
            
            if (!$invoice) {
                $this->logInfo('Invoice not found for PDF URL', ['invoiceId' => $invoiceId]);
                return null;
            }

            // Extrai linkPDF dos detalhes
            if (isset($invoice['linkPDF']) && !empty($invoice['linkPDF'])) {
                $pdfUrl = $invoice['linkPDF'];
                $this->logInfo('PDF URL retrieved from invoice details', [
                    'invoiceId' => $invoiceId,
                    'pdfUrl' => $pdfUrl
                ]);
                return $pdfUrl;
            }

            $this->logInfo('PDF URL not available', [
                'invoiceId' => $invoiceId,
                'hasLinkPDF' => isset($invoice['linkPDF']),
                'linkPDF' => $invoice['linkPDF'] ?? null
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

        // Status válidos das notas fiscais no Bling:
        // 1=Pendente, 2=Cancelada, 3=Aguardando recibo, 4=Rejeitada, 5=Autorizada, 6=Emitida DANFE
        // 7=Registrada, 8=Aguardando protocolo, 9=Denegada, 10=Consulta situação, 11=Bloqueada
        // Processamos apenas notas autorizadas (5) e emitidas DANFE (6) - status finais válidos
        $validStatuses = [5, 6]; // 5=Autorizada, 6=Emitida DANFE
        if (!in_array($invoice['situacao'], $validStatuses)) {
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
            'type' => $invoice['tipo'] ?? null,
            'contact' => $invoice['contato'] ?? null,
            'store' => $invoice['loja'] ?? null,
            'link_pdf' => $invoice['linkPDF'] ?? null,
            'link_danfe' => $invoice['linkDanfe'] ?? null,
            'valor_nota' => $invoice['valorNota'] ?? null,
            'valor_frete' => $invoice['valorFrete'] ?? null,
            'numero_pedido_loja' => $invoice['numeroPedidoLoja'] ?? null,
            'serie' => $invoice['serie'] ?? null,
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
            'Authorization' => 'Bearer ' . $this->bearerToken,
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
            'pagina' => 1
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
