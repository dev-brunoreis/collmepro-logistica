# Melhorias no Sistema de Logs do Bling Sync

## Data: 14 de Outubro de 2025

## Resumo das Alterações

### 1. Logs Diários Separados
- **Antes**: Todos os logs iam para `storage/logs/laravel.log` (arquivo único)
- **Agora**: Logs do Bling sync vão para `storage/logs/bling_sync/bling-sync-YYYY-MM-DD.log` (um arquivo por dia)
- **Retenção**: 30 dias de logs mantidos automaticamente
- **Localização**: `/home/logistica.collmepro.com.br/public_html/storage/logs/bling_sync/`

### 2. Logs Mais Leves e Otimizados

#### Removido dos Logs:
- ❌ Stack traces completos (eram muito pesados)
- ❌ Raw data completo de pedidos e notas fiscais
- ❌ Body completo de respostas de API (limitado a 500 caracteres)
- ❌ Hashes completos (mantido apenas 8 primeiros caracteres)
- ❌ Logs debug desnecessários em produção
- ❌ Contextos duplicados e informações redundantes

#### Mantido nos Logs:
- ✅ Mensagens de erro com localização (arquivo:linha)
- ✅ Sumários de sincronização (processed, created, updated, skipped, errors)
- ✅ Mudanças críticas em pedidos (status, invoice, tracking)
- ✅ Ações importantes (criação, atualização, cancelamento)
- ✅ Warnings e erros essenciais

### 3. Novo Sistema de Logging

#### Trait `BlingSyncLogger`
Criado em `app/Traits/BlingSyncLogger.php` com métodos:
- `logBlingInfo()` - Logs informativos
- `logBlingError()` - Logs de erro (sem trace pesado)
- `logBlingWarning()` - Logs de aviso
- `logBlingDebug()` - Logs de debug (apenas em modo debug)
- `logSyncSummary()` - Sumário de sincronização
- `logOrderAction()` - Ações em pedidos

#### Sanitização Automática
- Remove automaticamente dados pesados do contexto
- Trunca strings longas
- Simplifica arrays de mudanças
- Mantém apenas informações essenciais

### 4. Exemplos de Logs

#### Antes (Pesado - ~2KB por pedido):
```
[2025-10-14 23:00:00] local.INFO: BlingSyncJob: Order processed successfully {"bling_id":123456,"order_id":789,"numero":"12345","action":"updated","changed_fields":["situacao_valor","invoice_number"],"change_types":["status_changed","invoice_added"],"invoice_state":{"old_invoice":null,"new_invoice":"NFe123","has_invoice":true},"tracking_state":{"old_tracking":null,"new_tracking":"ABC123","has_tracking":true},"trace":"#0 vendor/laravel/framework/src/Illuminate/Foundation/Bootstrap/HandleExceptions.php(254): Illuminate\\Foundation\\Bootstrap\\HandleExceptions->handleError(8, 'Undefined variable: order', '/home/logistica.collmepro.com.br/public_html/app/Jobs/BlingSyncJob.php', 617)..."} 
```

#### Agora (Leve - ~300 bytes por pedido):
```
[2025-10-14 23:55:32] production.INFO: Order updated: #12345 {"action":"updated","bling_id":123456,"numero":"12345","changed_fields":"situacao_valor,invoice_number","has_invoice":true,"has_tracking":true} 
```

### 5. Melhorias de Performance

- **Redução de ~85% no tamanho dos logs**
- **Logs debug desabilitados em produção**
- **Menos I/O de disco**
- **Mais fácil de analisar e debugar**
- **Rotação automática de logs (30 dias)**

### 6. Configuração

#### `config/logging.php`
```php
'bling_sync' => [
    'driver' => 'daily',
    'path' => storage_path('logs/bling_sync/bling-sync.log'),
    'level' => env('LOG_LEVEL', 'info'),
    'days' => 30,
    'replace_placeholders' => true,
],
```

### 7. Como Usar

#### No Job ou Service:
```php
use App\Traits\BlingSyncLogger;

class BlingSyncJob implements ShouldQueue
{
    use BlingSyncLogger;
    
    public function handle(): void
    {
        // Logs otimizados
        $this->logBlingInfo('Starting sync', ['account' => 'pro']);
        $this->logBlingError('Sync failed', ['error' => $e->getMessage()]);
        $this->logSyncSummary('pro', ['processed' => 100, 'created' => 10]);
    }
}
```

### 8. Visualizar Logs

#### Logs de Hoje:
```bash
tail -f storage/logs/bling_sync/bling-sync-2025-10-14.log
```

#### Logs de uma Data Específica:
```bash
cat storage/logs/bling_sync/bling-sync-2025-10-10.log
```

#### Últimas 50 Linhas:
```bash
tail -n 50 storage/logs/bling_sync/bling-sync-2025-10-14.log
```

### 9. Estrutura de Arquivos

```
storage/logs/bling_sync/
├── bling-sync-2025-10-14.log
├── bling-sync-2025-10-13.log
├── bling-sync-2025-10-12.log
└── ...
```

### 10. Nota sobre Formato de Data

O Laravel usa o formato internacional `YYYY-MM-DD` para logs diários, que:
- ✅ É melhor para ordenação cronológica
- ✅ É padrão internacional (ISO 8601)
- ✅ Evita ambiguidades
- ✅ Funciona melhor com ferramentas de log

Se precisar do formato brasileiro (DD-MM-YY), seria necessário criar um custom log handler, 
mas isso adiciona complexidade sem benefícios significativos.

## Arquivos Modificados

1. `config/logging.php` - Adicionado canal `bling_sync`
2. `app/Traits/BlingSyncLogger.php` - Novo trait para logs otimizados
3. `app/Jobs/BlingSyncJob.php` - Refatorado para usar novo sistema de logs
4. `storage/logs/bling_sync/` - Novo diretório criado

## Benefícios

1. **Organização**: Logs separados por dia e por funcionalidade
2. **Performance**: Logs 85% mais leves
3. **Manutenibilidade**: Mais fácil de debugar problemas
4. **Espaço em Disco**: Menor uso de espaço
5. **Análise**: Mais fácil de analisar logs específicos
6. **Compliance**: Retenção automática de 30 dias

## Próximos Passos (Opcional)

1. Implementar log aggregation (ex: ELK Stack, Graylog)
2. Adicionar alertas automáticos para erros críticos
3. Dashboard de métricas de sincronização
4. Exportação de logs para análise

