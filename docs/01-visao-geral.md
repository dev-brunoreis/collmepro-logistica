# Visao Geral do Sistema

## Introducao

O Collmepro Logistica e um sistema centralizado de gestao logistica desenvolvido para gerenciar pedidos provenientes de multiplos sistemas de e-commerce (Collmepro, Collmepro Atacadista e Collme Beauty). O sistema integra-se com APIs externas para sincronizar pedidos, notas fiscais e informacoes de rastreamento, fornecendo uma interface unica para o controle de todo o fluxo logistico.

## Proposito

O sistema foi criado para:

- Centralizar a gestao de pedidos de diferentes plataformas de e-commerce
- Automatizar a sincronizacao de pedidos, notas fiscais e rastreamento
- Fornecer uma interface visual (Kanban) para acompanhamento do fluxo logistico
- Facilitar a gestao de usuarios e permissoes
- Gerar relatorios e metricas sobre operacoes logisticas

## Contexto de Uso

O sistema atende tres sistemas de e-commerce principais:

### Collmepro (Pro)
- Loja principal do grupo
- Integrada via API Bling
- Loja ID no Bling: 204303730

### Collmepro Atacadista
- Loja atacadista do grupo
- Integrada via API Bling
- Loja ID no Bling: 204651922

### Collme Beauty
- Loja especializada em produtos de beleza
- Integrada via API Bling (originalmente planejado Shopify, mas implementado via Bling)

## Arquitetura Geral

O sistema utiliza uma arquitetura moderna baseada em:

### Backend
- **Framework**: Laravel 12 (PHP 8.2+)
- **Autenticacao**: Laravel Fortify (com suporte a 2FA)
- **ORM**: Eloquent
- **Jobs/Filas**: Laravel Queue (com suporte a multiplas filas)
- **Cache**: Redis/File cache
- **Broadcasting**: Laravel Reverb (WebSockets)

### Frontend
- **Framework**: Vue.js 3.5+
- **Adaptador**: Inertia.js 2.0
- **Roteamento**: Laravel Wayfinder
- **UI Components**: Reka UI
- **Styling**: Tailwind CSS 4.0
- **Build**: Vite 7.0

### Banco de Dados
- **Producao**: MySQL
- **Desenvolvimento**: SQLite
- **Migracao**: Laravel Migrations

## Stack Tecnologico Completo

### Dependencias Backend (PHP)
```
- PHP 8.2+
- Laravel Framework 12.0
- Laravel Fortify 1.30 (autenticacao)
- Laravel Reverb 1.6 (WebSockets)
- Inertia Laravel 2.0 (adaptador frontend)
- Laravel Wayfinder 0.1.9 (roteamento)
- DomPDF 3.0 (geracao de PDFs)
- Maatwebsite Excel 3.1 (exportacao de dados)
```

### Dependencias Frontend (JavaScript)
```
- Vue.js 3.5.13
- Inertia.js Vue3 2.1.0
- VueUse 12.8.2 (composables utilitarios)
- Reka UI 2.4.1 (componentes UI)
- Tailwind CSS 4.1.1
- Lucide Vue Next 0.468.0 (icones)
- Laravel Echo 2.2.4 (WebSockets cliente)
- Pusher JS 8.4.0 (driver WebSockets)
- Vite 7.0.4
- TypeScript 5.2.2
```

### Ferramentas de Desenvolvimento
```
- Laravel Pint (formatacao de codigo PHP)
- ESLint (linting JavaScript)
- Prettier (formatacao JavaScript)
- Laravel Sail (ambiente Docker)
- PHPUnit 11.5 (testes)
- Concurrently (execucao paralela de comandos)
```

## Fluxo de Dados Principal

### 1. Importacao de Pedidos
```
Bling API → BlingSyncJob → OrderSummary → OrderStatus → Dashboard/Kanban
```

### 2. Sincronizacao de Notas Fiscais
```
OrderSummary → OrderSynced Event → SyncInvoiceJob → Invoice → Tracking
```

