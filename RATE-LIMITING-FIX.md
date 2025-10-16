# Rate Limiting Fix - Múltiplos Workers

## 🚨 Problema Identificado

### Situação Anterior
```
❌ PROBLEMA: Rate limiting POR WORKER (não global)

Workers simultâneos fazendo requisições ao Bling:
1. BlingSyncJob (sync principal de pedidos)
2. SyncInvoiceJob (worker: invoices)
3. ExtractTrackingJob (worker: tracking)
4. SyncSellerJob (worker: sellers) ← usa WooCommerce, não Bling

Total de workers no Bling: 3 workers simultâneos

Rate limit configurado: 40 req/s POR WORKER
- BlingSyncJob: 40 req/s
- SyncInvoiceJob: 40 req/s
- ExtractTrackingJob: 40 req/s
──────────────────────────
TOTAL: 120 req/s 💥

Limite do Bling: 60 req/s
Resultado: DOBRO do limite! ❌ 429 Rate Limit Exceeded
```

### Evidência no Log
```
[logs] │ Bling API rate limit hit
[logs] └ artisan queue:work • tracking • App\Jobs\ExtractTrackingJob 
      • method: GET • endpoint: /logisticas/objetos/15833955764 
      • retry_after: • attempt: 1
```

## ✅ Solução Implementada

### Rate Limiting Simples e Conservador

Ao invés de usar mutex complexo, usamos **delay fixo conservador** em cada worker:

```php
// Antes: 40 req/s POR WORKER (problemático!)
usleep(25000); // 25ms

// Agora: 10 req/s POR WORKER (simples e seguro)
usleep(100000); // 100ms
```

### Cálculos

```
Limite Bling: 600 requests / 10 segundos = 60 req/s
Margem de segurança: 50% do limite = 30 req/s alvo

Com 3 workers fazendo requests ao Bling:
- BlingSyncJob: 10 req/s
- SyncInvoiceJob: 10 req/s  
- ExtractTrackingJob: 10 req/s
─────────────────────────────
Total: 30 req/s ✅

Resultado:
- 30 req/s < 60 req/s (50% do limite) ✅
- Margem de 100% para picos
- Sem erros 429
```

### Por Que Esta Solução?

**Tentativa 1 (FALHOU)**: Mutex com Cache (33ms TTL)
- ❌ Timeouts constantes (10 segundos)
- ❌ Workers travando esperando token
- ❌ File cache não trabalha bem com TTL muito curto
- ❌ Complexidade desnecessária

**Solução Final (FUNCIONA)**: Delay fixo conservador
- ✅ **Simples**: Apenas 1 linha de código `usleep(100000)`
- ✅ **Confiável**: Funciona com qualquer cache driver
- ✅ **Previsível**: Exatamente 10 req/s por worker
- ✅ **Seguro**: 50% de margem de segurança
- ✅ **Sem travamentos**: Sem mutex, sem timeouts

### Vantagens

✅ **Ultra simples**: Uma linha de código, fácil entender
✅ **Sem dependências**: Não precisa de coordenação entre workers
✅ **Conservador**: 50% do limite = muita margem
✅ **Funciona sempre**: Não importa o cache driver
✅ **Sem travamentos**: Sem mutex, sem espera, sem timeout
✅ **Performático**: Workers sempre progredindo (sem wait loops)

## 📊 Comparação

| Métrica | Antes | Depois |
|---------|-------|--------|
| **Rate limit** | 40 req/s por worker | 10 req/s por worker |
| **Total (3 workers)** | 120 req/s | 30 req/s |
| **Limite Bling** | 60 req/s | 60 req/s |
| **Status** | ❌ 2x acima do limite | ✅ 50% do limite |
| **Erros 429** | ✅ Sim (frequentes) | ❌ Não |
| **Delay** | 25ms | 100ms |
| **Complexidade** | 1 linha | 1 linha |
| **Travamentos** | ❌ Não | ❌ Não |

## 🔍 Monitoramento

### Verificar Rate Limiting

```bash
# Ver erros 429 (não deve ter nenhum!)
tail -f storage/logs/bling_sync/*.log storage/logs/invoices*.log storage/logs/tracking*.log | grep "429"

# Ver logs de sync
tail -f storage/logs/bling_sync/*.log storage/logs/invoices*.log storage/logs/tracking*.log
```

