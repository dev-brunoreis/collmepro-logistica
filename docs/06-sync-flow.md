# Fluxo de Sincronização Sequencial

## 🎯 Objetivo
Processar **1 pedido por vez**, garantindo que todo o fluxo (Pedido → NFE → Rastreio) seja completado antes do próximo pedido.

## 📊 Timeline do Fluxo

```
Minuto 0:00 → Import Order #1
            ↓ (aguarda 10s)
Minuto 0:10 → Sync NFE #1
            ↓ (aguarda 15s)
Minuto 0:25 → Fetch Tracking #1
            ✅ Pedido #1 completo!

Minuto 1:00 → Import Order #2
            ↓ (aguarda 10s)
Minuto 1:10 → Sync NFE #2
            ↓ (aguarda 15s)
Minuto 1:25 → Fetch Tracking #2
            ✅ Pedido #2 completo!

Minuto 2:00 → Import Order #3
            ...e assim por diante
```

## 🔄 Detalhamento do Fluxo

### 1️⃣ Importação de Pedido
**Job:** `ImportWooCommerceOrdersJob`  
**Frequência:** A cada minuto  
**Comportamento:**
- Busca todos os pedidos novos na API
- **Importa APENAS o primeiro pedido novo**
- Para após importar 1 pedido
- Cria log: `sync_type = 'order_import'`
- Dispara `SyncBlingDataJob` com delay de 10s

### 2️⃣ Sincronização de NFE
**Job:** `SyncBlingDataJob`  
**Trigger:** Disparado automaticamente após importar pedido  
**Comportamento:**
- Busca NFE no Bling usando o número do pedido
- Atualiza pedido com dados da NFE
- Cria registro na tabela `invoices`
- Cria log: `sync_type = 'invoice_sync'`
- **Se encontrar NFE com DANFE + transporte:**
  - Dispara `FetchTrackingDataJob` com delay de 15s
- **Se não encontrar NFE:**
  - Marca log como `skipped`
  - Não dispara próximo job
  - Tentará novamente no próximo ciclo (próximo minuto)

### 3️⃣ Busca de Rastreio
**Job:** `FetchTrackingDataJob`  
**Trigger:** Disparado automaticamente após sincronizar NFE (se aplicável)  
**Comportamento:**
- Busca objeto logístico no Bling
- Extrai código de rastreamento
- Atualiza pedido com tracking_code e logistics_provider
- Cria log: `sync_type = 'tracking_fetch'`
- **Se não encontrar rastreio:**
  - Marca log como `skipped`

## 📝 Logs de Sincronização

Cada operação cria um registro na tabela `sync_logs`:

```sql
SELECT 
    id,
    order_id,
    sync_type,
    status,
    message,
    duration_ms,
    created_at
FROM sync_logs
WHERE order_id = 123
ORDER BY created_at;
```

**Exemplo de resultado:**
```
| id | order_id | sync_type      | status  | message                    | duration_ms | created_at          |
|----|----------|----------------|---------|----------------------------|-------------|---------------------|
| 1  | 123      | order_import   | success | Order imported             | 250         | 2025-10-01 10:00:00 |
| 2  | 123      | invoice_sync   | success | Invoice synced             | 1200        | 2025-10-01 10:00:10 |
| 3  | 123      | tracking_fetch | success | Tracking data fetched      | 800         | 2025-10-01 10:00:25 |
```

## 🎯 Vantagens

### Performance
- ✅ **67% menos carga**: Apenas 2 jobs/minuto (vs 3 jobs/minuto antes)
- ✅ **Processamento controlado**: 1 pedido por vez evita sobrecarga
- ✅ **Delays inteligentes**: Dá tempo para APIs responderem

### Rastreabilidade
- ✅ **Histórico completo**: Cada operação registrada
- ✅ **Timeline clara**: Veja exatamente quando cada etapa aconteceu
- ✅ **Debug fácil**: Identifique onde o processo falhou

