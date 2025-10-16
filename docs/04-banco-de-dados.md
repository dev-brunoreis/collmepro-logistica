# Banco de Dados

## Visao Geral

O sistema utiliza um banco de dados relacional para armazenar todas as informacoes de pedidos, notas fiscais, rastreamento, usuarios e configuracoes. O schema foi projetado para suportar:

- Importacao de pedidos de multiplas fontes
- Rastreamento completo do fluxo logistico
- Auditoria de todas as alteracoes
- Sistema de permissoes granular
- Cache de tokens OAuth

## Diagrama de Relacionamentos (ER)

```
users
  ├─→ role_permissions (via role)
  ├─→ order_status (picking_marked_by)
  ├─→ order_status (packing_marked_by)
  └─→ order_status (picked_up_marked_by)

order_summary (pedidos principais)
  ├─→ order_data (dados detalhados)
  ├─→ invoices (notas fiscais)
  ├─→ order_statuses (status de processamento)
  ├─→ order_histories (historico de mudancas)
  └─→ sellers (vendedores, many-to-many)

invoices (notas fiscais)
  └─→ tracking (rastreamento)

permissions
  └─→ role_permissions (permissoes por role)

bling_tokens (tokens OAuth)
```

## Tabelas e Relacionamentos Detalhados

### users

Armazena usuarios do sistema com diferentes niveis de acesso.

```sql
CREATE TABLE users (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    username VARCHAR(255) UNIQUE,
    password VARCHAR(255) NOT NULL,
    role VARCHAR(255) NOT NULL,
    email_verified_at TIMESTAMP NULL,
    remember_token VARCHAR(100) NULL,
    two_factor_secret TEXT NULL,
    two_factor_recovery_codes TEXT NULL,
    two_factor_confirmed_at TIMESTAMP NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL
);
```

**Colunas:**
- `id`: Identificador unico
- `name`: Nome completo do usuario
- `email`: Email (unico)
- `username`: Nome de usuario (unico)
- `password`: Senha hasheada
- `role`: Role do usuario (admin, manager, sales, developer)
- `two_factor_*`: Campos para autenticacao de dois fatores

**Roles:**
- `admin`: Acesso total ao sistema
- `manager`: Gestao de pedidos e usuarios
- `sales`: Visualizacao e atualizacao de pedidos
- `developer`: Acesso para desenvolvimento

**Indices:**
- PRIMARY KEY (id)
- UNIQUE (email)
- UNIQUE (username)

### order_summary

Tabela principal que armazena o sumario de pedidos importados do Bling.

```sql
CREATE TABLE order_summary (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    bling_id BIGINT UNIQUE NOT NULL,
    numero VARCHAR(255) NOT NULL,
    numero_loja VARCHAR(255) NULL,
    data DATETIME NOT NULL,
    data_saida DATETIME NULL,
    data_prevista DATETIME NULL,
    total_produtos INT DEFAULT 0,
    total DECIMAL(10,2) DEFAULT 0,
    contato_id BIGINT NULL,
    contato_nome VARCHAR(255) NULL,
    contato_tipo_pessoa VARCHAR(255) NULL,
    contato_documento VARCHAR(255) NULL,
    situacao_id BIGINT NULL,
    situacao_valor INT NULL,
    loja_id BIGINT NULL,
    source VARCHAR(255) DEFAULT 'bling',
    data_processed BOOLEAN DEFAULT false,
    external_updated_at DATETIME NULL,
    tracking_code VARCHAR(255) NULL,
    logistics_provider VARCHAR(255) NULL,
    service_type VARCHAR(255) NULL,
    volume DECIMAL(10,2) NULL,
    weight DECIMAL(10,2) NULL,
    raw_order_data JSON NULL,
    bling_data_hash VARCHAR(32) NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL
);
```

