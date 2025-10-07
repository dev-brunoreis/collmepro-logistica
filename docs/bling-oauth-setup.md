# Bling OAuth2 Integration Setup

## Overview

Este sistema implementa integração OAuth2 completa com o Bling API v3 para as contas Collme Pro e Collme Atacadista. O sistema gerencia automaticamente tokens de acesso, refresh automático e persistência no banco de dados.

## Arquitetura

### Componentes Principais

1. **BlingToken Model** - Gerencia tokens no banco de dados
2. **BlingOAuthService** - Serviço principal para gerenciamento OAuth2
3. **BlingService** - Classe abstrata base para serviços Bling
4. **CollmeProBlingService** - Implementação específica para Collme Pro
5. **CollmeAtacadistaBlingService** - Implementação específica para Collme Atacadista

### Fluxo de Autenticação

1. **Primeira Execução**: Sistema gera URL de autorização
2. **Autorização Manual**: Usuário autoriza via browser
3. **Troca de Código**: Sistema troca código por tokens
4. **Uso Automático**: Tokens são reutilizados até expirar
5. **Refresh Automático**: Sistema renova tokens automaticamente

## Configuração

### 1. Variáveis de Ambiente

Adicione ao seu arquivo `.env`:

```env
# Collme Pro Account
BLING_PRO_CLIENT_ID=seu_client_id_pro_aqui
BLING_PRO_CLIENT_SECRET=seu_client_secret_pro_aqui

# Collme Atacadista Account
BLING_ATACADISTA_CLIENT_ID=seu_client_id_atacadista_aqui
BLING_ATACADISTA_CLIENT_SECRET=seu_client_secret_atacadista_aqui
```

### 2. Database

A migration já foi executada e criou a tabela `bling_tokens`:

```sql
- id (primary key)
- account_type (pro|atacadista)
- access_token (encrypted)
- refresh_token (nullable)
- expires_at (timestamp)
- scope (json)
- created_at, updated_at
```

## Uso

### 1. Autorização Inicial

Para cada conta, você precisa autorizar uma vez:

```bash
# Gerar URL de autorização para Collme Pro
php artisan bling:authorize pro

# Gerar URL de autorização para Collme Atacadista
php artisan bling:authorize atacadista
```

### 2. Completar Autorização

Após clicar no link e autorizar:

```bash
# Trocar código por token (exemplo)
php artisan bling:authorize pro --code=6521791563472106326454c818bd19f60febdcf3 --state=291e61b56ab3d845622cf137b1e1e2
```

### 3. Sincronização

Após autorização, você pode sincronizar:

```bash
# Sincronizar todas as contas
php artisan bling:sync

# Sincronizar apenas Collme Pro
php artisan bling:sync --account=pro

# Sincronizar apenas Collme Atacadista
php artisan bling:sync --account=atacadista

# Forçar atualização de pedidos existentes
php artisan bling:sync --force

# Testar conexão
php artisan bling:sync --test
```

## Comandos Disponíveis

### `bling:sync`
Sincroniza pedidos do Bling.

**Opções:**
- `--account=` - Conta específica (pro|atacadista|all)
- `--force` - Forçar atualização de pedidos existentes
- `--queue` - Executar em background
- `--test` - Testar apenas conexão

### `bling:authorize`
Gerencia autorização OAuth2.

**Argumentos:**
- `account` - Conta para autorizar (pro|atacadista)

**Opções:**
- `--code=` - Código de autorização do callback
- `--state=` - Parâmetro state do callback

## Funcionalidades Automáticas

### 1. Gerenciamento de Tokens
- ✅ Verificação automática de expiração
- ✅ Refresh automático quando necessário
- ✅ Persistência no banco de dados
- ✅ Reutilização entre requests

### 2. Tratamento de Erros
- ✅ Retry automático em falhas de rede
- ✅ Logs detalhados para debug
- ✅ Fallback para autorização manual

### 3. Segurança
- ✅ Tokens armazenados de forma segura
- ✅ Validação de state parameter
- ✅ HTTPS obrigatório para todas as requests

## Estrutura de Dados

### BlingToken
```php
[
    'id' => 1,
    'account_type' => 'pro',
    'access_token' => '4a9de71b8aaf91c8ebbf830888354d5479e83a01',
    'refresh_token' => 'e4d61baafd951bbbdec0a92cf9700a49b4cbc005',
    'expires_at' => '2025-10-07 22:00:00',
    'scope' => ['98309', '318257570', '5862218180'],
    'created_at' => '2025-10-07 16:00:00',
    'updated_at' => '2025-10-07 16:00:00'
]
```

## Troubleshooting

### Token Expirado
```bash
# O sistema tenta refresh automático
# Se falhar, execute autorização manual:
php artisan bling:authorize pro
```

### Erro de Configuração
```bash
# Verifique se as variáveis estão no .env
php artisan config:cache
php artisan config:clear
```

### Logs
```bash
# Ver logs em tempo real
tail -f storage/logs/laravel.log

# Filtrar logs do Bling
grep "Bling" storage/logs/laravel.log
```

## Desenvolvimento

### Adicionar Nova Conta

1. Crie nova implementação estendendo `BlingService`
2. Adicione credenciais no `config/services.php`
3. Registre no `AppServiceProvider`
4. Adicione variáveis de ambiente

### Personalizar Processamento de Pedidos

Override o método `processOrder()` nas classes específicas:

```php
protected function processOrder(array $orderData): void
{
    parent::processOrder($orderData);
    
    // Sua lógica específica aqui
}
```

## API Endpoints Utilizados

- **Authorization**: `https://bling.com.br/Api/v3/oauth/authorize`
- **Token**: `https://api.bling.com.br/Api/v3/oauth/token`
- **Orders**: `https://api.bling.com.br/Api/v3/pedidos`
- **Products**: `https://api.bling.com.br/Api/v3/produtos`
- **Contacts**: `https://api.bling.com.br/Api/v3/contatos`