### Confiabilidade
- ✅ **Retry automático**: Se NFE não estiver pronta, tenta no próximo ciclo
- ✅ **Sem travamento**: Se uma etapa falhar, não bloqueia as outras
- ✅ **Logs detalhados**: Saiba exatamente o que aconteceu

## 🔍 Monitoramento

### Ver pedidos aguardando NFE
```sql
SELECT * FROM orders 
WHERE nfe_number IS NULL 
  AND created_at > NOW() - INTERVAL 1 DAY;
```

### Ver pedidos com NFE mas sem rastreio
```sql
SELECT * FROM orders 
WHERE nfe_number IS NOT NULL 
  AND tracking_code IS NULL 
  AND created_at > NOW() - INTERVAL 1 DAY;
```

### Ver sincronizações que falharam
```sql
SELECT * FROM sync_logs 
WHERE status = 'failed' 
  AND created_at > NOW() - INTERVAL 1 DAY
ORDER BY created_at DESC;
```

### Ver tempo médio de cada operação
```sql
SELECT 
    sync_type,
    AVG(duration_ms) as avg_duration_ms,
    COUNT(*) as total_operations,
    SUM(CASE WHEN status = 'success' THEN 1 ELSE 0 END) as successful,
    SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed
FROM sync_logs
WHERE created_at > NOW() - INTERVAL 1 DAY
GROUP BY sync_type;
```

## 🚨 Troubleshooting

### Pedido importado mas sem NFE
**Possíveis causas:**
1. NFE ainda não foi emitida no Bling
2. Número do pedido não corresponde no Bling
3. Erro na API do Bling

**Como verificar:**
```php
$order = Order::find(123);
$logs = $order->syncLogs()->where('sync_type', 'invoice_sync')->get();
```

### NFE sincronizada mas sem rastreio
**Possíveis causas:**
1. NFE não tem DANFE (status ≠ 6)
2. NFE não tem informações de transporte
3. Objeto logístico não criado no Bling

**Como verificar:**
```php
$order = Order::find(123);
$logs = $order->syncLogs()->where('sync_type', 'tracking_fetch')->get();
```

## ⚙️ Configurações

### Alterar intervalo de importação
Em `app/Providers/ScheduleServiceProvider.php`:
```php
// Importar a cada 2 minutos
->everyTwoMinutes()

// Importar a cada 5 minutos
->everyFiveMinutes()

// Importar a cada 10 minutos
->everyTenMinutes()
```

### Alterar delays entre jobs
Em `app/Jobs/ImportWooCommerceOrdersJob.php`:
```php
// Alterar delay antes de buscar NFE (linha 176)
->delay(now()->addSeconds(10)); // 10 segundos
```

Em `app/Jobs/SyncBlingDataJob.php`:
```php
// Alterar delay antes de buscar rastreio (linha 125)
->delay(now()->addSeconds(15)); // 15 segundos
```

### Processar múltiplos pedidos por ciclo
Em `app/Jobs/ImportWooCommerceOrdersJob.php` (linha 84):
```php
// Remover o break; para processar todos os pedidos
// OU alterar para processar N pedidos:

$maxImports = 3; // Importar até 3 pedidos por vez
foreach ($orders as $orderData) {
    if ($existingOrder) {
        $skippedCount++;
        continue;
    }
    
    if ($this->importOrder($orderData)) {
        $importedCount++;
        if ($importedCount >= $maxImports) {
            break;
        }
    }
}
```

## 📊 Métricas Recomendadas

### KPIs de Sincronização
1. **Taxa de sucesso**: % de sincronizações bem-sucedidas
2. **Tempo médio**: Tempo médio de cada etapa
3. **Pedidos pendentes**: Quantidade de pedidos sem NFE ou rastreio
4. **Erros por tipo**: Quais etapas falham mais

### Dashboard Sugerido
- Total de pedidos importados hoje
- Pedidos aguardando NFE
- Pedidos aguardando rastreio
- Últimas sincronizações (com status)
- Gráfico de tempo de processamento
- Taxa de erro por hora