**Colunas Principais:**
- `bling_id`: ID do pedido no Bling (unico)
- `numero`: Numero do pedido
- `numero_loja`: Numero da loja / WooCommerce ID
- `data`: Data do pedido
- `total`: Valor total
- `source`: Sistema de origem (pro, atacadista, beauty)
- `situacao_id`: ID da situacao no Bling (12 = cancelado)
- `situacao_valor`: Valor da situacao (1-4)
- `tracking_code`: Codigo de rastreamento
- `bling_data_hash`: Hash MD5 para deteccao de mudancas
- `raw_order_data`: Dados completos do Bling (JSON)

**Indices:**
- PRIMARY KEY (id)
- UNIQUE (bling_id)
- INDEX (numero)
- INDEX (data)
- INDEX (situacao_valor)
- INDEX (source)
- INDEX (data_processed)
- INDEX (external_updated_at)

**Relacionamentos:**
- `hasOne`: order_data
- `hasOne`: invoice
- `hasOne`: order_status
- `hasMany`: order_histories
- `belongsToMany`: sellers

### order_data

Armazena dados detalhados dos pedidos (itens, parcelas, etc).

```sql
CREATE TABLE order_data (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    order_summary_id BIGINT UNIQUE NOT NULL,
    itens JSON NULL,
    parcelas JSON NULL,
    transporte JSON NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    FOREIGN KEY (order_summary_id) REFERENCES order_summary(id) ON DELETE CASCADE
);
```

**Colunas:**
- `order_summary_id`: Referencia para order_summary
- `itens`: Array JSON com itens do pedido
- `parcelas`: Array JSON com parcelas de pagamento
- `transporte`: JSON com dados de transporte

**Indices:**
- PRIMARY KEY (id)
- UNIQUE (order_summary_id)
- FOREIGN KEY (order_summary_id)

### invoices

Armazena notas fiscais (NFe) vinculadas aos pedidos.

```sql
CREATE TABLE invoices (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    order_summary_id BIGINT NOT NULL,
    bling_id BIGINT UNIQUE NOT NULL,
    tipo INT NULL,
    situacao INT NULL,
    numero VARCHAR(255) NULL,
    data_emissao TIMESTAMP NULL,
    data_operacao TIMESTAMP NULL,
    chave_acesso VARCHAR(255) NULL,
    valor_nota DECIMAL(10,2) NULL,
    valor_frete DECIMAL(10,2) NULL,
    xml TEXT NULL,
    link_danfe VARCHAR(255) NULL,
    link_pdf VARCHAR(255) NULL,
    optante_simples_nacional BOOLEAN DEFAULT false,
    numero_pedido_loja VARCHAR(255) NULL,
    serie INT NULL,
    
    -- Dados do contato
    contato_id BIGINT NULL,
    contato_nome VARCHAR(255) NULL,
    contato_documento VARCHAR(255) NULL,
    contato_ie VARCHAR(255) NULL,
    contato_rg VARCHAR(255) NULL,
    contato_telefone VARCHAR(255) NULL,
    contato_email VARCHAR(255) NULL,
    
    -- Endereco do contato
    contato_endereco VARCHAR(255) NULL,
    contato_numero VARCHAR(255) NULL,
    contato_complemento VARCHAR(255) NULL,
    contato_bairro VARCHAR(255) NULL,
    contato_cep VARCHAR(255) NULL,
    contato_municipio VARCHAR(255) NULL,
    contato_uf VARCHAR(255) NULL,
    contato_pais VARCHAR(255) NULL,
    
    -- Dados adicionais
    natureza_operacao_id BIGINT NULL,
    loja_id BIGINT NULL,
    vendedor_id BIGINT NULL,
    
    -- Transporte
    frete_por_conta INT NULL,
    transportador_nome VARCHAR(255) NULL,
    transportador_documento VARCHAR(255) NULL,
    
    -- Etiqueta
    etiqueta_nome VARCHAR(255) NULL,
    etiqueta_endereco VARCHAR(255) NULL,
    etiqueta_numero VARCHAR(255) NULL,
    etiqueta_complemento VARCHAR(255) NULL,
    etiqueta_municipio VARCHAR(255) NULL,
    etiqueta_uf VARCHAR(255) NULL,
    etiqueta_cep VARCHAR(255) NULL,
    etiqueta_bairro VARCHAR(255) NULL,
    
    -- Arrays JSON
    itens JSON NULL,
    parcelas JSON NULL,
    volumes JSON NULL,
    raw_data JSON NULL,
    
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    FOREIGN KEY (order_summary_id) REFERENCES order_summary(id) ON DELETE CASCADE
);
```

