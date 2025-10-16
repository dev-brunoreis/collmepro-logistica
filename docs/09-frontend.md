# Frontend

## Visao Geral

O frontend do sistema e construido com Vue.js 3 e Inertia.js, fornecendo uma experiencia de Single Page Application (SPA) mantendo o roteamento do lado do servidor. A interface e estilizada com Tailwind CSS 4 e utiliza componentes da biblioteca Reka UI.

## Stack Tecnologico Frontend

### Frameworks e Bibliotecas Principais

- **Vue.js 3.5.13**: Framework JavaScript reativo
- **Inertia.js 2.1.0**: Adaptador para SPAs sem API
- **TypeScript 5.2.2**: Tipagem estatica
- **Tailwind CSS 4.1.1**: Framework CSS utility-first
- **Vite 7.0.4**: Build tool e dev server

### Bibliotecas de UI

- **Reka UI 2.4.1**: Componentes UI acessiveis e customizaveis
- **Lucide Vue Next**: Biblioteca de icones
- **Heroicons Vue**: Icones adicionais
- **Class Variance Authority**: Gerenciamento de variantes de componentes
- **Tailwind Merge**: Merge inteligente de classes Tailwind

### Utilitarios

- **VueUse 12.8.2**: Colecao de composables Vue
- **Laravel Echo 2.2.4**: Cliente WebSocket
- **Pusher JS 8.4.0**: Driver para WebSockets

## Estrutura de Diretorios Frontend

```
resources/js/
├── actions/          # Acoes reutilizaveis (API calls)
├── components/       # Componentes Vue
│   ├── ui/          # Componentes base (Button, Input, etc)
│   └── features/    # Componentes especificos
├── composables/      # Composables Vue
├── helpers/          # Funcoes auxiliares
├── layouts/          # Layouts da aplicacao
│   ├── AuthenticatedLayout.vue
│   └── GuestLayout.vue
├── lib/             # Bibliotecas e configuracoes
├── pages/           # Paginas Inertia
│   ├── auth/        # Paginas de autenticacao
│   ├── Kanban/      # Pagina Kanban
│   ├── Orders/      # Paginas de pedidos
│   ├── Permissions/ # Gestao de permissoes
│   ├── Profile/     # Perfil de usuario
│   ├── settings/    # Configuracoes
│   ├── Users/       # Gestao de usuarios
│   ├── Dashboard.vue
│   └── Welcome.vue
├── routes/          # Rotas do Wayfinder
├── types/           # Types TypeScript
├── wayfinder/       # Configuracao Wayfinder
├── app.ts           # Entry point principal
├── echo.ts          # Configuracao Laravel Echo
└── ssr.ts           # Entry point SSR
```

## Sistema de Roteamento (Wayfinder)

Laravel Wayfinder fornece roteamento type-safe para Inertia.js.

### Configuracao

Arquivo: `resources/js/routes/index.ts`

```typescript
import { route } from '@/wayfinder';

// Uso em componentes
const url = route('orders.show', { order: 123 });
// Gera: /dashboard/orders/123
```

### Rotas Disponiveis

- `dashboard`: Dashboard principal
- `orders.index`: Listagem de pedidos
- `orders.show`: Detalhes de pedido
- `users.index`: Listagem de usuarios
- `permissions.index`: Gestao de permissoes
- `profile.edit`: Edicao de perfil

## Layouts

### AuthenticatedLayout

Layout principal para usuarios autenticados.

**Arquivo**: `resources/js/layouts/AuthenticatedLayout.vue`

**Componentes**:
- Header com logo e informacoes do usuario
- Sidebar com menu de navegacao
- Area de conteudo principal
- Notificacoes e alertas

**Menu de Navegacao**:
- Dashboard
- Pedidos
- Usuarios (apenas Admin)
- Permissoes (apenas Admin)
- Perfil

### GuestLayout

Layout para paginas publicas (login, registro).

**Arquivo**: `resources/js/layouts/GuestLayout.vue`

**Componentes**:
- Logo centralizado
- Formularios de autenticacao
- Links de navegacao

## Paginas Principais

### Dashboard

**Arquivo**: `resources/js/pages/Dashboard.vue`

**Funcionalidades**:
- Cards de metricas (total de pedidos, concluidos)
- Indicador de status de sincronizacao
- Visualizacao Kanban simplificada
- Atividades recentes

**Carregamento de Dados**:
```vue
<script setup lang="ts">
import { onMounted, ref } from 'vue';
import axios from 'axios';

const metrics = ref({ total_orders: 0, completed_orders: 0 });

onMounted(async () => {
    const { data } = await axios.get('/dashboard/data');
    metrics.value = data.metrics;
});
</script>
```