### Métricas Esperadas

```
✅ Perfeito: ~30 requests por segundo no total
✅ Bom: 25-35 requests por segundo  
⚠️  Alerta: > 50 requests por segundo (ainda dentro do limite, mas não ideal)
❌ Problema: Erros 429 nos logs (rate limit excedido)
```

### Performance

Com o delay de 100ms:
- **Cada worker**: ~10 requisições/segundo
- **3 workers**: ~30 requisições/segundo no total  
- **Velocidade de sync**: Mais lenta que antes, mas **SEM ERROS**
- **Vantagem**: Sistema estável, sem retries por rate limit

## 🎯 WooCommerce Rate Limiting

O SyncSellerJob também foi atualizado com rate limiting:

```php
// WooCommerce: ~10 req/s por IP
// Usando: 5 req/s (50% de margem)
usleep(200000); // 200ms = 5 req/s
```

**Importante**: WooCommerce não usa o mutex global porque:
- API diferente (não é Bling)
- Rate limit menor (10 req/s)
- Apenas 1 worker (sellers)
- Não precisa coordenar com outros

## 🚀 Comandos de Teste

### Testar Rate Limiting

```bash
# Rodar sync com todos os workers
composer dev

# Monitorar logs em tempo real
tail -f storage/logs/bling_sync/*.log \
        storage/logs/invoices*.log \
        storage/logs/tracking*.log \
        storage/logs/sellers*.log
```

### Verificar Cache (se usar Redis)

```bash
redis-cli
> KEYS bling_global_rate_limit_*
> TTL bling_global_rate_limit_pro
> GET bling_global_rate_limit_pro
```

## 📝 Notas Importantes

1. **Simplicidade é Rei**:
   - Solução: 1 linha de código `usleep(100000)`
   - Sem complexidade, sem mutex, sem travamentos
   - Fácil de entender, manter e debugar

2. **Performance vs Estabilidade**: 
   - Antes: Muito rápido MAS com erros 429
   - Agora: Mais lento MAS 100% estável
   - Trade-off correto: Estabilidade > Velocidade

3. **Múltiplas Contas**:
   - Pro e Atacadista são independentes
   - Cada um tem seu próprio limite de 60 req/s
   - Nossa taxa de 30 req/s é por conta

4. **Escalabilidade**:
   - Se adicionar mais workers: reduzir o delay proporcionalmente
   - Exemplo: 6 workers = 200ms delay (5 req/s cada = 30 req/s total)
   - Fórmula: `delay_ms = (1000 / target_rps_per_worker)`

## ✅ Checklist de Verificação

- [x] Rate limiting conservador implementado (10 req/s por worker)
- [x] Solução simples sem mutex ou locks complexos
- [x] Margem de segurança de 50% (30 req/s vs 60 req/s limite)
- [x] Sem travamentos ou timeouts
- [x] WooCommerce rate limiting adicionado (5 req/s)
- [x] Documentação completa
- [x] Testado e funcionando

## 🎓 Aprendizado

> **Sempre considere quantos processos/workers vão usar a mesma API simultaneamente ao definir rate limits!**

Se você tem:
- Limite da API: X req/s
- N workers simultâneos
- Margem de segurança desejada: M% (recomendado 50%)

Então cada worker deve fazer no máximo:
```
requests_per_worker = (X × M) / N
delay_ms = 1000 / requests_per_worker
```

**Exemplo deste projeto:**
```
X = 60 req/s (limite Bling)
M = 50% (margem segurança)
N = 3 workers

requests_per_worker = (60 × 0.5) / 3 = 10 req/s
delay_ms = 1000 / 10 = 100ms

Solução: usleep(100000)
```

**Lições importantes:**
1. ✅ **Simplicidade vence complexidade**: Delay fixo > Mutex complexo
2. ✅ **Margem de segurança importa**: 50% do limite = zero problemas
3. ✅ **Estabilidade > Velocidade**: Sistema lento e estável > rápido e falhando
4. ✅ **KISS Principle**: Keep It Simple, Stupid!

