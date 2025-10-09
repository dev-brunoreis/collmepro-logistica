# Exemplo de Log do Token Refresh

## Cenário 1: Tokens Válidos (Sem Refresh Necessário)

```log
[2025-01-10 08:00:00] local.INFO: === Starting automatic token refresh check ===
[2025-01-10 08:00:00] local.INFO: Checking pro account token...
[2025-01-10 08:00:00] local.INFO: pro token status {"expires_at":"2025-01-10 13:45:00","hours_until_expiry":5.75,"is_expired":false,"is_expiring_soon":false}
[2025-01-10 08:00:00] local.INFO: Token for pro is still valid (expires in 5.75 hours). No refresh needed.
[2025-01-10 08:00:01] local.INFO: Checking atacadista account token...
[2025-01-10 08:00:01] local.INFO: atacadista token status {"expires_at":"2025-01-10 14:20:00","hours_until_expiry":6.33,"is_expired":false,"is_expiring_soon":false}
[2025-01-10 08:00:01] local.INFO: Token for atacadista is still valid (expires in 6.33 hours). No refresh needed.
[2025-01-10 08:00:01] local.INFO: === Token refresh check completed ===
```

---

## Cenário 2: Token Pro Precisa de Refresh

```log
[2025-01-10 12:00:00] local.INFO: === Starting automatic token refresh check ===
[2025-01-10 12:00:00] local.INFO: Checking pro account token...
[2025-01-10 12:00:00] local.INFO: pro token status {"expires_at":"2025-01-10 15:30:00","hours_until_expiry":3.5,"is_expired":false,"is_expiring_soon":true}
[2025-01-10 12:00:00] local.INFO: Token for pro needs refresh (expires in 3.5 hours)
[2025-01-10 12:00:00] local.INFO: Attempting to refresh token for account: pro
[2025-01-10 12:00:01] local.INFO: Token refreshed successfully {"account":"pro","expires_at":"2025-01-10 18:00:01","expires_in_hours":6.0}
[2025-01-10 12:00:01] local.INFO: Token successfully refreshed for pro account
[2025-01-10 12:00:01] local.INFO: Checking atacadista account token...
[2025-01-10 12:00:01] local.INFO: atacadista token status {"expires_at":"2025-01-10 17:45:00","hours_until_expiry":5.75,"is_expired":false,"is_expiring_soon":false}
[2025-01-10 12:00:01] local.INFO: Token for atacadista is still valid (expires in 5.75 hours). No refresh needed.
[2025-01-10 12:00:01] local.INFO: === Token refresh check completed ===
```

---

## Cenário 3: Ambos os Tokens Precisam de Refresh

```log
[2025-01-10 16:00:00] local.INFO: === Starting automatic token refresh check ===
[2025-01-10 16:00:00] local.INFO: Checking pro account token...
[2025-01-10 16:00:00] local.INFO: pro token status {"expires_at":"2025-01-10 19:20:00","hours_until_expiry":3.33,"is_expired":false,"is_expiring_soon":true}
[2025-01-10 16:00:00] local.INFO: Token for pro needs refresh (expires in 3.33 hours)
[2025-01-10 16:00:00] local.INFO: Attempting to refresh token for account: pro
[2025-01-10 16:00:01] local.INFO: Token refreshed successfully {"account":"pro","expires_at":"2025-01-10 22:00:01","expires_in_hours":6.0}
[2025-01-10 16:00:01] local.INFO: Token successfully refreshed for pro account
[2025-01-10 16:00:01] local.INFO: Checking atacadista account token...
[2025-01-10 16:00:01] local.INFO: atacadista token status {"expires_at":"2025-01-10 19:45:00","hours_until_expiry":3.75,"is_expired":false,"is_expiring_soon":true}
[2025-01-10 16:00:01] local.INFO: Token for atacadista needs refresh (expires in 3.75 hours)
[2025-01-10 16:00:01] local.INFO: Attempting to refresh token for account: atacadista
[2025-01-10 16:00:02] local.INFO: Token refreshed successfully {"account":"atacadista","expires_at":"2025-01-10 22:00:02","expires_in_hours":6.0}
[2025-01-10 16:00:02] local.INFO: Token successfully refreshed for atacadista account
[2025-01-10 16:00:02] local.INFO: === Token refresh check completed ===
```

---

## Cenário 4: Erro ao Refreshar Token

