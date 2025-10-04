<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { ref, onMounted, onUnmounted } from 'vue';
import { Search, Filter, Eye, CheckCircle, XCircle, Package, Truck, ExternalLink, RefreshCw, AlertTriangle, ZoomIn, Download } from 'lucide-vue-next';
import axios from '@/lib/axios';

interface OrderStatus {
    picking: boolean;
    packing: boolean;
    picked_up: boolean;
    picking_marked_by?: string;
    packing_marked_by?: string;
    picked_up_marked_by?: string;
    picking_marked_at?: string;
    packing_marked_at?: string;
    picked_up_marked_at?: string;
}

interface User {
    id: number;
    name: string;
    email: string;
    username: string;
    role: string;
    permissions?: Array<{
        id: number;
        name: string;
        display_name: string;
        description?: string;
    }>;
}

interface Order {
    id: number;
    bling_id: string;
    numero: string;
    numero_loja: string;
    data: string;
    data_saida: string;
    data_prevista: string;
    total_produtos: number;
    total: number;
    contato_id: number;
    contato_nome: string;
    contato_tipo_pessoa: string;
    contato_documento: string;
    situacao_id: number;
    situacao_valor: number;
    loja_id: number;
    source: string;
    created_at: string;
    updated_at: string;
    order_status?: OrderStatus;
    nota_fiscal_id?: number;
    invoice_id?: string;
    invoice_chave_acesso?: string;
    invoice?: any; // raw_invoice_data
    tracking?: any; // raw_tracking_data
    carrier?: string;
    has_tracking_code?: boolean;
    tracking_code?: string;
    tracking_url?: string;
    tracking_status?: string;
    seller_name?: string;
    logistics_provider?: string;
    service_type?: string;
    volume?: number;
    weight?: number;
    raw_order_data?: any;
    raw_invoice_data?: any;
    raw_tracking_data?: any;
}

interface Props {
    orders: {
        data: Order[];
        links: any[];
        from?: number;
        to?: number;
        total?: number;
        current_page?: number;
        last_page?: number;
        per_page?: number;
    };
    filters: {
        search?: string;
        system?: string;
        tracking_status?: string;
        logistics_provider?: string;
        seller?: string;
        picking_status?: string;
        packing_status?: string;
        picked_up_status?: string;
    };
    systems: {
        [key: string]: string;
    };
    tracking_statuses: {
        [key: string]: string;
    };
    logistics_providers: {
        [key: string]: string;
    };
}

const props = defineProps<Props>();
const page = usePage();
const user = page.props.auth.user as User;

// Check if user has permission to update order status
const canUpdateStatus = user?.permissions?.some((p: any) => p.name === 'orders.status.update') || false;

// Check if user has permission to import orders
const canImportOrders = user?.permissions?.some((p: any) => p.name === 'orders.import') || false;

// Loading state for manual fetch
const isFetching = ref(false);

// Loading state for tracking refresh (by order ID)
const trackingRefreshLoading = ref<Record<number, boolean>>({});

// Modal state for status confirmation
const showConfirmModal = ref(false);
const confirmData = ref<{
    orderId: number;
    action: string;
    actionName: string;
    currentStatus: boolean;
    newStatus: boolean;
    orderNumber: string;
    orderData?: Order;
} | null>(null);

// Loading state for status update
const isUpdatingStatus = ref(false);

// Tracking functionality removed as part of simplification

const search = (value: string) => {
    const scrollPosition = window.pageYOffset || document.documentElement.scrollTop;
    
    router.get('/orders', { search: value }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        onSuccess: () => {
            setTimeout(() => {
                window.scrollTo(0, scrollPosition);
            }, 50);
        },
    });
};

const updateFilter = (filterName: string, value: string) => {
    const filters = { ...props.filters };
    if (value === '') {
        delete filters[filterName as keyof typeof filters];
    } else {
        filters[filterName as keyof typeof filters] = value;
    }
    
    const scrollPosition = window.pageYOffset || document.documentElement.scrollTop;
    
    router.get('/orders', filters, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        onSuccess: () => {
            setTimeout(() => {
                window.scrollTo(0, scrollPosition);
            }, 50);
        },
    });
};


