<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { ref, onMounted, onUnmounted, watch, reactive } from 'vue';
import { Search, Filter, Eye, CheckCircle, XCircle, Package, Truck, ExternalLink, RefreshCw, AlertTriangle, ZoomIn, Download, Loader2 } from 'lucide-vue-next';
import axios from '@/lib/axios';
import { useOrderBroadcasting } from '@/composables/useBroadcasting';
import { BreadcrumbItem } from '@/types';
import { dashboard } from '@/routes';
import orders from '@/routes/orders';
// import orders from '@/routes/orders'; // Removed as file doesn't exist

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
    [key: string]: any; // Index signature to allow dynamic property access
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
    invoice?: any; // Invoice relationship
    tracking?: any; // Tracking relationship array
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
    systems_with_colors: {
        [key: string]: {
            name: string;
            color: string;
        };
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
const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Pedidos',
        href: orders.index.url(),
    }
];

// Broadcasting setup
const { isConnected, lastUpdate, connectToOrdersChannel } = useOrderBroadcasting();

// Use the original props data and create a reactive trigger for updates
const ordersData = ref(props.orders.data);
const updateTrigger = ref(0);

// Watch for real-time updates and force interface refresh
watch(lastUpdate, (update) => {
    if (update) {
        // Find and update the order data directly
        const orderIndex = ordersData.value.findIndex(order => order.id === update.order.id);
        if (orderIndex !== -1) {
            const order = ordersData.value[orderIndex];
            
            // Ensure order_status exists
            if (!order.order_status) {
                order.order_status = {
                    picking: false,
                    packing: false,
                    picked_up: false
                };
            }
            
            // Update the specific status
            order.order_status[update.action] = update.status;
            
            if (update.status) {
                // Set the marked by and marked at fields
                order.order_status[`${update.action}_marked_by`] = update.user.name;
                order.order_status[`${update.action}_marked_at`] = update.timestamp;
            } else {
                // Clear the marked by and marked at fields
                order.order_status[`${update.action}_marked_by`] = null;
                order.order_status[`${update.action}_marked_at`] = null;
            }
            
            // Force reactivity update
            updateTrigger.value++;
        }
        
        // Show notification to user
        if (update.user.id !== user.id) {
            const actionName = update.action_name;
            const statusText = update.status ? 'marcado como concluído' : 'desmarcado';
            console.log(`${update.user.name} ${statusText} ${actionName} no pedido ${update.order.numero}`);
        }
    }
});

// Watch for connection status changes and attempt reconnection
watch(isConnected, (connected) => {
    if (!connected) {
        console.log('Connection lost, attempting to reconnect...');
        // Try to reconnect after a short delay
        setTimeout(() => {
            connectToOrdersChannel();
        }, 2000);
    }
});

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

// Search input value
const searchValue = ref(props.filters?.search || '');

// Loading state for search
const isSearching = ref(false);

