# Documentacao Tecnica - Sistema Collmepro Logistica

## Introducao

Bem-vindo a documentacao tecnica completa do Sistema Collmepro Logistica. Esta documentacao foi escrita para desenvolvedores e contem informacoes detalhadas sobre arquitetura, implementacao, configuracao e manutencao do sistema.

## Visao Geral do Sistema

O Collmepro Logistica e um sistema centralizado de gestao logistica desenvolvido para gerenciar pedidos de multiplas plataformas de e-commerce (Collmepro Pro, Collmepro Atacadista e Collme Beauty). O sistema integra-se com a API do Bling para sincronizar pedidos, notas fiscais e dados de rastreamento.

### Stack Tecnologico

- **Backend**: Laravel 12 (PHP 8.2+)
- **Frontend**: Vue.js 3 + Inertia.js
- **Database**: MySQL / SQLite
- **Cache/Queue**: Redis
- **Broadcasting**: Laravel Reverb (WebSockets)

## Indice da Documentacao

### 1. Fundamentos

#### [01 - Visao Geral](01-visao-geral.md)
Introducao ao sistema, proposito, contexto de uso, arquitetura geral e stack tecnologico completo.

#### [02 - Instalacao e Configuracao](02-instalacao.md)
Requisitos do sistema, processo de instalacao, configuracao de ambiente, comandos de desenvolvimento e build.

#### [03 - Arquitetura do Sistema](03-arquitetura.md)
Estrutura de diretorios, camadas da aplicacao, padroes de design, sistema de filas, broadcasting e otimizacoes.

### 2. Dados e Persistencia

#### [04 - Banco de Dados](04-banco-de-dados.md)
Diagrama ER, tabelas e relacionamentos, indices, migrations, seeders e estrategias de backup.

### 3. Seguranca e Acesso

#### [05 - Autenticacao e Autorizacao](05-autenticacao.md)
Laravel Fortify, perfis de usuario, sistema de permissoes, middleware, gestao de sessoes e 2FA.

### 4. Integracoes

#### [06 - Integracoes Externas](06-integracoes.md)
Bling API v3, OAuth 2.0, rate limiting, WooCommerce API, mapeamento de transportadoras e APIs de rastreamento.

### 5. Funcionalidades

#### [07 - Modulos Funcionais](07-modulos.md)
Dashboard, gestao de pedidos, sistema Kanban, gestao de usuarios, sincronizacao de dados e historico de alteracoes.

#### [08 - Jobs e Processamento em Background](08-jobs.md)
Sistema de filas, workers, BlingSyncJob, SyncInvoiceJob, ExtractTrackingJob e estrategias de retry.

#### [09 - Frontend](09-frontend.md)
Vue.js + Inertia, estrutura de componentes, roteamento Wayfinder, composables, broadcasting em tempo real e TypeScript.

### 6. APIs e Services

#### [10 - API e Rotas](10-rotas-api.md)
Mapeamento completo de rotas, endpoints, middleware, parametros, respostas e canais de broadcasting.

#### [11 - Services e Logica de Negocio](11-services.md)
BlingService, BlingOAuthService, CarrierService, OrderHistoryService, SystemMappingService e WooCommerceService.

### 7. Qualidade e Testes

#### [12 - Testes](12-testes.md)
Estrutura de testes, testes unitarios, testes de feature, mocks para APIs externas, factories e cobertura de codigo.

### 8. Operacao e Manutencao

#### [13 - Deploy e Producao](13-deploy.md)
Requisitos de servidor, configuracao, deploy, Nginx, Supervisor, SSL, backup, atualizacoes e otimizacoes.

#### [14 - Manutencao e Troubleshooting](14-manutencao.md)
Logs do sistema, problemas comuns e solucoes, debug de integracoes, limpeza de cache e monitoramento.

#### [15 - Referencia de Comandos](15-comandos.md)
Comandos Artisan, NPM, Composer, Git, MySQL, Redis, Supervisor, Nginx e comandos de sistema.

## Como Usar Esta Documentacao

### Para Desenvolvedores Novos no Projeto

1. Comece com [01 - Visao Geral](01-visao-geral.md) para entender o sistema
2. Siga para [02 - Instalacao](02-instalacao.md) para configurar ambiente local
3. Leia [03 - Arquitetura](03-arquitetura.md) para entender a estrutura
4. Consulte [04 - Banco de Dados](04-banco-de-dados.md) para entender os dados
5. Explore [07 - Modulos](07-modulos.md) para conhecer as funcionalidades

### Para Desenvolvimento

- [08 - Jobs](08-jobs.md): Entender processamento em background
- [09 - Frontend](09-frontend.md): Desenvolver interfaces
- [11 - Services](11-services.md): Implementar logica de negocio
- [12 - Testes](12-testes.md): Escrever testes

### Para Integracao

- [06 - Integracoes](06-integracoes.md): Trabalhar com APIs externas
- [10 - Rotas](10-rotas-api.md): Entender endpoints disponiveis

### Para DevOps

- [13 - Deploy](13-deploy.md): Deploy em producao
- [14 - Manutencao](14-manutencao.md): Monitorar e resolver problemas
- [15 - Comandos](15-comandos.md): Referencia rapida de comandos

### Para Troubleshooting

1. Consulte [14 - Manutencao](14-manutencao.md) para problemas comuns
2. Verifique logs conforme documentado
3. Use [15 - Comandos](15-comandos.md) para executar diagnosticos

## Principios de Desenvolvimento

O sistema foi desenvolvido seguindo:

- **TDD** (Test-Driven Development)
- **DRY** (Don't Repeat Yourself)
- **SOLID** (Principios de design orientado a objetos)
- **KISS** (Keep It Simple, Stupid)
- **Event-Driven Architecture**

## Convencoes

### Codigo

- **PHP**: PSR-12
- **JavaScript/Vue**: ESLint
- **Commits**: Conventional Commits
- **Branches**: GitFlow

### Nomenclatura

- **Classes**: PascalCase
- **Metodos**: camelCase
- **Variaveis**: camelCase
- **Constantes**: UPPER_SNAKE_CASE
- **Arquivos**: kebab-case ou PascalCase

### Commits

- `feat:` Nova funcionalidade
- `fix:` Correcao de bug
- `docs:` Documentacao
- `style:` Formatacao
- `refactor:` Refatoracao
- `test:` Testes
- `chore:` Manutencao

Exemplo: `feat: add order export to PDF`

## Recursos Adicionais

### Links Importantes

- **Laravel**: https://laravel.com/docs
- **Vue.js**: https://vuejs.org/guide
- **Inertia.js**: https://inertiajs.com/
- **Bling API**: https://developer.bling.com.br/
- **Tailwind CSS**: https://tailwindcss.com/docs

### Suporte

- **Desenvolvedor**: Bruno Reis
- **Email**: contato@brunoreis.dev
- **Website**: https://brunoreis.dev

## Licenca

MIT License

Copyright (c) 2025 Bruno Reis

---

Documentacao gerada para o projeto Collmepro Logistica.