```log
[2025-01-10 20:00:00] local.INFO: === Starting automatic token refresh check ===
[2025-01-10 20:00:00] local.INFO: Checking pro account token...
[2025-01-10 20:00:00] local.INFO: pro token status {"expires_at":"2025-01-11 01:15:00","hours_until_expiry":5.25,"is_expired":false,"is_expiring_soon":false}
[2025-01-10 20:00:00] local.INFO: Token for pro is still valid (expires in 5.25 hours). No refresh needed.
[2025-01-10 20:00:00] local.INFO: Checking atacadista account token...
[2025-01-10 20:00:00] local.INFO: atacadista token status {"expires_at":"2025-01-10 23:30:00","hours_until_expiry":3.5,"is_expired":false,"is_expiring_soon":true}
[2025-01-10 20:00:00] local.INFO: Token for atacadista needs refresh (expires in 3.5 hours)
[2025-01-10 20:00:00] local.INFO: Attempting to refresh token for account: atacadista
[2025-01-10 20:00:01] local.ERROR: Token refresh failed {"account":"atacadista","status":401,"body":"Invalid refresh token"}
[2025-01-10 20:00:01] local.ERROR: Failed to refresh token for atacadista account. Manual authorization may be required.
[2025-01-10 20:00:01] local.INFO: === Token refresh check completed ===
```

**Ação Necessária**: Quando ver erro 401, executar:
```bash
php artisan bling:authorize atacadista
```

---

## Cenário 5: Token Não Existe (Primeira Autorização Necessária)

```log
[2025-01-10 00:00:00] local.INFO: === Starting automatic token refresh check ===
[2025-01-10 00:00:00] local.INFO: Checking pro account token...
[2025-01-10 00:00:00] local.WARNING: No token found for pro account. Manual authorization required.
[2025-01-10 00:00:00] local.INFO: Checking atacadista account token...
[2025-01-10 00:00:00] local.WARNING: No token found for atacadista account. Manual authorization required.
[2025-01-10 00:00:00] local.INFO: === Token refresh check completed ===
```

**Ação Necessária**: Executar autorização inicial:
```bash
php artisan bling:authorize pro
php artisan bling:authorize atacadista
```

---

## Cenário 6: Autorização Manual Bem-Sucedida

```log
[2025-01-10 10:30:45] local.INFO: Token generated successfully for account: pro {"expires_at":"2025-01-10 16:30:45","expires_in_hours":6.0}
```

---

## Cenário 7: Refresh Manual via Comando

```log
[2025-01-10 11:15:30] local.INFO: === Starting automatic token refresh check ===
[2025-01-10 11:15:30] local.INFO: Checking pro account token...
[2025-01-10 11:15:30] local.INFO: pro token status {"expires_at":"2025-01-10 16:30:45","hours_until_expiry":5.26,"is_expired":false,"is_expiring_soon":false}
[2025-01-10 11:15:30] local.INFO: Token for pro is still valid (expires in 5.26 hours). No refresh needed.
[2025-01-10 11:15:30] local.INFO: Checking atacadista account token...
[2025-01-10 11:15:30] local.INFO: atacadista token status {"expires_at":"2025-01-10 17:00:00","hours_until_expiry":5.76,"is_expired":false,"is_expiring_soon":false}
[2025-01-10 11:15:30] local.INFO: Token for atacadista is still valid (expires in 5.76 hours). No refresh needed.
[2025-01-10 11:15:30] local.INFO: === Token refresh check completed ===
```

---

## Como Monitorar

### Ver logs em tempo real
```bash
tail -f storage/logs/token.log
```

### Ver últimas 50 linhas
```bash
tail -n 50 storage/logs/token.log
```

### Buscar por erros
```bash
grep -i error storage/logs/token.log
```

### Buscar por refreshes bem-sucedidos
```bash
grep -i "refreshed successfully" storage/logs/token.log
```

### Ver apenas horários de execução do job
```bash
grep "Starting automatic token refresh check" storage/logs/token.log
```

### Contar quantos refreshes foram feitos hoje
```bash
grep -c "Token refreshed successfully" storage/logs/token.log
```

---

## Interpretação dos Logs

### ✅ Tudo OK
- `Token is still valid` - Token não precisa de refresh
- `Token refreshed successfully` - Refresh foi bem-sucedido

### ⚠️ Atenção
- `Token needs refresh` - Token será refreshed (normal)
- `is_expiring_soon: true` - Token será refreshed em breve (normal)

### ❌ Erro - Requer Ação
- `No token found` - Precisa autorizar: `php artisan bling:authorize [account]`
- `Invalid refresh token` - Refresh_token expirou, precisa autorizar novamente
- `Token refresh failed` - Erro na API, verificar credenciais no `.env`
- `Manual authorization required` - Executar `php artisan bling:authorize [account]`

---

## Frequência de Logs

Com schedule a cada 4 horas, você terá logs:
- **6 verificações por dia** (00:00, 04:00, 08:00, 12:00, 16:00, 20:00)
- **~42 verificações por semana**
- **~180 verificações por mês**

Cada verificação gera ~8-12 linhas de log, resultando em:
- **~1000 linhas por semana**
- **~4000 linhas por mês**

O arquivo `token.log` deve ser rotacionado periodicamente para evitar crescimento excessivo.