const search = () => {
    if (isSearching.value || !searchValue.value.trim()) return;
    
    isSearching.value = true;
    const scrollPosition = window.pageYOffset || document.documentElement.scrollTop;
    
    router.get('/dashboard/orders', { search: searchValue.value.trim() }, {
        preserveState: false,
        preserveScroll: true,
        replace: true,
        onSuccess: () => {
            setTimeout(() => {
                window.scrollTo(0, scrollPosition);
            }, 50);
        },
        onFinish: () => {
            isSearching.value = false;
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
    
    router.get('/dashboard/orders', filters, {
        preserveState: false,
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
    searchValue.value = '';
    const scrollPosition = window.pageYOffset || document.documentElement.scrollTop;
    
    router.get('/dashboard/orders', {}, {
        preserveState: false,
        preserveScroll: true,
        replace: true,
        onSuccess: () => {
            setTimeout(() => {
                window.scrollTo(0, scrollPosition);
            }, 50);
        },
    });
};

const isStatusButtonDisabled = (order: Order, action: string): boolean => {
    // Disable all buttons for cancelled orders
    if (order.situacao_id === 12) return true;
    
    const status = order.order_status;
    const hasInvoice = !!(order.invoice_id || order.invoice?.numero);
    const hasTracking = !!(order.tracking_code || (order.tracking && order.tracking.length > 0));
    
    switch (action) {
        case 'picking':
            // Can't mark without invoice
            if (!status?.picking && !hasInvoice) return true;
            // Can't unmark if packing is true
            if (status?.picking && status?.packing) return true;
            return false;
            
        case 'packing':
            // Can't mark without picking
            if (!status?.packing && !status?.picking) return true;
            // Can't unmark if picked_up is true
            if (status?.packing && status?.picked_up) return true;
            return false;
            
        case 'picked_up':
            // Can't mark without packing
            if (!status?.picked_up && !status?.packing) return true;
            // Can't mark without tracking code
            if (!status?.picked_up && !hasTracking) return true;
            return false;
    }
    return false;
};

const getStatusButtonTooltip = (order: Order, action: string): string => {
    // Show cancellation message for cancelled orders
    if (order.situacao_id === 12) {
        return 'Pedido cancelado - não é possível modificar status';
    }
    
    const status = order.order_status;
    const hasInvoice = !!(order.invoice_id || order.invoice?.numero);
    const hasTracking = !!(order.tracking_code || (order.tracking && order.tracking.length > 0));
    
    switch (action) {
        case 'picking':
            if (!status?.picking && !hasInvoice) return 'Requer nota fiscal para marcar como separado';
            if (status?.picking && status?.packing) return 'Não é possível desmarcar enquanto embalado estiver marcado';
            return status?.picking ? 'Desmarcar como separado' : 'Marcar como separado';
            
        case 'packing':
            if (!status?.packing && !status?.picking) return 'Requer separado marcado primeiro';
            if (status?.packing && status?.picked_up) return 'Não é possível desmarcar enquanto coletado estiver marcado';
            return status?.packing ? 'Desmarcar como embalado' : 'Marcar como embalado';
            
        case 'picked_up':
            if (!status?.picked_up && !status?.packing) return 'Requer embalado marcado primeiro';
            if (!status?.picked_up && !hasTracking) return 'Requer código de rastreamento para marcar como coletado';
            return status?.picked_up ? 'Desmarcar como coletado' : 'Marcar como coletado';
    }
    return '';
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
    const order = ordersData.value.find(o => o.id === orderId);
    
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
    
    router.patch(`/dashboard/orders/${orderId}`, {
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



const getSystemBadgeClass = (order: Order) => {
    // Try to find the system by loja_id from invoice first
    if (order.invoice?.loja_id) {
        const lojaId = order.invoice.loja_id.toString();
        const systemConfig = props.systems_with_colors?.[lojaId];
        if (systemConfig) {
            return systemConfig.color;
        }
    }
    
    // Fallback to old logic for backward compatibility
    switch (order.source) {
        case 'collmepro':
            return 'bg-blue-600 text-white border border-blue-700';
        case 'collmepro_atacadista':
            return 'bg-green-600 text-white border border-green-700';
        case 'collme_beauty':
            return 'bg-yellow-400 text-gray-900 border border-yellow-500';
        default:
            return 'bg-gray-100 text-gray-800 border border-gray-200';
    }
};

const formatDate = (dateString: string) => {
    if (!dateString) return '-';
    
    try {
        // Handle format: 2025-10-03 00:00:00 (MySQL datetime)
        let date: Date;
        
        if (dateString.includes(' ')) {
            // Format: 2025-10-03 00:00:00 - extract just the date part
            const datePart = dateString.split(' ')[0];
            const [year, month, day] = datePart.split('-');
            date = new Date(parseInt(year), parseInt(month) - 1, parseInt(day));
        } else if (dateString.includes('T')) {
            // Handle ISO format - extract date part only to avoid timezone conversion
            const datePart = dateString.split('T')[0];
            const [year, month, day] = datePart.split('-');
            date = new Date(parseInt(year), parseInt(month) - 1, parseInt(day));
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
    
    router.post('/dashboard/orders/fetch', {}, {
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
    
    router.get('/dashboard/orders', { ...props.filters, per_page: perPage }, {
        preserveState: false,
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

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <h1 class="text-2xl font-bold">Pedidos</h1>
                    <!-- Real-time connection indicator -->
                    <div class="flex items-center gap-2">
                        <div 
                            :class="[
                                'h-2 w-2 rounded-full',
                                isConnected ? 'bg-green-500' : 'bg-red-500'
                            ]"
                        ></div>
                        <span class="text-xs text-muted-foreground">
                            {{ isConnected ? 'Tempo real ativo' : 'Sem conexão' }}
                        </span>
                        <button
                            v-if="!isConnected"
                            @click="connectToOrdersChannel"
                            class="ml-2 text-xs text-blue-600 hover:text-blue-800 underline"
                            title="Tentar reconectar"
                        >
                            Reconectar
                        </button>
                    </div>
                </div>
            </div>

            <!-- Filters -->
            <div class="flex flex-col gap-4 rounded-lg border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                <!-- Search and basic filters -->
                <div class="flex flex-col lg:flex-row lg:flex-wrap items-stretch lg:items-center gap-4">
                    <div class="flex-1 min-w-0">
                        <div class="flex flex-col sm:flex-row gap-2">
                            <div class="relative flex-1">
                                <Search class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                                <input
                                    type="text"
                                    v-model="searchValue"
                                    @keyup.enter="search"
                                    placeholder="Buscar por pedido, cliente, NFE ou rastreamento..."
                                    class="w-full rounded-lg border border-sidebar-border/70 bg-background pl-10 pr-4 py-2 text-sm placeholder:text-muted-foreground focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary dark:border-sidebar-border"
                                />
                            </div>
                            <button
                                @click="search"
                                :disabled="isSearching || !searchValue.trim()"
                                class="inline-flex items-center justify-center cursor-pointer gap-2 rounded-lg bg-primary px-4 py-2 text-sm font-medium text-primary-foreground hover:bg-primary/90 disabled:opacity-50 disabled:cursor-not-allowed whitespace-nowrap"
                            >
                                <Loader2 v-if="isSearching" class="h-4 w-4 animate-spin" />
                                <Search v-else class="h-4 w-4" />
                                <span class="hidden sm:inline">{{ isSearching ? 'Buscando...' : 'Buscar' }}</span>
                            </button>
                        </div>
                    </div>
                    
                    <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2">
                        <div class="flex items-center gap-2">
                            <Filter class="h-4 w-4 text-muted-foreground" />
                            <select
                                :value="props.filters?.system || ''"
                                @change="updateFilter('system', ($event.target as HTMLSelectElement).value)"
                                class="w-full sm:min-w-48 rounded-lg border border-sidebar-border/70 bg-background px-4 py-3 text-sm focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary dark:border-sidebar-border"
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
                            class="inline-flex items-center justify-center gap-2 rounded-lg border border-sidebar-border/70 px-3 py-2 text-sm font-medium text-foreground hover:bg-sidebar-border/70 dark:border-sidebar-border whitespace-nowrap"
                        >
                            <Filter class="h-4 w-4" />
                            <span class="hidden sm:inline">Limpar Filtros</span>
                            <span class="sm:hidden">Limpar</span>
                        </button>
                    </div>
                </div>

                <!-- Advanced filters -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-muted-foreground mb-2">Status Rastreamento</label>
                        <select
                            :value="props.filters?.tracking_status || ''"
                            @change="updateFilter('tracking_status', ($event.target as HTMLSelectElement).value)"
                            class="w-full rounded-lg border border-sidebar-border/70 bg-background px-4 py-3 text-sm focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary dark:border-sidebar-border"
                        >
                            <option value="">Selecione um status</option>
                            <option v-for="(label, value) in props.tracking_statuses || {}" :key="value" :value="value">
                                {{ label }}
                            </option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-muted-foreground mb-2">Transportadora</label>
                        <select
                            :value="props.filters?.logistics_provider || ''"
                            @change="updateFilter('logistics_provider', ($event.target as HTMLSelectElement).value)"
                            class="w-full rounded-lg border border-sidebar-border/70 bg-background px-4 py-3 text-sm focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary dark:border-sidebar-border"
                        >
                            <option value="">Selecione uma transportadora</option>
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
            <div v-if="props.orders.links?.length > 3" class="mb-4">
                <div class="hidden md:flex items-center justify-between mb-2">
                    <div class="text-sm text-muted-foreground">
                        Mostrando {{ props.orders.from || 0 }} a {{ props.orders.to || 0 }} de {{ props.orders.total || 0 }} resultados
                    </div>
                </div>
                <div class="flex items-center justify-center overflow-x-auto">
                    <div class="flex items-center gap-1 md:gap-2">
                        <Link
                            v-for="link in props.orders.links || []"
                            :key="link.label"
                            :href="link.url || '#'"
                            v-html="link.label"
                            class="inline-flex items-center justify-center rounded-md px-2 py-2 md:px-3 text-sm font-medium whitespace-nowrap"
                            :class="{
                                'bg-primary text-primary-foreground': link.active,
                                'text-muted-foreground hover:bg-sidebar-border/70': !link.active && link.url,
                                'text-muted-foreground/50 cursor-not-allowed': !link.url,
                            }"
                        />
                    </div>
                </div>
            </div>

            <!-- Orders Table -->
            <div class="rounded-lg border border-sidebar-border/70 dark:border-sidebar-border">
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[1200px]">
                        <thead class="border-b border-sidebar-border/70 dark:border-sidebar-border">
                            <tr>
                                <th class="px-2 sm:px-4 py-3 text-center text-sm font-medium text-muted-foreground whitespace-nowrap">Pedido</th>
                                <th class="px-2 sm:px-4 py-3 text-center text-sm font-medium text-muted-foreground whitespace-nowrap">Sistema</th>
                                <th class="px-2 sm:px-4 py-3 text-center text-sm font-medium text-muted-foreground whitespace-nowrap min-w-[120px]">Cliente</th>
                                <th class="px-2 sm:px-4 py-3 text-center text-sm font-medium text-muted-foreground whitespace-nowrap">Data</th>
                                <th class="px-2 sm:px-4 py-3 text-center text-sm font-medium text-muted-foreground whitespace-nowrap min-w-[100px]">NFE</th>
                                <th class="px-2 sm:px-4 py-3 text-center text-sm font-medium text-muted-foreground whitespace-nowrap min-w-[120px]">Rastreamento</th>
                                <th class="px-2 sm:px-4 py-3 text-center text-sm font-medium text-muted-foreground whitespace-nowrap min-w-[100px]">Transportadora</th>
                                <th class="px-2 sm:px-4 py-3 text-center text-sm font-medium text-muted-foreground whitespace-nowrap">Status</th>
                                <th class="px-2 sm:px-4 py-3 text-center text-sm font-medium text-muted-foreground whitespace-nowrap">Separado</th>
                                <th class="px-2 sm:px-4 py-3 text-center text-sm font-medium text-muted-foreground whitespace-nowrap">Embalado</th>
                                <th class="px-2 sm:px-4 py-3 text-center text-sm font-medium text-muted-foreground whitespace-nowrap">Coletado</th>
                                <th class="px-2 sm:px-4 py-3 text-center text-sm font-medium text-muted-foreground whitespace-nowrap">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Empty state -->
                            <tr v-if="!ordersData || ordersData.length === 0">
                                <td colspan="12" class="px-4 py-12 text-center">
                                    <div class="flex flex-col items-center gap-4">
                                        <div class="w-16 h-16 rounded-full bg-muted/20 flex items-center justify-center">
                                            <Package class="w-8 h-8 text-muted-foreground" />
                                        </div>
                                        <div class="text-center">
                                            <h3 class="text-lg font-medium text-foreground mb-2">
                                                Nenhum pedido encontrado
                                            </h3>
                                            <p class="text-sm text-muted-foreground mb-4">
                                                Não há pedidos que correspondam aos filtros aplicados.
                                            </p>
                                            <div class="flex flex-col gap-2 text-xs text-muted-foreground">
                                                <p v-if="Object.values(props.filters || {}).some(filter => filter !== undefined && filter !== '')">
                                                    Tente ajustar os filtros ou limpar todos os filtros para ver mais resultados.
                                                </p>
                                                <p v-else>
                                                    Execute a sincronização para importar pedidos do Bling.
                                                </p>
                                            </div>
                                        </div>
                                        <button
                                            v-if="Object.values(props.filters || {}).some(filter => filter !== undefined && filter !== '')"
                                            @click="clearFilters"
                                            class="inline-flex items-center gap-2 rounded-lg border border-sidebar-border/70 px-4 py-2 text-sm font-medium text-foreground hover:bg-sidebar-border/70 dark:border-sidebar-border"
                                        >
                                            <Filter class="h-4 w-4" />
                                            Limpar Filtros
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            
                            <!-- Orders data -->
                            <tr
                                v-for="order in ordersData || []"
                                :key="`${order.id}-${updateTrigger}`"
                                class="border-b border-sidebar-border/70 dark:border-sidebar-border last:border-b-0 hover:bg-sidebar-border/20"
                            >
                            <td class="px-2 sm:px-4 py-3 text-center">
                                <div class="font-medium">
                                    <a :href="`https://www.bling.com.br/vendas.php#edit/${order?.bling_id}`" target="_blank" class="text-foreground gap-1 font-medium rounded-md text-xs flex items-center justify-center hover:bg-sidebar-border/70" :title="`Abrir pedido no Bling`">
                                        <span class="text-sm">{{ order.numero }}</span>
                                            <ExternalLink class="h-3 w-3" />
                                        </a>
                                    </div>
                                </td>
                                <td class="px-2 sm:px-4 py-3 text-center">
                                    <span
                                        class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium"
                                        :class="getSystemBadgeClass(order)"
                                    >
                                        {{ order.source.toUpperCase() }}
                                    </span>
                                </td>
                                <td class="px-2 sm:px-4 py-3 text-center">
                                    <div class="text-sm truncate max-w-[120px]" :title="order.contato_nome">{{ order.contato_nome }}</div>
                                </td>
                                <td class="px-2 sm:px-4 py-3 text-sm text-muted-foreground text-center">
                                    {{ formatDate(order.data) }}
                                </td>
                                <td class="px-2 sm:px-4 py-3 text-center">
                                    <div v-if="order.invoice?.numero" class="flex flex-col items-center gap-1">
                                        <!-- Número da NFE -->
                                         <a :href="order.invoice?.link_pdf" target="_blank" class="text-foreground gap-1 font-small rounded-md flex items-center justify-center hover:bg-sidebar-border/70" :title="`Abrir nota fiscal no Bling`">
                                            <span class="text-sm">{{ order.invoice.numero }}</span>
                                            <ExternalLink class="h-3 w-3" />
                                        </a>
                                         <span class="text-xs">R$ {{ order.total }}</span>       
                                        <!-- Botões de ação -->
                                        <div class="flex items-center gap-1">
                                            <!-- Visualizar DANFE -->
                                            <a
                                                v-if="order.invoice?.linkDanfe"
                                                :href="order.invoice.linkDanfe"
                                                target="_blank"
                                                class="inline-flex items-center justify-center w-5 h-5 rounded-md bg-blue-100 hover:bg-blue-200 dark:bg-blue-900 dark:hover:bg-blue-800 text-blue-600 dark:text-blue-400 transition-colors"
                                                :title="`Visualizar DANFE ${order.invoice.numero}`"
                                            >
                                                <ZoomIn class="h-3 w-3" />
                                            </a>
                                            
                                            <!-- Download PDF -->
                                            <a
                                                v-if="order.invoice?.linkPDF"
                                                :href="order.invoice.linkPDF"
                                                target="_blank"
                                                class="inline-flex items-center justify-center w-5 h-5 rounded-md bg-green-100 hover:bg-green-200 dark:bg-green-900 dark:hover:bg-green-800 text-green-600 dark:text-green-400 transition-colors"
                                                :title="`Baixar PDF da NFE ${order.invoice.numero}`"
                                            >
                                                <Download class="h-3 w-3" />
                                            </a>
                                        </div>
                                    </div>
                                    <span v-else class="text-sm text-muted-foreground">-</span>
                                </td>
                                <td class="px-2 sm:px-4 py-3 text-center">
                                    <div v-if="order.has_tracking_code" class="flex items-center justify-center gap-1">
                                        <a 
                                            v-if="order.tracking_url" 
                                            :href="order.tracking_url" 
                                            target="_blank" 
                                            class="inline-flex items-center gap-1 font-medium rounded-md px-1 py-0.5 text-xs hover:bg-sidebar-border/70"
                                            title="Abrir rastreamento"
                                        >
                                            <span class="text-sm truncate max-w-[80px]">{{ order.tracking_code }}</span>
                                            <ExternalLink class="h-3 w-3 flex-shrink-0" />
                                        </a>
                                        <span v-else class="text-sm truncate max-w-[100px]">{{ order.tracking_code }}</span>
                                    </div>
                                    <span v-else class="text-sm text-muted-foreground">-</span>
                                </td>
                                <td class="px-2 sm:px-4 py-3 text-center text-sm text-muted-foreground">
                                    <div class="truncate max-w-[100px]" :title="order.logistics_provider">{{ order.logistics_provider }}</div>
                                </td>
                                <td class="px-2 sm:px-4 py-3 text-center">
                                    <div v-if="order.situacao_id === 12" class="flex items-center justify-center">
                                        <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300">
                                            CANCELADO
                                        </span>
                                    </div>
                                    <div v-else class="flex items-center justify-center">
                                        <span v-if="order.tracking && order.tracking[0]?.descricao" class="text-xs truncate max-w-[80px]" :title="order.tracking[0].descricao">{{ order.tracking[0].descricao }}</span>
                                        <span v-else class="text-sm text-muted-foreground">-</span>
                                    </div>
                                </td>
                                <td class="px-2 sm:px-4 py-3 text-center">
                                    <button
                                        v-if="canUpdateStatus"
                                        @click="updateOrderStatus(order.id, 'picking', order.order_status?.picking || false, order.numero)"
                                        :disabled="isStatusButtonDisabled(order, 'picking')"
                                        class="inline-flex items-center gap-1 rounded-md px-2 py-2 text-sm transition-colors"
                                        :class="[
                                            order.order_status?.picking ? 'text-green-600 dark:text-green-400' : 'text-muted-foreground',
                                            isStatusButtonDisabled(order, 'picking') 
                                                ? 'opacity-40 cursor-not-allowed' 
                                                : 'cursor-pointer hover:bg-sidebar-border/70'
                                        ]"
                                        :title="getStatusButtonTooltip(order, 'picking')"
                                    >
                                        <CheckCircle v-if="order.order_status?.picking" class="h-4 w-4" />
                                        <XCircle v-else class="h-4 w-4" />
                                        <Package class="h-4 w-4" />
                                    </button>
                                    <div v-else class="inline-flex items-center gap-1 px-2 py-2 text-sm text-muted-foreground">
                                        <CheckCircle v-if="order.order_status?.picking" class="h-4 w-4 text-green-600 dark:text-green-400" />
                                        <XCircle v-else class="h-4 w-4" />
                                        <Package class="h-4 w-4" />
                                    </div>
                                </td>
                                <td class="px-2 sm:px-4 py-3 text-center">
                                    <button
                                        v-if="canUpdateStatus"
                                        @click="updateOrderStatus(order.id, 'packing', order.order_status?.packing || false, order.numero)"
                                        :disabled="isStatusButtonDisabled(order, 'packing')"
                                        class="inline-flex items-center gap-1 rounded-md px-2 py-2 text-sm transition-colors"
                                        :class="[
                                            order.order_status?.packing ? 'text-green-600 dark:text-green-400' : 'text-muted-foreground',
                                            isStatusButtonDisabled(order, 'packing') 
                                                ? 'opacity-40 cursor-not-allowed' 
                                                : 'cursor-pointer hover:bg-sidebar-border/70'
                                        ]"
                                        :title="getStatusButtonTooltip(order, 'packing')"
                                    >
                                        <CheckCircle v-if="order.order_status?.packing" class="h-4 w-4" />
                                        <XCircle v-else class="h-4 w-4" />
                                        <Package class="h-4 w-4" />
                                    </button>
                                    <div v-else class="inline-flex items-center gap-1 px-2 py-2 text-sm text-muted-foreground">
                                        <CheckCircle v-if="order.order_status?.packing" class="h-4 w-4 text-green-600 dark:text-green-400" />
                                        <XCircle v-else class="h-4 w-4" />
                                        <Package class="h-4 w-4" />
                                    </div>
                                </td>
                                <td class="px-2 sm:px-4 py-3 text-center">
                                    <button
                                        v-if="canUpdateStatus"
                                        @click="updateOrderStatus(order.id, 'picked_up', order.order_status?.picked_up || false, order.numero)"
                                        :disabled="isStatusButtonDisabled(order, 'picked_up')"
                                        class="inline-flex items-center gap-1 rounded-md px-2 py-2 text-sm transition-colors"
                                        :class="[
                                            order.order_status?.picked_up ? 'text-green-600 dark:text-green-400' : 'text-muted-foreground',
                                            isStatusButtonDisabled(order, 'picked_up') 
                                                ? 'opacity-40 cursor-not-allowed' 
                                                : 'cursor-pointer hover:bg-sidebar-border/70'
                                        ]"
                                        :title="getStatusButtonTooltip(order, 'picked_up')"
                                    >
                                        <CheckCircle v-if="order.order_status?.picked_up" class="h-4 w-4" />
                                        <XCircle v-else class="h-4 w-4" />
                                        <Truck class="h-4 w-4" />
                                    </button>
                                    <div v-else class="inline-flex items-center gap-1 px-2 py-2 text-sm text-muted-foreground">
                                        <CheckCircle v-if="order.order_status?.picked_up" class="h-4 w-4 text-green-600 dark:text-green-400" />
                                        <XCircle v-else class="h-4 w-4" />
                                        <Truck class="h-4 w-4" />
                                    </div>
                                </td>
                                <td class="px-2 sm:px-4 py-3 text-center">
                                    <Link
                                        :href="`/dashboard/orders/${order.id}`"
                                        class="inline-flex items-center gap-1 rounded-md px-2 py-2 text-sm text-muted-foreground hover:bg-sidebar-border/70"
                                        :title="`Ver pedido ${order.numero}`"
                                    >
                                        <Eye class="h-4 w-4" />
                                        <span class="hidden sm:inline">Ver</span>
                                    </Link>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Pagination -->
            <div v-if="props.orders.links?.length > 3">
                <div class="hidden md:flex items-center justify-center mb-2">
                    <div class="text-sm text-muted-foreground">
                        Mostrando {{ props.orders.from || 0 }} a {{ props.orders.to || 0 }} de {{ props.orders.total || 0 }} resultados
                    </div>
                </div>
                <div class="flex items-center justify-center overflow-x-auto">
                    <div class="flex items-center gap-1 md:gap-2">
                        <Link
                            v-for="link in props.orders.links || []"
                            :key="link.label"
                            :href="link.url || '#'"
                            v-html="link.label"
                            class="inline-flex items-center justify-center rounded-md px-2 py-2 md:px-3 text-sm font-medium whitespace-nowrap"
                            :class="{
                                'bg-primary text-primary-foreground': link.active,
                                'text-muted-foreground hover:bg-sidebar-border/70': !link.active && link.url,
                                'text-muted-foreground/50 cursor-not-allowed': !link.url,
                            }"
                        />
                    </div>
                </div>
            </div>
        </div>

        <!-- Confirmation Modal -->
        <div v-if="showConfirmModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/20 backdrop-blur-md p-4" @click="cancelStatusUpdate">
            <div class="bg-background border border-sidebar-border/70 rounded-xl shadow-2xl max-w-lg w-full max-h-[90vh] overflow-y-auto animate-in fade-in-0 zoom-in-95 duration-200" @click.stop>
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
                            <div class="flex flex-col sm:flex-row sm:items-center gap-2 mb-2">
                                <span class="font-medium text-foreground">{{ confirmData?.actionName }}</span>
                                <span class="text-muted-foreground hidden sm:inline">•</span>
                                <span class="text-sm text-muted-foreground">Pedido {{ confirmData?.orderNumber }}</span>
                            </div>
                            
                            <div v-if="confirmData?.orderData" class="grid grid-cols-1 sm:grid-cols-2 gap-2 text-xs text-muted-foreground">
                                <div>
                                    <span class="font-medium">Cliente:</span> 
                                    <div class="truncate">{{ confirmData.orderData.contato_nome }}</div>
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
                    <div class="flex flex-col sm:flex-row gap-3 justify-end">
                        <button
                            @click="cancelStatusUpdate"
                            class="px-4 py-2 text-sm font-medium cursor-pointer text-muted-foreground hover:text-foreground transition-colors order-2 sm:order-1"
                        >
                            Cancelar
                        </button>
                        <button
                            @click="confirmStatusUpdate"
                            :disabled="isUpdatingStatus"
                            class="px-4 py-2 text-sm font-medium cursor-pointer text-white rounded-lg transition-colors disabled:opacity-50 disabled:cursor-not-allowed order-1 sm:order-2"
                            :class="confirmData?.newStatus ? 'bg-green-600 hover:bg-green-700' : 'bg-red-600 hover:bg-red-700'"
                        >
                            <span v-if="isUpdatingStatus" class="flex items-center justify-center gap-2">
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
