<?php

// Configurações da API do Bling
$blingConfig = [
    'base_url' => 'https://api.bling.com.br/Api/v3',
    'api_key' => '',
    'timeout' => 30,
    'rate_limit' => [
        'requests_per_second' => 3,
        'requests_per_day' => 120000
    ]
];

/**
 * Classe para testar integração com Bling
 */
class BlingIntegrationTest
{
    private $config;
    private $requestCount = 0;
    private $lastRequestTime = 0;

    public function __construct($config)
    {
        $this->config = $config;
    }

    /**
     * Faz requisição para API do Bling com rate limiting
     */
    private function makeRequest($endpoint, $params = [])
    {
        // Rate limiting: máximo 3 requisições por segundo
        $currentTime = microtime(true);
        $timeSinceLastRequest = $currentTime - $this->lastRequestTime;
        
        if ($timeSinceLastRequest < (1 / $this->config['rate_limit']['requests_per_second'])) {
            $sleepTime = (1 / $this->config['rate_limit']['requests_per_second']) - $timeSinceLastRequest;
            usleep($sleepTime * 1000000); // Converte para microsegundos
        }

        $url = $this->config['base_url'] . $endpoint;
        if (!empty($params)) {
            $url .= '?' . http_build_query($params);
        }

        $headers = [
            'Authorization: Bearer ' . $this->config['api_key'],
            'Content-Type: application/json',
            'Accept: application/json'
        ];

        echo "🔍 Fazendo requisição para: $url\n";
        echo "📋 Parâmetros: " . json_encode($params, JSON_PRETTY_PRINT) . "\n";

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->config['timeout'],
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_USERAGENT => 'Collmepro-Logistics/1.0'
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        $this->requestCount++;
        $this->lastRequestTime = microtime(true);

        if ($error) {
            throw new Exception("Erro cURL: $error");
        }

        echo "📊 Status HTTP: $httpCode\n";
        echo "📈 Requisições feitas: {$this->requestCount}\n\n";

        return [
            'status_code' => $httpCode,
            'body' => $response,
            'success' => $httpCode >= 200 && $httpCode < 300
        ];
    }

    /**
     * Testa busca de notas fiscais
     */
    public function testFetchInvoices($params = [])
    {
        echo "🧪 TESTE: Buscar Notas Fiscais\n";
        echo str_repeat("=", 50) . "\n";

        try {
            $response = $this->makeRequest('/nfe', $params);
            
            if (!$response['success']) {
                echo "❌ Erro na requisição:\n";
                echo "Status: {$response['status_code']}\n";
                echo "Resposta: {$response['body']}\n";
                return false;
            }

            $data = json_decode($response['body'], true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                echo "❌ Erro ao decodificar JSON: " . json_last_error_msg() . "\n";
                echo "Resposta bruta: {$response['body']}\n";
                return false;
            }

            echo "✅ Sucesso! Dados recebidos:\n";
            echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";

            // Analisa a estrutura dos dados
            if (isset($data['data']) && is_array($data['data'])) {
                $invoiceCount = count($data['data']);
                echo "\n📊 Análise dos dados:\n";
                echo "- Total de notas encontradas: $invoiceCount\n";
                
                if ($invoiceCount > 0) {
                    $firstInvoice = $data['data'][0];
                    echo "- Campos disponíveis na primeira nota:\n";
                    foreach (array_keys($firstInvoice) as $field) {
                        echo "  • $field\n";
                    }
                }
            }

            return true;

        } catch (Exception $e) {
            echo "❌ Erro no teste: " . $e->getMessage() . "\n";
            return false;
        }
    }

    /**
     * Testa busca de nota fiscal por número da loja
     */
    public function testFetchInvoiceByOrderNumber($orderNumber)
    {
        echo "🧪 TESTE: Buscar NFE por Número do Pedido\n";
        echo str_repeat("=", 50) . "\n";

        $params = [
            'numeroLoja' => $orderNumber,
            'limite' => 10
        ];

        return $this->testFetchInvoices($params);
    }

