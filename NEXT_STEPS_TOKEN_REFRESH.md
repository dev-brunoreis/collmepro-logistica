# 🚀 Próximos Passos - Bling Token Refresh

## ✅ O Que Já Está Pronto

- ✅ Sistema de refresh automático implementado
- ✅ Schedule configurado (a cada 4 horas)
- ✅ Logging dedicado (token.log)
- ✅ Comando manual para testes
- ✅ Testes automatizados (4/4 passando)
- ✅ Documentação completa

---

## 📋 Checklist de Ativação

### 1. ✅ Verificar Variáveis de Ambiente

Confirme que seu `.env` tem as credenciais corretas:

```bash
cd /home/logistica.collmepro.com.br/public_html
cat .env | grep BLING
```

Deve mostrar:
```env
BLING_PRO_CLIENT_ID=seu_client_id_pro
BLING_PRO_CLIENT_SECRET=seu_client_secret_pro
BLING_ATACADISTA_CLIENT_ID=seu_client_id_atacadista
BLING_ATACADISTA_CLIENT_SECRET=seu_client_secret_atacadista
```

---

### 2. 🔑 Autorizar as Contas (IMPORTANTE - Faça Agora!)

#### Conta Pro
```bash
php artisan bling:authorize pro
```

Passos:
1. O comando vai gerar uma URL
2. Abra a URL no navegador
3. Faça login no Bling (conta Pro)
4. Autorize o aplicativo
5. Você será redirecionado para uma URL com `code` e `state`
6. Copie esses valores e execute:
```bash
php artisan bling:authorize pro --code=CODIGO --state=STATE
```

#### Conta Atacadista
```bash
php artisan bling:authorize atacadista
```

Repita os mesmos passos acima para a conta Atacadista.

---

### 3. ✅ Testar Refresh Manual

Após autorizar ambas as contas:

```bash
php artisan bling:refresh-tokens
```

Deve mostrar:
```
🔄 Refreshing Bling tokens...

Checking and refreshing tokens for all accounts...

✅ Token refresh process completed!

📋 Check the logs for details:
   tail -f storage/logs/token.log
```

---

### 4. 📊 Verificar Logs

```bash
tail -f storage/logs/token.log
```

Você deve ver algo como:
```log
[2025-XX-XX XX:XX:XX] local.INFO: === Starting automatic token refresh check ===
[2025-XX-XX XX:XX:XX] local.INFO: Checking pro account token...
[2025-XX-XX XX:XX:XX] local.INFO: pro token status {"expires_at":"...","hours_until_expiry":X.XX,...}
...
```

---

### 5. ✅ Verificar Schedule

```bash
php artisan schedule:list
```

Deve mostrar:
```
0 */4 * * * App\Jobs\RefreshBlingTokensJob ......... Next Due: em X horas
```

---

### 6. 🔄 Verificar Banco de Dados

```bash
php artisan tinker
```

Dentro do tinker:
```php
\App\Models\BlingToken::all();
```

Deve mostrar 2 tokens (pro e atacadista) com:
- `access_token` (preenchido)
- `refresh_token` (preenchido)
- `expires_at` (data futura)

Para sair do tinker: `exit`

---

### 7. 🌐 Verificar Cron (PRODUÇÃO)

Se estiver em produção, verifique se o cron do Laravel está configurado:

```bash
crontab -l
```

Deve ter:
```cron
* * * * * cd /home/logistica.collmepro.com.br/public_html && php artisan schedule:run >> /dev/null 2>&1
```

Se não tiver, adicione:
```bash
crontab -e
```

Adicione a linha acima e salve.

---

### 8. 📈 Monitorar por 24h

Depois de tudo configurado, monitore os logs por 24 horas:

```bash
# Terminal 1 - Monitorar tokens
tail -f storage/logs/token.log

# Terminal 2 - Monitorar aplicação
tail -f storage/logs/laravel.log
```

O job deve rodar automaticamente:
- **00:00** - Meia-noite
- **04:00** - Madrugada
- **08:00** - Manhã
- **12:00** - Meio-dia
- **16:00** - Tarde
- **20:00** - Noite

---

## 🎯 Comandos Úteis do Dia a Dia

### Ver status dos tokens
```bash
php artisan tinker
\App\Models\BlingToken::all(['account_type', 'expires_at'])->toArray();
exit
```

### Forçar refresh manual
```bash
php artisan bling:refresh-tokens
```

### Ver quando o próximo job vai rodar
```bash
php artisan schedule:list | grep Refresh
```

### Ver logs de hoje
```bash
grep "$(date +%Y-%m-%d)" storage/logs/token.log
```

### Contar quantos refreshes foram feitos
```bash
grep -c "Token refreshed successfully" storage/logs/token.log
```

### Ver apenas erros
```bash
grep ERROR storage/logs/token.log
```

---

## 🚨 Troubleshooting

### Problema: "No token found"
**Solução**: Autorize a conta
```bash
php artisan bling:authorize [pro|atacadista]
```

### Problema: "Invalid refresh token"
**Causa**: Refresh token expirou (sistema parado 30+ dias)
**Solução**: Autorize novamente
```bash
php artisan bling:authorize [pro|atacadista]
```

### Problema: "Client ID not configured"
**Causa**: Variáveis de ambiente faltando
**Solução**: Configure no `.env`:
```env
BLING_PRO_CLIENT_ID=...
BLING_PRO_CLIENT_SECRET=...
BLING_ATACADISTA_CLIENT_ID=...
BLING_ATACADISTA_CLIENT_SECRET=...
```

### Problema: Schedule não está rodando
**Causa**: Cron não configurado
**Solução**: 
1. Em desenvolvimento: `php artisan schedule:work` (deixe rodando)
2. Em produção: Configure cron (ver passo 7 acima)

---

## 📚 Documentação

- **Guia Completo**: `docs/bling-token-refresh.md`
- **Exemplos de Log**: `docs/token-log-example.md`
- **Resumo da Implementação**: `BLING_TOKEN_REFRESH_SUMMARY.md`
- **Próximos Passos**: `NEXT_STEPS_TOKEN_REFRESH.md` (este arquivo)

---

## ✅ Checklist Final

- [ ] Verificar variáveis de ambiente (.env)
- [ ] Autorizar conta Pro
- [ ] Autorizar conta Atacadista
- [ ] Testar refresh manual
- [ ] Verificar logs (token.log)
- [ ] Verificar schedule está registrado
- [ ] Verificar tokens no banco de dados
- [ ] Configurar cron (se produção)
- [ ] Monitorar logs por 24h
- [ ] ✨ Pronto! Sistema funcionando automaticamente

---

## 🎉 Quando Tudo Estiver OK

Você verá nos logs a cada 4 horas:
```log
[DATA HORA] local.INFO: === Starting automatic token refresh check ===
[DATA HORA] local.INFO: Checking pro account token...
[DATA HORA] local.INFO: Token for pro is still valid (expires in X hours). No refresh needed.
[DATA HORA] local.INFO: Checking atacadista account token...
[DATA HORA] local.INFO: Token for atacadista is still valid (expires in X hours). No refresh needed.
[DATA HORA] local.INFO: === Token refresh check completed ===
```

Ou quando fizer refresh:
```log
[DATA HORA] local.INFO: Token refreshed successfully {"account":"pro","expires_at":"...","expires_in_hours":6.0}
```

---

**E pronto! 🎉 Sistema totalmente automático!**

Você só precisou autorizar UMA VEZ e o sistema manterá os tokens sempre válidos automaticamente! 🚀

