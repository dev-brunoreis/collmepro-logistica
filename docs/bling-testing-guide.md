# 🧪 Guia de Testes - Integração Bling

## 📋 Como Testar a Integração com Bling

### 1. **Configuração Inicial**

Primeiro, configure seu token no arquivo `.env`:

```env
BLING_BEARER_TOKEN=seu_token_aqui
BLING_BASE_URL=https://bling.com.br/Api/v3
BLING_TIMEOUT=30
BLING_LOGGING_ENABLED=true
```

### 2. **Testes Unitários (Mock)**

Testa a lógica do serviço sem fazer requisições reais:

```bash
php artisan test tests/Unit/Services/BlingServiceTest.php
```

**Resultado esperado**: ✅ 19 testes passando

### 3. **Testes de Integração (Real)**

Testa a conexão real com a API do Bling:

```bash
php artisan test tests/Feature/BlingIntegrationTest.php
```

⚠️ **Requisito**: Token válido configurado no `.env`

### 4. **Comando Artisan para Testes Manuais**

#### **Testar Busca de Faturas**
```bash
# Buscar 5 faturas
php artisan bling:test --action=invoices --limit=5

# Buscar 10 faturas
php artisan bling:test --action=invoices --limit=10
```

#### **Testar Busca por Número do Pedido**
```bash
# Buscar fatura específica
php artisan bling:test --action=order --order-number=123456
```

#### **Testar Busca por Período**
```bash
# Buscar faturas de janeiro 2024
php artisan bling:test --action=date-range --start-date=2024-01-01 --end-date=2024-01-31

# Buscar faturas dos últimos 7 dias
php artisan bling:test --action=date-range --start-date=2024-01-15 --end-date=2024-01-22
```

#### **Testar Busca por Status**
```bash
# Buscar faturas autorizadas (status 5)
php artisan bling:test --action=status --status=5

# Buscar faturas pendentes (status 1)
php artisan bling:test --action=status --status=1
```

#### **Testar Download de PDF**
```bash
# Buscar URL do PDF de uma fatura
php artisan bling:test --action=pdf --order-number=123456
```

### 5. **Testes via Tinker (Console Interativo)**

```bash
php artisan tinker
```

```php
// Criar instância do serviço
$bling = app(\App\Services\BlingService::class);

// Buscar faturas
$invoices = $bling->fetchInvoices(['limite' => 5]);
dd($invoices);

// Buscar fatura por número
$invoice = $bling->fetchInvoiceByOrderNumber('123456');
dd($invoice);

// Buscar por período
$invoices = $bling->fetchInvoicesByDateRange('2024-01-01 00:00:00', '2024-01-31 23:59:59');
dd($invoices);

// Validar dados
$isValid = $bling->isValidInvoice($invoice);
dd($isValid);

// Extrair dados
$data = $bling->extractInvoiceData($invoice);
dd($data);
```

### 6. **Exemplos de Uso em Código**

#### **Controller**
```php
<?php

namespace App\Http\Controllers;

use App\Services\BlingService;
use Illuminate\Http\Request;

class InvoiceController extends Controller
{
    public function __construct(private BlingService $blingService) {}

    public function index(Request $request)
    {
        $filters = [
            'limite' => $request->get('limit', 50),
            'situacao' => 5, // Apenas autorizadas
        ];

        if ($request->has('start_date') && $request->has('end_date')) {
            return $this->blingService->fetchInvoicesByDateRange(
                $request->start_date,
                $request->end_date,
                $filters
            );
        }

        return $this->blingService->fetchInvoices($filters);
    }

    public function show(string $orderNumber)
    {
        $invoice = $this->blingService->fetchInvoiceByOrderNumber($orderNumber);
        
        if (!$invoice) {
            return response()->json(['error' => 'Invoice not found'], 404);
        }

        return response()->json($invoice);
    }

    public function pdf(string $orderNumber)
    {
        $invoice = $this->blingService->fetchInvoiceByOrderNumber($orderNumber);
        
        if (!$invoice) {
            return response()->json(['error' => 'Invoice not found'], 404);
        }

        $pdfUrl = $this->blingService->getInvoicePdfUrl($invoice['id']);
        
        if (!$pdfUrl) {
            return response()->json(['error' => 'PDF not available'], 404);
        }

        return response()->json(['pdf_url' => $pdfUrl]);
    }
}
```

#### **Job para Sincronização**
```php
<?php

namespace App\Jobs;

use App\Services\BlingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SyncBlingInvoicesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        private string $startDate,
        private string $endDate
    ) {}

    public function handle(BlingService $blingService): void
    {
        $invoices = $blingService->fetchInvoicesByDateRange(
            $this->startDate,
            $this->endDate
        );

        foreach ($invoices as $invoice) {
            if ($blingService->isValidInvoice($invoice)) {
                $data = $blingService->extractInvoiceData($invoice);
                
                // Salvar no banco de dados
                // Invoice::updateOrCreate(['bling_id' => $data['bling_id']], $data);
                
                \Log::info('Invoice synced', ['bling_id' => $data['bling_id']]);
            }
        }
    }
}
```

### 7. **Monitoramento e Logs**

#### **Habilitar Logs Detalhados**
```env
BLING_LOGGING_ENABLED=true
BLING_LOG_REQUESTS=true
BLING_LOG_RESPONSES=true
```

#### **Verificar Logs**
```bash
tail -f storage/logs/laravel.log | grep "BlingService"
```

### 8. **Troubleshooting**

#### **Erro: "Bling Bearer Token not configured"**
```bash
# Verificar se o token está configurado
php artisan config:show bling.api.bearer_token
```

#### **Erro: "Unauthorized"**
- Verificar se o token está correto
- Verificar se o token não expirou
- Verificar se o token tem as permissões necessárias

#### **Erro: "Rate limit exceeded"**
- O serviço já implementa rate limiting automático
- Ajustar `BLING_RATE_LIMIT` se necessário

#### **Timeout de Requisição**
```env
BLING_TIMEOUT=60  # Aumentar timeout
```

### 9. **Status da API**

Para verificar o status da API do Bling:
```bash
curl -H "Authorization: Bearer SEU_TOKEN" \
     -H "Accept: application/json" \
     https://bling.com.br/Api/v3/notas-fiscais?limite=1
```

### 10. **Exemplo de Resposta da API**

```json
{
  "data": [
    {
      "id": 12345678,
      "tipo": "S",
      "situacao": 5,
      "numero": "6541",
      "dataEmissao": "2024-01-15 09:52:12",
      "dataOperacao": "2024-01-15 09:52:12",
      "chaveAcesso": "35250145752681000182650010000266371238060760",
      "contato": {
        "id": 12345678,
        "nome": "Cliente Teste",
        "numeroDocumento": "30188025000121",
        "email": "cliente@teste.com.br"
      },
      "loja": {
        "id": 12345678,
        "nome": "Loja Principal"
      }
    }
  ]
}
```

## 🎯 Próximos Passos

1. **Configure seu token** no `.env`
2. **Execute os testes unitários** para validar a implementação
3. **Execute os testes de integração** para validar a conexão real
4. **Use o comando Artisan** para testes manuais
5. **Implemente nos controllers** conforme necessário

---

**💡 Dica**: Sempre teste primeiro com poucos registros (`--limit=5`) para validar a conexão antes de fazer buscas maiores!