**Colunas Principais:**
- `bling_id`: ID da nota fiscal no Bling
- `numero`: Numero da nota fiscal
- `chave_acesso`: Chave de acesso da NFe
- `xml`: XML completo da nota fiscal
- `link_danfe`: Link para DANFE em PDF
- `raw_data`: Dados completos do Bling (JSON)

**Indices:**
- PRIMARY KEY (id)
- UNIQUE (bling_id)
- INDEX (order_summary_id, bling_id)
- INDEX (numero)
- INDEX (data_emissao)
- FOREIGN KEY (order_summary_id)

**Relacionamentos:**
- `belongsTo`: order_summary
- `hasMany`: tracking

### tracking

Armazena dados de rastreamento de volumes.

```sql
CREATE TABLE tracking (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    invoice_id BIGINT NOT NULL,
    volume_id BIGINT NOT NULL,
    codigo_rastreamento VARCHAR(255) NULL,
    servico_id BIGINT NULL,
    descricao TEXT NULL,
    situacao VARCHAR(255) NULL,
    url_rastreamento VARCHAR(255) NULL,
    origem VARCHAR(255) NULL,
    destino VARCHAR(255) NULL,
    ultima_alteracao TIMESTAMP NULL,
    peso DECIMAL(10,2) NULL,
    altura DECIMAL(10,2) NULL,
    largura DECIMAL(10,2) NULL,
    comprimento DECIMAL(10,2) NULL,
    diametro DECIMAL(10,2) NULL,
    raw_data JSON NULL,
    last_sync_at TIMESTAMP NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE CASCADE
);
```

**Colunas:**
- `volume_id`: ID do volume no Bling
- `codigo_rastreamento`: Codigo de rastreamento da transportadora
- `servico_id`: ID do servico no Bling
- `url_rastreamento`: URL para rastreamento
- `raw_data`: Dados completos do Bling (JSON)

**Indices:**
- PRIMARY KEY (id)
- UNIQUE (invoice_id, volume_id)
- INDEX (codigo_rastreamento)
- FOREIGN KEY (invoice_id)

**Relacionamentos:**
- `belongsTo`: invoice

### order_statuses

Controla o status de processamento dos pedidos (separacao, embalagem, coleta).

```sql
CREATE TABLE order_statuses (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    order_summary_id BIGINT NOT NULL,
    picking BOOLEAN DEFAULT false,
    picking_marked_by BIGINT NULL,
    picking_marked_at DATETIME NULL,
    packing BOOLEAN DEFAULT false,
    packing_marked_by BIGINT NULL,
    packing_marked_at DATETIME NULL,
    picked_up BOOLEAN DEFAULT false,
    picked_up_marked_by BIGINT NULL,
    picked_up_marked_at DATETIME NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    FOREIGN KEY (order_summary_id) REFERENCES order_summary(id) ON DELETE CASCADE,
    FOREIGN KEY (picking_marked_by) REFERENCES users(id),
    FOREIGN KEY (packing_marked_by) REFERENCES users(id),
    FOREIGN KEY (picked_up_marked_by) REFERENCES users(id)
);
```