const clearFilters = () => {
    const scrollPosition = window.pageYOffset || document.documentElement.scrollTop;
    
    router.get('/orders', {}, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        onSuccess: () => {
            setTimeout(() => {
                window.scrollTo(0, scrollPosition);
            }, 50);
        },
    });
};

const updateOrderStatus = (orderId: number, action: string, currentStatus: boolean, orderNumber: string) => {
    const actionNames = {
        picking: 'Separado',
        packing: 'Embalado',
        picked_up: 'Coletado'
    };
    
    const actionName = actionNames[action as keyof typeof actionNames] || action;
    const newStatus = !currentStatus;
    
    // Find the order data
    const order = props.orders.data.find(o => o.id === orderId);
    
    // Set modal data
    confirmData.value = {
        orderId,
        action,
        actionName,
        currentStatus,
        newStatus,
        orderNumber,
        orderData: order
    };
    
    // Show modal
    showConfirmModal.value = true;
};

const confirmStatusUpdate = () => {
    if (!confirmData.value || isUpdatingStatus.value) return;
    
    const { orderId, action, newStatus, actionName } = confirmData.value;
    
    // Set loading state
    isUpdatingStatus.value = true;
    
    // Store current scroll position
    const scrollPosition = window.pageYOffset || document.documentElement.scrollTop;
    
    router.patch(`/orders/${orderId}`, {
        action,
        status: newStatus,
    }, {
        preserveState: true,
        preserveScroll: true,
        onSuccess: () => {
            // Close modal
            showConfirmModal.value = false;
            confirmData.value = null;
            
            // Restore scroll position after a short delay to ensure DOM is updated
            setTimeout(() => {
                window.scrollTo(0, scrollPosition);
            }, 50);
            
            if (typeof window !== 'undefined' && (window as any).toast) {
                const message = newStatus ? `${actionName} marcado como concluído` : `${actionName} desmarcado`;
                (window as any).toast.success('Status atualizado', message);
            }
        },
        onError: (errors: any) => {
            if (typeof window !== 'undefined' && (window as any).toast) {
                const message = errors.message || 'Erro ao atualizar status';
                (window as any).toast.error('Erro', message);
            }
        },
        onFinish: () => {
            // Clear loading state
            isUpdatingStatus.value = false;
        },
    });
};

const cancelStatusUpdate = () => {
    showConfirmModal.value = false;
    confirmData.value = null;
};

// Handle ESC key to close modal
const handleKeydown = (event: KeyboardEvent) => {
    if (event.key === 'Escape' && showConfirmModal.value) {
        cancelStatusUpdate();
    }
};

// Add event listener when component mounts
onMounted(() => {
    document.addEventListener('keydown', handleKeydown);
});

onUnmounted(() => {
    document.removeEventListener('keydown', handleKeydown);
});



const getSystemBadgeClass = (system: string) => {
    switch (system) {
        case 'collmepro':
            return 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300';
        case 'collmepro_atacadista':
            return 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-300';
        case 'collme_beauty':
            return 'bg-pink-100 text-pink-800 dark:bg-pink-900 dark:text-pink-300';
        default:
            return 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-300';
    }
};

const formatDate = (dateString: string) => {
    if (!dateString) return '-';
    
    try {
        // Handle format: 2025-10-03 00:00:00 (MySQL datetime)
        let date: Date;
        
        if (dateString.includes(' ')) {
            // Format: 2025-10-03 00:00:00
            date = new Date(dateString + 'Z'); // Add Z to treat as UTC
        } else {
            // Fallback for other formats
            date = new Date(dateString);
        }
        
        // Check if date is valid
        if (isNaN(date.getTime())) {
            return '-';
        }
        
        return date.toLocaleDateString('pt-BR', {
            day: '2-digit',
            month: '2-digit',
            year: 'numeric'
        });
    } catch (error) {
        console.warn('Error formatting date:', dateString, error);
        return '-';
    }
};

const fetchOrders = () => {
    if (isFetching.value) return;
    
    isFetching.value = true;
    
    router.post('/orders/fetch', {}, {
        preserveState: true,
        preserveScroll: true,
        onSuccess: () => {
            // Reload the page to show updated orders
            setTimeout(() => {
                router.reload({ only: ['orders'] });
            }, 2000); // Wait 2 seconds for jobs to process
        },
        onError: (errors: any) => {
            if (typeof window !== 'undefined' && (window as any).toast) {
                const message = errors.message || 'Erro ao buscar pedidos';
                (window as any).toast.error('Erro', message);
            }
        },
        onFinish: () => {
            isFetching.value = false;
        }
    });
};

