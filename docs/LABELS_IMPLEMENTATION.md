# Implementação da Tela de Etiquetas

## Visão Geral

Implementação completa do módulo de gerenciamento de etiquetas de envio, integrado com a API do Bling, incluindo sincronização automática, filtros avançados e exportação para XLS/PDF.

## Arquivos Criados

### Backend

#### Database
- **Migration**: `database/migrations/2025_01_11_000001_create_labels_table.php`
  - Tabela `labels` com campos: `id`, `order_summary_id`, `bling_label_id`, `tracking_code`, `format`, `issue_date`, `link`, `observation`, `raw_data`
  - Índices para otimização de consultas
  - Foreign key para `order_summary`

- **Model**: `app/Models/Label.php`
  - Relationships: `belongsTo(OrderSummary)`, `tracking()`
  - Accessors: `is_posted`, `status_text`
  - Scopes: `posted()`, `notPosted()`, `generatedToday()`, `byDateRange()`, `search()`

- **Factory**: `database/factories/LabelFactory.php`
  - Geração de dados fake para testes
  - Estados: `withoutTracking()`, `withTrackingCode()`, `issuedToday()`, `pdf()`, `zpl()`

#### Services
- **CollmeProBlingLabelService**: `app/Services/CollmeProBlingLabelService.php`
  - Sincronização de etiquetas da API do Bling
  - Métodos: `syncLabels()`, `getLabelsFromApi()`, `processLabel()`, `mapBlingLabelToLabel()`
  - Rate limiting: 3 requisições/segundo

- **CollmeAtacadistaBlingLabelService**: `app/Services/CollmeAtacadistaBlingLabelService.php`
  - Herda de `CollmeProBlingLabelService`
  - Configuração específica para conta Atacadista

- **LabelPdfService**: `app/Services/LabelPdfService.php`
  - Geração de PDF para exportação
  - Métodos: `generate()`, `download()`, `stream()`

#### Jobs
- **LabelSyncJob**: `app/Jobs/LabelSyncJob.php`
  - Job de sincronização automática
  - Executa a cada 30 minutos
  - Sincroniza ambas as contas (Pro e Atacadista)
  - Configurado em `routes/console.php`

#### Controllers
- **LabelController**: `app/Http/Controllers/LabelController.php`
  - `index()`: Lista etiquetas com filtros e paginação
  - `export()`: Exporta para XLS ou PDF
  - Middleware: `permission:labels.view`

#### Exports
- **LabelsExport**: `app/Exports/LabelsExport.php`
  - Exportação para Excel usando `maatwebsite/excel`
  - Colunas: Número do Pedido, Número da Etiqueta, Data de Emissão, Formato, Status, Observação
  - Estilização: Cabeçalho com fundo indigo

#### Views
- **labels-pdf.blade.php**: `resources/views/exports/labels-pdf.blade.php`
  - Template para exportação PDF
  - Layout tabular com estilização moderna
  - Cabeçalho com informações e total de registros

### Frontend

#### Pages
- **Labels/Index.vue**: `resources/js/pages/Labels/Index.vue`
  - Interface completa de gerenciamento
  - Tabela responsiva com colunas configuradas
  - Filtros: Data (início/fim), Status, Busca rápida
  - Filtro rápido: "Etiquetas Geradas no Dia"
  - Botões de exportação: XLS e PDF
  - Paginação: 25, 50, 100, 200 itens/página
  - Dark theme consistente com o sistema

#### Components
- **AppSidebar.vue**: Atualizado para incluir item "Etiquetas"
  - Ícone: Tag (lucide-vue-next)
  - Rota: `/dashboard/labels`
  - Visível para usuários com permissão `labels.view`

### Routes
- **web.php**: Rotas adicionadas
  - `GET /dashboard/labels` → `LabelController@index`
  - `POST /dashboard/labels/export` → `LabelController@export`
  - Middleware: `permission:labels.view`

- **console.php**: Schedule adicionado
  - `Schedule::job(new LabelSyncJob)->everyThirtyMinutes()`

### Tests