**Colunas:**
- `picking`: Pedido separado (booleano)
- `picking_marked_by`: Usuario que marcou
- `picking_marked_at`: Data/hora da marcacao
- `packing`: Pedido embalado (booleano)
- `packing_marked_by`: Usuario que marcou
- `packing_marked_at`: Data/hora da marcacao
- `picked_up`: Pedido coletado (booleano)
- `picked_up_marked_by`: Usuario que marcou
- `picked_up_marked_at`: Data/hora da marcacao

**Indices:**
- PRIMARY KEY (id)
- FOREIGN KEY (order_summary_id)
- FOREIGN KEY (picking_marked_by)
- FOREIGN KEY (packing_marked_by)
- FOREIGN KEY (picked_up_marked_by)

**Relacionamentos:**
- `belongsTo`: order_summary
- `belongsTo`: users (picking_marked_by)
- `belongsTo`: users (packing_marked_by)
- `belongsTo`: users (picked_up_marked_by)

### order_histories

Registra todas as alteracoes realizadas em pedidos para auditoria.

```sql
CREATE TABLE order_histories (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    order_summary_id BIGINT NOT NULL,
    user_id BIGINT NULL,
    action VARCHAR(255) NOT NULL,
    description TEXT NULL,
    old_value TEXT NULL,
    new_value TEXT NULL,
    metadata JSON NULL,
    worker_name VARCHAR(255) NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    FOREIGN KEY (order_summary_id) REFERENCES order_summary(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);
```

**Colunas:**
- `action`: Tipo de acao (order_imported, invoice_synced, tracking_fetched, etc)
- `description`: Descricao da acao
- `old_value`: Valor anterior
- `new_value`: Novo valor
- `metadata`: Dados adicionais (JSON)
- `worker_name`: Nome do worker que executou a acao

**Indices:**
- PRIMARY KEY (id)
- INDEX (order_summary_id, created_at)
- FOREIGN KEY (order_summary_id)
- FOREIGN KEY (user_id)

**Relacionamentos:**
- `belongsTo`: order_summary
- `belongsTo`: users

### sellers

Armazena informacoes sobre vendedores (importados do WooCommerce).

```sql
CREATE TABLE sellers (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    woocommerce_id BIGINT UNIQUE NOT NULL,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL
);
```

**Colunas:**
- `woocommerce_id`: ID do vendedor no WooCommerce
- `name`: Nome do vendedor
- `email`: Email do vendedor

**Indices:**
- PRIMARY KEY (id)
- UNIQUE (woocommerce_id)

### order_summary_seller (tabela pivot)

Relaciona pedidos com vendedores (many-to-many).

```sql
CREATE TABLE order_summary_seller (
    order_summary_id BIGINT NOT NULL,
    seller_id BIGINT NOT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    FOREIGN KEY (order_summary_id) REFERENCES order_summary(id) ON DELETE CASCADE,
    FOREIGN KEY (seller_id) REFERENCES sellers(id) ON DELETE CASCADE
);
```

### permissions

Define as permissoes disponiveis no sistema.

```sql
CREATE TABLE permissions (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) UNIQUE NOT NULL,
    display_name VARCHAR(255) NOT NULL,
    description TEXT NULL,
    category VARCHAR(255) NULL,
    is_active BOOLEAN DEFAULT true,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL
);
```

**Colunas:**
- `name`: Nome tecnico da permissao (ex: orders.view)
- `display_name`: Nome amigavel
- `description`: Descricao da permissao
- `category`: Categoria (orders, users, permissions, etc)
- `is_active`: Se a permissao esta ativa

**Indices:**
- PRIMARY KEY (id)
- UNIQUE (name)

### role_permissions

Relaciona roles com permissoes.

```sql
CREATE TABLE role_permissions (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    role VARCHAR(255) NOT NULL,
    permission_id BIGINT NOT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE
);
```

**Colunas:**
- `role`: Nome do role (admin, manager, sales, developer)
- `permission_id`: ID da permissao

**Indices:**
- PRIMARY KEY (id)
- UNIQUE (role, permission_id)
- FOREIGN KEY (permission_id)

**Relacionamentos:**
- `belongsTo`: permission

