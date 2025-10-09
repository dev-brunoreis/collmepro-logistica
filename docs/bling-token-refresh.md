# Bling Token Automatic Refresh System

## Overview

Sistema automático de refresh de tokens da API do Bling para as contas Pro e Atacadista.

## Como Funciona

### Tokens do Bling
- **Access Token**: Expira em 6 horas
- **Refresh Token**: Expira em 30 dias, mas é renovado a cada refresh
- **Resultado**: Com o sistema rodando, os tokens NUNCA expiram!

### Schedule Automático
O sistema verifica os tokens **a cada 4 horas** automaticamente:
- Se o token expira em menos de 4 horas → Faz refresh
- Se o token ainda é válido → Não faz nada
- Registra tudo em `storage/logs/token.log`

### Job: RefreshBlingTokensJob
- Verifica ambas as contas (Pro e Atacadista)
- Calcula tempo até expiração
- Faz refresh quando necessário
- Loga todas as ações

## Arquivos Criados/Modificados

### Criados
1. `app/Jobs/RefreshBlingTokensJob.php` - Job que faz o refresh
2. `app/Console/Commands/RefreshBlingTokensCommand.php` - Comando manual
3. `tests/Unit/Jobs/RefreshBlingTokensJobTest.php` - Testes
4. `docs/bling-token-refresh.md` - Esta documentação

### Modificados
1. `app/Services/BlingOAuthService.php` - Correção do bug + logging
2. `config/logging.php` - Novo canal 'token'
3. `routes/console.php` - Schedule registrado

## Como Usar

### 1. Autorização Inicial (Uma vez apenas)

Para cada conta, você precisa autorizar UMA VEZ:

```bash
# Conta Pro
php artisan bling:authorize pro

# Conta Atacadista
php artisan bling:authorize atacadista
```

Siga as instruções para autorizar no navegador.

### 2. Refresh Manual (Para testes)

```bash
# Refresh de todas as contas
php artisan bling:refresh-tokens

# Refresh de uma conta específica
php artisan bling:refresh-tokens --account=pro
php artisan bling:refresh-tokens --account=atacadista
```

### 3. Ver Logs

```bash
# Ver logs em tempo real
tail -f storage/logs/token.log

# Ver últimas 50 linhas
tail -n 50 storage/logs/token.log

# Buscar por erros
grep -i error storage/logs/token.log
```

## Exemplo de Log

```
[2025-01-10 10:00:00] local.INFO: === Starting automatic token refresh check ===
[2025-01-10 10:00:00] local.INFO: Checking pro account token...
[2025-01-10 10:00:00] local.INFO: pro token status {"expires_at":"2025-01-10 14:30:00","hours_until_expiry":4.5,"is_expired":false,"is_expiring_soon":false}
[2025-01-10 10:00:00] local.INFO: Token for pro is still valid (expires in 4.5 hours). No refresh needed.
[2025-01-10 10:00:00] local.INFO: Checking atacadista account token...
[2025-01-10 10:00:00] local.INFO: atacadista token status {"expires_at":"2025-01-10 13:00:00","hours_until_expiry":3.0,"is_expired":false,"is_expiring_soon":true}
[2025-01-10 10:00:00] local.INFO: Token for atacadista needs refresh (expires in 3.0 hours)
[2025-01-10 10:00:01] local.INFO: Attempting to refresh token for account: atacadista
[2025-01-10 10:00:02] local.INFO: Token refreshed successfully {"account":"atacadista","expires_at":"2025-01-10 16:00:02","expires_in_hours":6.0}
[2025-01-10 10:00:02] local.INFO: Token successfully refreshed for atacadista account
[2025-01-10 10:00:02] local.INFO: === Token refresh check completed ===
```

## Schedule

O job roda automaticamente a cada 4 horas:
- 00:00
- 04:00
- 08:00
- 12:00
- 16:00
- 20:00

Para verificar os schedules:
```bash
php artisan schedule:list
```

Para rodar os schedules localmente (desenvolvimento):
```bash
php artisan schedule:work
```

Em produção, certifique-se que o cron está configurado:
```cron
* * * * * cd /path-to-project && php artisan schedule:run >> /dev/null 2>&1
```

## Troubleshooting

### Token não está sendo renovado

1. Verifique se o cron está rodando:
```bash
php artisan schedule:list
```

2. Execute manualmente para testar:
```bash
php artisan bling:refresh-tokens
```

3. Verifique os logs:
```bash
tail -f storage/logs/token.log
```

### "No refresh token available"

O refresh_token expirou (sistema ficou 30+ dias parado). 
**Solução**: Autorizar novamente:
```bash
php artisan bling:authorize pro
# ou
php artisan bling:authorize atacadista
```

### "Manual authorization required"

Não existe token no banco de dados.
**Solução**: Autorizar pela primeira vez:
```bash
php artisan bling:authorize pro
php artisan bling:authorize atacadista
```

## Testes

```bash
# Rodar testes do job
php artisan test --filter RefreshBlingTokensJobTest

# Rodar todos os testes
php artisan test
```

## Benefícios

✅ Tokens sempre válidos  
✅ Sem necessidade de autorização manual recorrente  
✅ Logs detalhados de todas as operações  
✅ Suporta múltiplas contas Bling  
✅ Refresh automático antes de expirar  
✅ Tratamento de erros robusto  

## Limitações

⚠️ Se o sistema ficar OFFLINE por mais de 30 dias, será necessário autorizar manualmente novamente  
⚠️ Requer cron configurado em produção  
⚠️ Depende das credenciais corretas no `.env`  

## Variáveis de Ambiente Necessárias

```env
BLING_PRO_CLIENT_ID=your_pro_client_id
BLING_PRO_CLIENT_SECRET=your_pro_client_secret

BLING_ATACADISTA_CLIENT_ID=your_atacadista_client_id
BLING_ATACADISTA_CLIENT_SECRET=your_atacadista_client_secret
```