#### Unit Tests
- **LabelTest**: `tests/Unit/Models/LabelTest.php`
  - Testa relationships
  - Testa accessors (`is_posted`, `status_text`)
  - Testa scopes (`generatedToday`, `byDateRange`, `search`)

- **BlingLabelServiceTest**: `tests/Unit/Services/BlingLabelServiceTest.php`
  - Testa configuração da conta
  - Testa mapeamento de dados da API
  - Testa detecção de formato (PDF/ZPL)
  - Testa busca de pedidos

#### Feature Tests
- **LabelControllerTest**: `tests/Feature/LabelControllerTest.php`
  - Testa listagem de etiquetas
  - Testa todos os filtros (search, date range, status, today)
  - Testa exportação XLS e PDF
  - Testa permissões
  - Testa paginação

- **LabelSyncJobTest**: `tests/Feature/LabelSyncJobTest.php`
  - Testa dispatch do job
  - Testa propriedades do job (tries, timeout)

## Dependências Adicionadas

```json
{
  "require": {
    "maatwebsite/excel": "^3.1",
    "barryvdh/laravel-dompdf": "^3.0"
  }
}
```

## Configuração

### Variáveis de Ambiente
As credenciais OAuth do Bling já estão configuradas:
- `BLING_PRO_CLIENT_ID`
- `BLING_PRO_CLIENT_SECRET`
- `BLING_ATACADISTA_CLIENT_ID`
- `BLING_ATACADISTA_CLIENT_SECRET`

### Permissões
A permissão `labels.view` já existe no sistema e está atribuída aos roles:
- Admin
- Manager
- Sales

## Fluxo de Sincronização

1. **Schedule** executa `LabelSyncJob` a cada 30 minutos
2. **Job** chama `syncLabels()` para cada conta (Pro, Atacadista)
3. **Service** faz requisições à API do Bling (`GET /logisticas/etiquetas`)
4. **Paginação** busca todas as páginas (100 itens por página)
5. **Processamento** cria ou atualiza etiquetas no banco
6. **Associação** tenta vincular etiqueta ao pedido via número no campo observação
7. **Tracking** vincula código de rastreamento quando disponível

## Status "Postado"

O status é calculado dinamicamente:
- **Não Postado**: Sem tracking ou tracking com situação < 2
- **Postado**: Tracking com situação >= 2 (despachado/em trânsito/entregue)

## Filtros Disponíveis

1. **Busca Rápida**: Busca em tracking_code, bling_label_id, observation, numero do pedido, nome do cliente
2. **Data Inicial/Final**: Filtra por range de data de emissão
3. **Status**: Postado, Não Postado, Todos
4. **Filtro Rápido "Hoje"**: Etiquetas geradas no dia atual
5. **Por Página**: 25, 50, 100, 200

## Exportação

### Excel (XLS)
- Formato: `.xlsx`
- Biblioteca: `maatwebsite/excel`
- Colunas: Número do Pedido, Número da Etiqueta, Data de Emissão, Formato, Status, Observação
- Estilização: Cabeçalho com fundo indigo, auto-size

### PDF
- Formato: `.pdf`
- Biblioteca: `barryvdh/laravel-dompdf`
- Layout: Landscape A4
- Cabeçalho: Logo, data de geração, total de registros
- Tabela: Todas as colunas com estilização

## Comandos Úteis

```bash
# Executar migration
php artisan migrate

# Executar sync manualmente
php artisan queue:work --once

# Rodar testes
php artisan test --filter Label

# Instalar dependências
composer update
```

## Próximos Passos

1. Executar `composer update` para instalar as novas dependências
2. Executar `php artisan migrate` para criar a tabela labels
3. Configurar tokens OAuth do Bling (se ainda não configurado)
4. Aguardar primeira sincronização automática ou executar manualmente
5. Acessar `/dashboard/labels` para visualizar etiquetas

## Observações

- O sistema mantém o raw_data da API para auditoria completa
- Labels duplicadas (mesmo bling_label_id) são atualizadas, não recriadas
- O rate limiting (3 req/s) é respeitado para não exceder limites da API do Bling
- A sincronização é incremental e não deleta etiquetas antigas
- O link para download da etiqueta é preservado do retorno da API