### bling_tokens

Armazena tokens OAuth para autenticacao com a API do Bling.

```sql
CREATE TABLE bling_tokens (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    account_type VARCHAR(255) UNIQUE NOT NULL,
    access_token TEXT NOT NULL,
    refresh_token TEXT NULL,
    expires_at TIMESTAMP NOT NULL,
    scope JSON NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL
);
```

**Colunas:**
- `account_type`: Tipo de conta (pro, atacadista)
- `access_token`: Token de acesso OAuth
- `refresh_token`: Token de refresh OAuth
- `expires_at`: Data de expiracao do token
- `scope`: Escopos do token (JSON)

**Indices:**
- PRIMARY KEY (id)
- UNIQUE (account_type)
- INDEX (account_type, expires_at)

## Indices e Otimizacoes

### Indices Simples
- `order_summary.bling_id` (UNIQUE)
- `order_summary.numero`
- `order_summary.data`
- `order_summary.situacao_valor`
- `order_summary.source`
- `invoices.numero`
- `tracking.codigo_rastreamento`

### Indices Compostos
- `invoices (order_summary_id, bling_id)`
- `tracking (invoice_id, volume_id)` (UNIQUE)
- `order_histories (order_summary_id, created_at)`
- `bling_tokens (account_type, expires_at)`

### Indices de Foreign Keys
Todos os relacionamentos possuem indices automaticos criados pelo banco de dados.

## Estrategia de Migrations

### Nomenclatura
As migrations seguem o padrao:
```
YYYY_MM_DD_HHMMSS_action_description.php
```

Exemplo:
```
2025_01_15_000001_create_order_summary_table.php
```

### Ordem de Execucao
1. Tabelas base sem dependencias (users, permissions, bling_tokens)
2. Tabelas com dependencias simples (order_summary, sellers)
3. Tabelas com dependencias multiplas (invoices, order_statuses, order_histories)
4. Tabelas de relacionamento (order_summary_seller)

### Reversibilidade
Todas as migrations possuem metodo `down()` para reverter alteracoes.

## Seeders e Dados Iniciais

### UserSeeder
Cria usuarios de teste para desenvolvimento:
- Admin: admin@example.com
- Manager: manager@example.com
- Sales: sales@example.com

### PermissionSeeder
Popula tabelas `permissions` e `role_permissions` com permissoes padrao:
- orders.view, orders.edit, orders.import
- users.view, users.create, users.edit, users.delete
- permissions.view, permissions.edit

## Backup e Recuperacao

### Backup Recomendado
```bash
# Dump completo
mysqldump -u usuario -p collmepro_logistica > backup.sql

# Dump apenas estrutura
mysqldump -u usuario -p --no-data collmepro_logistica > structure.sql

# Dump apenas dados
mysqldump -u usuario -p --no-create-info collmepro_logistica > data.sql
```

### Restauracao
```bash
mysql -u usuario -p collmepro_logistica < backup.sql
```

## Manutencao

### Limpeza de Dados Antigos
```sql
-- Deletar historicos com mais de 6 meses
DELETE FROM order_histories WHERE created_at < DATE_SUB(NOW(), INTERVAL 6 MONTH);

-- Deletar pedidos cancelados com mais de 1 ano
DELETE FROM order_summary WHERE situacao_id = 12 AND updated_at < DATE_SUB(NOW(), INTERVAL 1 YEAR);
```

### Otimizacao de Tabelas
```sql
OPTIMIZE TABLE order_summary;
OPTIMIZE TABLE invoices;
OPTIMIZE TABLE order_histories;
```

### Analise de Performance
```sql
-- Verificar indices nao utilizados
SELECT * FROM sys.schema_unused_indexes WHERE object_schema = 'collmepro_logistica';

-- Verificar queries lentas
SELECT * FROM mysql.slow_log WHERE start_time > DATE_SUB(NOW(), INTERVAL 1 DAY);
```