const updatePerPage = (event: Event) => {
    const target = event.target as HTMLSelectElement;
    const perPage = target.value;
    
    const scrollPosition = window.pageYOffset || document.documentElement.scrollTop;
    
    router.get('/orders', { ...props.filters, per_page: perPage }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        onSuccess: () => {
            setTimeout(() => {
                window.scrollTo(0, scrollPosition);
            }, 50);
        },
    });
};

const getCurrentPerPage = () => {
    const url = new URL(window.location.href);
    return url.searchParams.get('per_page') || '100';
};
</script>

<template>
    <Head title="Pedidos" />

    <AppLayout>
        <div class="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
            <div class="flex items-center justify-between">
                <h1 class="text-2xl font-bold">Pedidos</h1>
                <button
                    v-if="canImportOrders"
                    @click="fetchOrders"
                    :disabled="isFetching"
                    class="inline-flex items-center gap-2 rounded-lg bg-primary px-4 py-2 text-sm font-medium text-primary-foreground hover:bg-primary/90 disabled:opacity-50 disabled:cursor-not-allowed"
                >
                    <RefreshCw :class="{ 'animate-spin': isFetching }" class="h-4 w-4" />
                    {{ isFetching ? 'Buscando...' : 'Sincronizar pedidos' }}
                </button>
            </div>

            <!-- Filters -->
            <div class="flex flex-col gap-4 rounded-lg border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                <!-- Search and basic filters -->
                <div class="flex flex-wrap items-center gap-4">
                    <div class="flex-1 min-w-64">
                        <div class="relative">
                            <Search class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                            <input
                                type="text"
                                :value="props.filters?.search || ''"
                                @input="search(($event.target as HTMLInputElement).value)"
                                placeholder="Buscar por pedido, cliente, NFE ou rastreamento..."
                                class="w-full rounded-lg border border-sidebar-border/70 bg-background pl-10 pr-4 py-2 text-sm placeholder:text-muted-foreground focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary dark:border-sidebar-border"
                            />
                        </div>
                    </div>
                    
                    <div class="flex items-center gap-2">
                        <Filter class="h-4 w-4 text-muted-foreground" />
                        <select
                            :value="props.filters?.system || ''"
                            @change="updateFilter('system', ($event.target as HTMLSelectElement).value)"
                            class="min-w-48 rounded-lg border border-sidebar-border/70 bg-background px-4 py-3 text-sm focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary dark:border-sidebar-border"
                        >
                            <option value="">Todos os sistemas</option>
                            <option v-for="(label, value) in props.systems || {}" :key="value" :value="value">
                                {{ label }}
                            </option>
                        </select>
                    </div>

                    <button
                        v-if="Object.values(props.filters || {}).some(filter => filter !== undefined && filter !== '')"
                        @click="clearFilters"
                        class="inline-flex items-center gap-2 rounded-lg border border-sidebar-border/70 px-3 py-2 text-sm font-medium text-foreground hover:bg-sidebar-border/70 dark:border-sidebar-border"
                    >
                        Limpar Filtros
                    </button>
                </div>

                <!-- Advanced filters -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                    <div class="min-w-48">
                        <label class="block text-sm font-medium text-muted-foreground mb-2">Status Rastreamento</label>
                        <select
                            :value="props.filters?.tracking_status || ''"
                            @change="updateFilter('tracking_status', ($event.target as HTMLSelectElement).value)"
                            class="w-full rounded-lg border border-sidebar-border/70 bg-background px-4 py-3 text-sm focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary dark:border-sidebar-border"
                        >
                            <option value="">Todos os status</option>
                            <option v-for="(label, value) in props.tracking_statuses || {}" :key="value" :value="value">
                                {{ label }}
                            </option>
                        </select>
                    </div>
                    <div class="min-w-48">
                        <label class="block text-sm font-medium text-muted-foreground mb-2">Transportadora</label>
                        <select
                            :value="props.filters?.logistics_provider || ''"
                            @change="updateFilter('logistics_provider', ($event.target as HTMLSelectElement).value)"
                            class="w-full rounded-lg border border-sidebar-border/70 bg-background px-4 py-3 text-sm focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary dark:border-sidebar-border"
                        >
                            <option value="">Todas as transportadoras</option>
                            <option v-for="(label, value) in props.logistics_providers || {}" :key="value" :value="value">
                                {{ label }}
                            </option>
                        </select>
                    </div>
                </div>

                <!-- Active filters display -->
                <div v-if="Object.values(props.filters || {}).some(filter => filter !== undefined && filter !== '')" class="text-sm text-muted-foreground">
                    <span v-if="props.filters?.search">Busca: "{{ props.filters.search }}"</span>
                    <span v-if="props.filters?.system"> • Sistema: {{ props.systems?.[props.filters.system] }}</span>
                    <span v-if="props.filters?.tracking_status"> • Status: {{ props.tracking_statuses?.[props.filters.tracking_status] }}</span>
                    <span v-if="props.filters?.logistics_provider"> • Transportadora: {{ props.logistics_providers?.[props.filters.logistics_provider] }}</span>
                </div>
            </div>

            <!-- Pagination Top -->
            <div v-if="props.orders.links?.length > 3" class="flex items-center justify-between mb-4">
                <div class="flex items-center gap-4">
                    <div class="text-sm text-muted-foreground">
                        Mostrando {{ props.orders.from || 0 }} a {{ props.orders.to || 0 }} de {{ props.orders.total || 0 }} resultados
                    </div>
                    <div class="flex items-center gap-2">
                        <label class="text-sm text-muted-foreground">Por página:</label>
                        <select 
                            @change="updatePerPage"
                            :value="getCurrentPerPage()"
                            class="rounded-md border border-sidebar-border/70 bg-background px-2 py-1 text-sm focus:outline-none focus:ring-2 focus:ring-primary/20"
                        >
                            <option value="25">25</option>
                            <option value="50">50</option>
                            <option value="100">100</option>
                            <option value="200">200</option>
                            <option value="500">500</option>
                        </select>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <Link
                        v-for="link in props.orders.links || []"
                        :key="link.label"
                        :href="link.url || '#'"
                        v-html="link.label"
                        class="inline-flex items-center justify-center rounded-md px-3 py-2 text-sm font-medium"
                        :class="{
                            'bg-primary text-primary-foreground': link.active,
                            'text-muted-foreground hover:bg-sidebar-border/70': !link.active && link.url,
                            'text-muted-foreground/50 cursor-not-allowed': !link.url,
                        }"
                    />
                </div>
            </div>

            <!-- Orders Table -->
            <div class="rounded-lg border border-sidebar-border/70 dark:border-sidebar-border">
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="border-b border-sidebar-border/70 dark:border-sidebar-border">
                            <tr>
                                <th class="px-4 py-3 text-left text-sm font-medium text-muted-foreground">Pedido</th>
                                <th class="px-4 py-3 text-left text-sm font-medium text-muted-foreground">Sistema</th>
                                <th class="px-4 py-3 text-left text-sm font-medium text-muted-foreground">Cliente</th>
                                <th class="px-4 py-3 text-left text-sm font-medium text-muted-foreground">Data</th>
                                <th class="px-4 py-3 text-left text-sm font-medium text-muted-foreground">NFE</th>
                                <th class="px-4 py-3 text-left text-sm font-medium text-muted-foreground">Rastreamento</th>
                                <th class="px-4 py-3 text-left text-sm font-medium text-muted-foreground">Transportadora</th>
                                <th class="px-4 py-3 text-left text-sm font-medium text-muted-foreground">Status</th>
                                <th class="px-4 py-3 text-left text-sm font-medium text-muted-foreground">Separado</th>
                                <th class="px-4 py-3 text-left text-sm font-medium text-muted-foreground">Embalado</th>
                                <th class="px-4 py-3 text-left text-sm font-medium text-muted-foreground">Coletado</th>
                                <th class="px-4 py-3 text-left text-sm font-medium text-muted-foreground">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="order in props.orders.data || []"
                                :key="order.id"
                                class="border-b border-sidebar-border/70 dark:border-sidebar-border last:border-b-0 hover:bg-sidebar-border/20"
                            >
                            <td class="px-4 py-3">
                                <div class="font-medium">
                                    <a :href="`https://www.bling.com.br/vendas.php#edit/${order?.bling_id}`" target="_blank" class="text-primary gap-1 font-medium rounded-md text-xs flex items-center hover:bg-sidebar-border/70" :title="`Abrir pedido no Bling`">
                                        <span class="text-sm font-medium">{{ order.numero }}</span>
                                            <ExternalLink class="h-3 w-3" />
                                        </a>
                                    </div>
                                </td>
                                <td class="px-4 py-3">
                                    <span
                                        class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium"
                                        :class="getSystemBadgeClass(order.source)"
                                    >
                                        {{ order.source.toUpperCase() }}
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="font-medium">{{ order.contato_nome }}</div>
                                </td>
                                <td class="px-4 py-3 text-sm text-muted-foreground">
                                    {{ formatDate(order.data) }}
                                </td>
                                <td class="px-4 py-3">
                                    <div v-if="order.invoice?.numero" class="flex flex-col items-center gap-1">
                                        <!-- Número da NFE -->
                                         <a :href="`https://www.bling.com.br/notas.fiscais.php#edit/${order.invoice?.id}`" target="_blank" class="text-primary gap-1 font-small rounded-md  flex items-center hover:bg-sidebar-border/70 " :title="`Abrir nota fiscal no Bling`">
                                            {{ order.invoice.numero }}
                                            <ExternalLink class="h-3 w-3" />
                                        </a>
                                                
                                        <!-- Botões de ação -->
                                        <div class="flex items-center gap-2">
                                            <!-- Visualizar DANFE -->
                                            <a
                                                v-if="order.invoice?.linkDanfe"
                                                :href="order.invoice.linkDanfe"
                                                target="_blank"
                                                class="inline-flex items-center justify-center w-6 h-6 rounded-md bg-blue-100 hover:bg-blue-200 dark:bg-blue-900 dark:hover:bg-blue-800 text-blue-600 dark:text-blue-400 transition-colors"
                                                :title="`Visualizar DANFE ${order.invoice.numero}`"
                                            >
                                                <ZoomIn class="h-3 w-3" />
                                            </a>
                                            
                                            <!-- Download PDF -->
                                            <a
                                                v-if="order.invoice?.linkPDF"
                                                :href="order.invoice.linkPDF"
                                                target="_blank"
                                                class="inline-flex items-center justify-center w-6 h-6 rounded-md bg-green-100 hover:bg-green-200 dark:bg-green-900 dark:hover:bg-green-800 text-green-600 dark:text-green-400 transition-colors"
                                                :title="`Baixar PDF da NFE ${order.invoice.numero}`"
                                            >
                                                <Download class="h-3 w-3" />
                                            </a>
                                        </div>
                                    </div>
                                    <span v-else class="text-sm text-muted-foreground">-</span>
                                </td>
                                <td class="px-4 py-3">
                                    <a 
                                        v-if="order.raw_tracking_data?.rastreamento?.url" 
                                        :href="order.raw_tracking_data?.rastreamento?.url" 
                                        target="_blank" 
                                        class="inline-flex items-center gap-1 font-medium rounded-md px-1 py-0.5 text-xs hover:bg-sidebar-border/70"
                                        title="Abrir rastreamento"
                                    >
                                    <div v-if="order.has_tracking_code" class="flex items-center gap-2">
                                        <span class="text-sm">{{ order.tracking_code }}</span>
                                            <ExternalLink class="h-3 w-3" />
                                        </div>
                                    </a>
                                    <span v-else class="text-sm text-muted-foreground">-</span>
                                </td>
                                <td class="px-4 py-3 text-sm text-muted-foreground">
                                    {{ order.logistics_provider }}
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-2">
                                        <span v-if="order.raw_tracking_data?.rastreamento?.descricao" class="text-sm">{{ order.raw_tracking_data?.rastreamento?.descricao }}</span>
                                        <span v-else class="text-sm text-muted-foreground">-</span>
                                    </div>
                                </td>
                                <td class="px-4 py-3">
                                    <button
                                        v-if="canUpdateStatus"
                                        @click="updateOrderStatus(order.id, 'picking', order.order_status?.picking || false, order.numero)"
                                        class="inline-flex items-center gap-1 rounded-md px-4 py-3 cursor-pointer text-sm hover:bg-sidebar-border/70"
                                        :class="order.order_status?.picking ? 'text-green-600 dark:text-green-400' : 'text-muted-foreground'"
                                        :title="order.order_status?.picking ? 'Desmarcar como separado' :  'Marcar como separado'"
                                    >
                                        <CheckCircle v-if="order.order_status?.picking" class="h-3 w-3" />
                                        <XCircle v-else class="h-3 w-3" />
                                        <Package class="h-3 w-3" />
                                    </button>
                                    <div v-else class="inline-flex items-center gap-1 px-4 py-3 cursor-pointer text-sm text-muted-foreground">
                                        <CheckCircle v-if="order.order_status?.picking" class="h-3 w-3 text-green-600 dark:text-green-400" />
                                        <XCircle v-else class="h-3 w-3" />
                                        <Package class="h-3 w-3" />
                                    </div>
                                </td>
                                <td class="px-4 py-3">
                                    <button
                                        v-if="canUpdateStatus"
                                        @click="updateOrderStatus(order.id, 'packing', order.order_status?.packing || false, order.numero)"
                                        class="inline-flex items-center gap-1 rounded-md px-4 py-3 cursor-pointer text-sm hover:bg-sidebar-border/70"
                                        :class="order.order_status?.packing ? 'text-green-600 dark:text-green-400' : 'text-muted-foreground'"
                                        :title="order.order_status?.packing ? 'Desmarcar como embalado' : 'Marcar como embalado'"
                                    >
                                        <CheckCircle v-if="order.order_status?.packing" class="h-3 w-3" />
                                        <XCircle v-else class="h-3 w-3" />
                                        <Package class="h-3 w-3" />
                                    </button>
                                    <div v-else class="inline-flex items-center gap-1 px-4 py-3 cursor-pointer text-sm text-muted-foreground">
                                        <CheckCircle v-if="order.order_status?.packing" class="h-3 w-3 text-green-600 dark:text-green-400" />
                                        <XCircle v-else class="h-3 w-3" />
                                        <Package class="h-3 w-3" />
                                    </div>
                                </td>
                                <td class="px-4 py-3">
                                    <button
                                        v-if="canUpdateStatus"
                                        @click="updateOrderStatus(order.id, 'picked_up', order.order_status?.picked_up || false, order.numero)"
                                        class="inline-flex items-center gap-1 rounded-md px-4 py-3 cursor-pointer text-sm hover:bg-sidebar-border/70"
                                        :class="order.order_status?.picked_up ? 'text-green-600 dark:text-green-400' : 'text-muted-foreground'"
                                        :title="order.order_status?.picked_up ? 'Desmarcar como coletado' : 'Marcar como coletado'"
                                    >
                                        <CheckCircle v-if="order.order_status?.picked_up" class="h-3 w-3" />
                                        <XCircle v-else class="h-3 w-3" />
                                        <Truck class="h-3 w-3" />
                                    </button>
                                    <div v-else class="inline-flex items-center gap-1 px-4 py-3 cursor-pointer text-sm text-muted-foreground">
                                        <CheckCircle v-if="order.order_status?.picked_up" class="h-3 w-3 text-green-600 dark:text-green-400" />
                                        <XCircle v-else class="h-3 w-3" />
                                        <Truck class="h-3 w-3" />
                                    </div>
                                </td>
                                <td class="px-4 py-3">
                                    <Link
                                        :href="`/orders/${order.id}`"
                                        class="inline-flex items-center gap-1 rounded-md px-4 py-3 text-sm text-muted-foreground hover:bg-sidebar-border/70"
                                        :title="`Ver pedido ${order.numero}`"
                                    >
                                        <Eye class="h-3 w-3" />
                                        Ver
                                    </Link>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Pagination -->
            <div v-if="props.orders.links?.length > 3" class="flex items-center justify-between">
                <div class="flex items-center gap-4">
                    <div class="text-sm text-muted-foreground">
                        Mostrando {{ props.orders.from || 0 }} a {{ props.orders.to || 0 }} de {{ props.orders.total || 0 }} resultados
                    </div>
                    <div class="flex items-center gap-2">
                        <label class="text-sm text-muted-foreground">Por página:</label>
                        <select 
                            @change="updatePerPage"
                            :value="getCurrentPerPage()"
                            class="rounded-md border border-sidebar-border/70 bg-background px-2 py-1 text-sm focus:outline-none focus:ring-2 focus:ring-primary/20"
                        >
                            <option value="25">25</option>
                            <option value="50">50</option>
                            <option value="100">100</option>
                            <option value="200">200</option>
                            <option value="500">500</option>
                        </select>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <Link
                        v-for="link in props.orders.links || []"
                        :key="link.label"
                        :href="link.url || '#'"
                        v-html="link.label"
                        class="inline-flex items-center justify-center rounded-md px-3 py-2 text-sm font-medium"
                        :class="{
                            'bg-primary text-primary-foreground': link.active,
                            'text-muted-foreground hover:bg-sidebar-border/70': !link.active && link.url,
                            'text-muted-foreground/50 cursor-not-allowed': !link.url,
                        }"
                    />
                </div>
            </div>
        </div>

        <!-- Confirmation Modal -->
        <div v-if="showConfirmModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/20 backdrop-blur-md" @click="cancelStatusUpdate">
            <div class="bg-background border border-sidebar-border/70 rounded-xl shadow-2xl max-w-lg w-full mx-4 animate-in fade-in-0 zoom-in-95 duration-200" @click.stop>
                <div class="p-6">
                    <!-- Header -->
                    <div class="flex items-center gap-3 mb-4">
                        <div class="flex-shrink-0 w-10 h-10 rounded-full bg-orange-100 dark:bg-orange-900 flex items-center justify-center">
                            <AlertTriangle class="w-5 h-5 text-orange-600 dark:text-orange-400" />
                        </div>
                        <div>
                            <h3 class="text-lg font-semibold text-foreground">Confirmar Alteração</h3>
                            <p class="text-sm text-muted-foreground">Esta ação não pode ser desfeita</p>
                        </div>
                    </div>

                    <!-- Content -->
                    <div class="mb-6">
                        <p class="text-sm text-foreground mb-4">
                            Você está prestes a 
                            <span class="font-semibold" :class="confirmData?.newStatus ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400'">
                                {{ confirmData?.newStatus ? 'marcar como concluído' : 'desmarcar' }}
                            </span>
                            o status:
                        </p>
                        
                        <!-- Order Information -->
                        <div class="bg-sidebar-border/20 rounded-lg p-4 mb-4">
                            <div class="flex items-center gap-2 mb-2">
                                <span class="font-medium text-foreground">{{ confirmData?.actionName }}</span>
                                <span class="text-muted-foreground">•</span>
                                <span class="text-sm text-muted-foreground">Pedido {{ confirmData?.orderNumber }}</span>
                            </div>
                            
                            <div v-if="confirmData?.orderData" class="grid grid-cols-2 gap-2 text-xs text-muted-foreground">
                                <div>
                                    <span class="font-medium">Cliente:</span> {{ confirmData.orderData.contato_nome }}
                                </div>
                                <div>
                                    <span class="font-medium">Total:</span> R$ {{ confirmData.orderData.total }}
                                </div>
                                <div>
                                    <span class="font-medium">Data:</span> {{ formatDate(confirmData.orderData.data) }}
                                </div>
                                <div>
                                    <span class="font-medium">Sistema:</span> {{ confirmData.orderData.source.toUpperCase() }}
                                </div>
                            </div>
                        </div>
                        
                        <p class="text-sm text-muted-foreground">
                            Tem certeza que deseja continuar?
                        </p>
                    </div>

                    <!-- Actions -->
                    <div class="flex gap-3 justify-end">
                        <button
                            @click="cancelStatusUpdate"
                            class="px-4 py-2 text-sm font-medium cursor-pointer text-muted-foreground hover:text-foreground transition-colors"
                        >
                            Cancelar
                        </button>
                        <button
                            @click="confirmStatusUpdate"
                            :disabled="isUpdatingStatus"
                            class="px-4 py-2 text-sm font-medium cursor-pointer text-white rounded-lg transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                            :class="confirmData?.newStatus ? 'bg-green-600 hover:bg-green-700' : 'bg-red-600 hover:bg-red-700'"
                        >
                            <span v-if="isUpdatingStatus" class="flex items-center gap-2">
                                <RefreshCw class="h-4 w-4 animate-spin" />
                                Processando...
                            </span>
                            <span v-else>
                                {{ confirmData?.newStatus ? 'Confirmar' : 'Desmarcar' }}
                            </span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