### 3. Rastreamento
```
Invoice → ExtractTrackingJob → Tracking → OrderSummary (tracking_code)
```

### 4. Atualizacao em Tempo Real
```
Backend Events → Laravel Reverb → Frontend (Vue.js) → Atualizacao UI
```

### 5. Gestao de Usuarios
```
Admin → UserController → User Model → RolePermission → Middleware
```

## Integracao com APIs Externas

### Bling API v3
- **Endpoint Base**: `https://api.bling.com.br/Api/v3`
- **Autenticacao**: OAuth 2.0 (Client Credentials)
- **Recursos Utilizados**:
  - `/pedidos/vendas` - Listagem e detalhes de pedidos
  - `/nfe/{id}` - Detalhes de notas fiscais
  - `/logisticas/objetos/{id}` - Dados de rastreamento
- **Rate Limiting**: 3 requisicoes/segundo (por conta)
- **Tokens**: Refresh automatico a cada 4 horas

## Caracteristicas Principais

### Sincronizacao Inteligente
- Sincronizacao incremental (ultimos 30 dias)
- Sistema de hash para deteccao de mudancas
- Cache para otimizacao de desempenho
- Processamento em background via jobs

### Sistema de Filas
O sistema utiliza 4 filas especializadas:
- **invoices**: Sincronizacao de notas fiscais
- **tracking**: Extracao de dados de rastreamento
- **sellers**: Sincronizacao de vendedores
- **default**: Jobs gerais

### Broadcasting em Tempo Real
- Atualizacoes instantaneas de pedidos
- Status de sincronizacao ao vivo
- Notificacoes de mudancas criticas

### Sistema de Permissoes Granular
- 4 niveis de acesso (Admin, Manager, Sales, Developer)
- Permissoes por recurso e acao
- Middleware customizado para verificacao

### Historico de Alteracoes
- Rastreamento completo de todas as mudancas em pedidos
- Registro de usuario e timestamp
- Armazenamento de valores antigos e novos

## Fluxo de Trabalho Kanban

O sistema organiza pedidos em 7 etapas:

1. **Pedidos Emitidos**: Pedidos sem nota fiscal
2. **Faturamento**: Pedidos com nota fiscal, aguardando separacao
3. **Checkout**: Pedidos em separacao (picking)
4. **Embalagem**: Pedidos em embalagem (packing)
5. **Etiqueta**: Pedidos embalados, aguardando codigo de rastreamento
6. **Coleta Aguardando**: Pedidos prontos para coleta
7. **Coleta Realizada**: Pedidos coletados

## Principios de Desenvolvimento

O sistema foi desenvolvido seguindo os principios:

- **TDD** (Test-Driven Development): Testes antes da implementacao
- **DRY** (Don't Repeat Yourself): Codigo reutilizavel
- **SOLID**: Principios de design orientado a objetos
- **KISS** (Keep It Simple, Stupid): Simplicidade na solucao
- **Event-Driven**: Arquitetura orientada a eventos

## Seguranca

- Autenticacao com Laravel Fortify
- Suporte a autenticacao de dois fatores (2FA)
- Middleware de autorizacao baseado em permissoes
- Sanitizacao de inputs
- Protecao CSRF
- Rate limiting em APIs

## Performance

- Cache de resultados de API
- Processamento assincrono via jobs
- Lazy loading de relacionamentos
- Indices otimizados no banco de dados
- Compactacao de assets
- CDN para assets estaticos (producao)

## Escalabilidade

- Jobs processados em workers paralelos
- Sistema de filas com prioridades
- Cache distribuido (Redis)
- Broadcasting via WebSockets (Laravel Reverb)
- Horizontal scaling ready

## Monitoramento

- Logs estruturados por canal
- Metricas de sincronizacao
- Historico de alteracoes
- Status de jobs em tempo real
- Laravel Pail para logs em desenvolvimento