### Orders (Listagem)

**Arquivo**: `resources/js/pages/Orders/Index.vue`

**Funcionalidades**:
- Tabela de pedidos com paginacao
- Filtros (sistema, status, data)
- Busca por numero ou cliente
- Ordenacao de colunas
- Acoes rapidas (visualizar, editar status)

**Colunas**:
- Sistema (Pro, Atacadista, Beauty)
- Numero do Pedido
- Cliente
- Data
- Total
- Nota Fiscal
- Rastreamento
- Status (Picking, Packing, Picked Up)

### Orders (Detalhes)

**Arquivo**: `resources/js/pages/Orders/Show.vue`

**Secoes**:
- Informacoes do pedido
- Dados do cliente
- Itens do pedido
- Nota fiscal (com download de PDF)
- Rastreamento (com URL)
- Historico de alteracoes

### Kanban

**Arquivo**: `resources/js/pages/Kanban/Index.vue`

**Funcionalidades**:
- Visualizacao em colunas
- Drag & drop entre colunas
- Filtros por sistema
- Contadores por coluna
- Atualizacao em tempo real

**Colunas**:
1. Pedidos Emitidos
2. Faturamento
3. Checkout
4. Embalagem
5. Etiqueta
6. Coleta Aguardando
7. Coleta Realizada

### Users (Gestao)

**Arquivo**: `resources/js/pages/Users/Index.vue`

**Funcionalidades**:
- Listagem de usuarios
- Criar novo usuario
- Editar usuario
- Deletar usuario
- Filtrar por role

## Componentes UI

### Componentes Base (UI)

Localizacao: `resources/js/components/ui/`

**Componentes Disponiveis**:
- `Button.vue`: Botoes com variantes
- `Input.vue`: Campos de input
- `Select.vue`: Dropdown select
- `Checkbox.vue`: Checkbox
- `Dialog.vue`: Modais
- `Card.vue`: Cards de conteudo
- `Badge.vue`: Badges de status
- `Table.vue`: Tabelas
- `Pagination.vue`: Paginacao

**Exemplo de Uso**:
```vue
<template>
    <Button variant="primary" @click="handleClick">
        Salvar
    </Button>
</template>

<script setup lang="ts">
import Button from '@/components/ui/Button.vue';

const handleClick = () => {
    // Handler
};
</script>
```

### Class Variance Authority

Sistema de variantes para componentes:

```typescript
import { cva } from 'class-variance-authority';

const buttonVariants = cva(
    'inline-flex items-center justify-center rounded-md text-sm font-medium',
    {
        variants: {
            variant: {
                primary: 'bg-blue-600 text-white hover:bg-blue-700',
                secondary: 'bg-gray-200 text-gray-900 hover:bg-gray-300',
                danger: 'bg-red-600 text-white hover:bg-red-700',
            },
            size: {
                sm: 'h-8 px-3',
                md: 'h-10 px-4',
                lg: 'h-12 px-6',
            },
        },
        defaultVariants: {
            variant: 'primary',
            size: 'md',
        },
    }
);
```

## Composables

### useForm

Composable para gerenciamento de formularios com Inertia.

```vue
<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';

const form = useForm({
    name: '',
    email: '',
    password: '',
});

const submit = () => {
    form.post('/users', {
        onSuccess: () => {
            // Sucesso
        },
        onError: () => {
            // Erro
        },
    });
};
</script>
```

### usePage

Composable para acessar dados compartilhados da pagina.

```vue
<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const page = usePage();
const user = computed(() => page.props.auth.user);
const permissions = computed(() => page.props.auth.user?.permissions || []);
</script>
```

### Custom Composables

**usePermissions**:
```typescript
// resources/js/composables/usePermissions.ts
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

export function usePermissions() {
    const page = usePage();
    const permissions = computed(() => page.props.auth.user?.permissions || []);
    
    const hasPermission = (permission: string) => {
        return permissions.value.includes(permission);
    };
    
    return {
        permissions,
        hasPermission,
    };
}
```

**useNotifications**:
```typescript
// resources/js/composables/useNotifications.ts
import { ref } from 'vue';

interface Notification {
    id: string;
    type: 'success' | 'error' | 'warning' | 'info';
    message: string;
}

const notifications = ref<Notification[]>([]);

export function useNotifications() {
    const addNotification = (type: Notification['type'], message: string) => {
        const id = Math.random().toString(36);
        notifications.value.push({ id, type, message });
        
        setTimeout(() => {
            removeNotification(id);
        }, 5000);
    };
    
    const removeNotification = (id: string) => {
        notifications.value = notifications.value.filter(n => n.id !== id);
    };
    
    return {
        notifications,
        addNotification,
        removeNotification,
    };
}
```