    /**
     * Testa busca de notas fiscais por período
     */
    public function testFetchInvoicesByDateRange($startDate, $endDate)
    {
        echo "🧪 TESTE: Buscar NFE por Período\n";
        echo str_repeat("=", 50) . "\n";

        $params = [
            'dataEmissaoInicial' => $startDate,
            'dataEmissaoFinal' => $endDate,
            'limite' => 20
        ];

        return $this->testFetchInvoices($params);
    }

    /**
     * Testa busca de notas fiscais por situação
     */
    public function testFetchInvoicesByStatus($status = 5) // 5 = Autorizada
    {
        echo "🧪 TESTE: Buscar NFE por Situação\n";
        echo str_repeat("=", 50) . "\n";

        $params = [
            'situacao' => $status,
            'limite' => 10
        ];

        return $this->testFetchInvoices($params);
    }

    /**
     * Executa todos os testes
     */
    public function runAllTests()
    {
        echo "🚀 INICIANDO TESTES DE INTEGRAÇÃO COM BLING\n";
        echo str_repeat("=", 60) . "\n\n";

        $tests = [
            'Buscar todas as notas (últimas 10)' => function() {
                return $this->testFetchInvoices(['limite' => 10]);
            },
            'Buscar notas autorizadas' => function() {
                return $this->testFetchInvoicesByStatus(5);
            },
            'Buscar notas por período (últimos 30 dias)' => function() {
                $endDate = date('Y-m-d H:i:s');
                $startDate = date('Y-m-d H:i:s', strtotime('-30 days'));
                return $this->testFetchInvoicesByDateRange($startDate, $endDate);
            },
            'Buscar nota por número do pedido (exemplo)' => function() {
                return $this->testFetchInvoiceByOrderNumber('12345678');
            }
        ];

        $results = [];
        foreach ($tests as $testName => $testFunction) {
            echo "🔬 Executando: $testName\n";
            echo str_repeat("-", 40) . "\n";
            
            $result = $testFunction();
            $results[$testName] = $result;
            
            echo "\n" . str_repeat("-", 40) . "\n\n";
            
            // Pausa entre testes para respeitar rate limiting
            sleep(1);
        }

        // Resumo dos resultados
        echo "📋 RESUMO DOS TESTES\n";
        echo str_repeat("=", 30) . "\n";
        foreach ($results as $testName => $result) {
            $status = $result ? "✅ PASSOU" : "❌ FALHOU";
            echo "$testName: $status\n";
        }

        $passedTests = array_sum($results);
        $totalTests = count($results);
        echo "\n📊 Resultado final: $passedTests/$totalTests testes passaram\n";

        return $passedTests === $totalTests;
    }
}

// Execução do script
echo "🔧 CONFIGURAÇÃO\n";
echo str_repeat("=", 20) . "\n";
echo "Base URL: {$blingConfig['base_url']}\n";
echo "API Key: " . (empty($blingConfig['api_key']) || $blingConfig['api_key'] === 'YOUR_API_KEY_HERE' ? "❌ NÃO CONFIGURADA" : "✅ Configurada") . "\n";
echo "Rate Limit: {$blingConfig['rate_limit']['requests_per_second']} req/s, {$blingConfig['rate_limit']['requests_per_day']} req/dia\n\n";

if (empty($blingConfig['api_key']) || $blingConfig['api_key'] === 'YOUR_API_KEY_HERE') {
    echo "❌ ERRO: Configure sua API Key do Bling no script antes de executar!\n";
    echo "Edite a variável \$blingConfig['api_key'] com sua chave real.\n";
    exit(1);
}

try {
    $tester = new BlingIntegrationTest($blingConfig);
    $success = $tester->runAllTests();
    
    if ($success) {
        echo "\n🎉 TODOS OS TESTES PASSARAM! A integração está funcionando.\n";
        echo "✅ Pronto para implementar o BlingService no Laravel.\n";
    } else {
        echo "\n⚠️  ALGUNS TESTES FALHARAM. Verifique a configuração e tente novamente.\n";
    }
    
} catch (Exception $e) {
    echo "\n💥 ERRO FATAL: " . $e->getMessage() . "\n";
    echo "Verifique sua conexão com a internet e as credenciais da API.\n";
}