## Broadcasting em Tempo Real

### Configuracao Laravel Echo

**Arquivo**: `resources/js/echo.ts`

```typescript
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

window.Echo = new Echo({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY,
    wsHost: import.meta.env.VITE_REVERB_HOST,
    wsPort: import.meta.env.VITE_REVERB_PORT,
    wssPort: import.meta.env.VITE_REVERB_PORT,
    forceTLS: import.meta.env.VITE_REVERB_SCHEME === 'https',
    enabledTransports: ['ws', 'wss'],
});
```

### Escutando Eventos

**No Dashboard**:
```vue
<script setup lang="ts">
import { onMounted, onUnmounted } from 'vue';

onMounted(() => {
    window.Echo.channel('orders')
        .listen('OrderCreated', (e) => {
            console.log('New order:', e.order);
            // Atualizar UI
        })
        .listen('OrderUpdated', (e) => {
            console.log('Order updated:', e.order);
            // Atualizar UI
        });
});

onUnmounted(() => {
    window.Echo.leaveChannel('orders');
});
</script>
```

**No Kanban**:
```vue
<script setup lang="ts">
import { ref, onMounted, onUnmounted } from 'vue';

const orders = ref<any[]>([]);

onMounted(() => {
    window.Echo.channel('orders')
        .listen('OrderCreated', ({ order }) => {
            // Adicionar pedido na coluna apropriada
            addOrderToKanban(order);
        })
        .listen('OrderUpdated', ({ order }) => {
            // Mover pedido entre colunas
            updateOrderInKanban(order);
        });
});

onUnmounted(() => {
    window.Echo.leaveChannel('orders');
});
</script>
```

## State Management

O sistema nao utiliza Vuex ou Pinia. Estado e gerenciado via:

1. **Inertia Shared Data**: Dados globais compartilhados
2. **Composables**: Estado reativo compartilhado
3. **Props/Emits**: Comunicacao entre componentes

## Estilizacao com Tailwind CSS

### Configuracao

**Arquivo**: `tailwind.config.js`

```javascript
export default {
    content: [
        './resources/**/*.blade.php',
        './resources/**/*.js',
        './resources/**/*.vue',
    ],
    theme: {
        extend: {
            colors: {
                primary: {
                    50: '#eff6ff',
                    500: '#3b82f6',
                    900: '#1e3a8a',
                },
            },
        },
    },
    plugins: [],
};
```

### Classes Utilitarias Comuns

```css
/* Layout */
.container
.flex, .grid
.p-4, .m-4

/* Typography */
.text-sm, .text-base, .text-lg
.font-medium, .font-bold

/* Colors */
.bg-blue-600, .text-white
.hover:bg-blue-700

/* Borders */
.border, .rounded-md
.shadow-sm, .shadow-lg

/* Responsive */
.md:flex, .lg:grid-cols-3
```

## TypeScript

### Types Principais

**Arquivo**: `resources/js/types/index.ts`

```typescript
export interface User {
    id: number;
    name: string;
    email: string;
    role: 'admin' | 'manager' | 'sales' | 'developer';
    permissions: string[];
}

export interface Order {
    id: number;
    bling_id: number;
    numero: string;
    data: string;
    total: number;
    contato_nome: string;
    source: 'pro' | 'atacadista' | 'beauty';
    invoice?: Invoice;
    order_status?: OrderStatus;
}

export interface Invoice {
    id: number;
    numero: string;
    chave_acesso: string;
    link_pdf: string;
}

export interface OrderStatus {
    picking: boolean;
    packing: boolean;
    picked_up: boolean;
}
```

## Build e Deploy

### Desenvolvimento

```bash
npm run dev
```

Inicia Vite dev server com HMR (Hot Module Replacement).

### Build Producao

```bash
npm run build
```

Gera arquivos otimizados em `public/build/`.

### Build com SSR

```bash
npm run build:ssr
```

Gera arquivos para Server-Side Rendering.

## Boas Praticas

### Componentes

- Use Composition API (setup script)
- Mantenha componentes pequenos e focados
- Use props tipadas
- Emita eventos customizados para comunicacao

### Performance

- Use `v-memo` para otimizar listas grandes
- Lazy load componentes pesados
- Otimize imagens
- Use Suspense para carregamento assincrono

### Acessibilidade

- Use componentes Reka UI (acessiveis por padrao)
- Adicione labels em inputs
- Use ARIA attributes quando necessario
- Teste com leitor de tela

