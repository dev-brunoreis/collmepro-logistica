<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { ref } from 'vue';
import { Search, Filter, Eye, CheckCircle, XCircle, Package, Truck, ExternalLink, RefreshCw } from 'lucide-vue-next';
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
    system: string;
    system_display_name: string;
    order_number: string;
    customer_name: string;
    seller_name: string;
    order_date: string;
    formatted_order_date: string;
    nfe_number?: string;
    nfe_pdf_url?: string;
    tracking_code?: string;
    logistics_provider?: string;
    tracking_status?: string;
    tracking_url?: string;
    has_nfe: boolean;
    has_tracking_code: boolean;
    order_status?: OrderStatus;
    latest_invoice?: any;
    latest_label?: any;
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

// Refresh tracking status for a specific order
const refreshTracking = async (order: Order) => {
    if (!order.tracking_code || trackingRefreshLoading.value[order.id]) {
        return; // Skip if no tracking code or already loading
    }

    trackingRefreshLoading.value[order.id] = true;

    try {
        // Use axios which has CSRF token configured automatically
        const response = await axios.post(`/orders/${order.id}/refresh-tracking`);
        
        const data = response.data;

        if (data.success) {
            // Update order in place
            order.tracking_code = data.tracking_code;
            order.logistics_provider = data.logistics_provider;
            order.tracking_status = data.tracking_status;

            console.log('Tracking updated successfully for order', order.order_number);
        } else {
            console.warn('Failed to refresh tracking:', data.message);
        }
    } catch (error: any) {
        console.error('Error refreshing tracking:', error.response?.data || error.message);
    } finally {
        trackingRefreshLoading.value[order.id] = false;
    }
};

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

const updateOrderStatus = (orderId: number, action: string, currentStatus: boolean) => {
    const actionNames = {
        picking: 'Separado',
        packing: 'Embalado',
        picked_up: 'Coletado'
    };
    
    const actionName = actionNames[action as keyof typeof actionNames] || action;
    const newStatus = !currentStatus;
    const statusText = newStatus ? 'marcar como concluído' : 'desmarcar';
    
    const confirmed = confirm(`Tem certeza que deseja ${statusText} o ${actionName}?`);
    
    if (!confirmed) {
        return;
    }
    
    // Store current scroll position
    const scrollPosition = window.pageYOffset || document.documentElement.scrollTop;
    
    router.patch(`/orders/${orderId}`, {
        action,
        status: newStatus,
    }, {
        preserveState: true,
        preserveScroll: true,
        onSuccess: () => {
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
    });
};



const getSystemBadgeClass = (system: string) => {
    switch (system) {
        case 'collmepro':
            return 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300';
        case 'collmepro_wholesale':
            return 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-300';
        case 'collme_beauty':
            return 'bg-pink-100 text-pink-800 dark:bg-pink-900 dark:text-pink-300';
        default:
            return 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-300';
    }
};

const formatDate = (dateString: string) => {
    return new Date(dateString).toLocaleDateString('pt-BR', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
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
                                placeholder="Buscar por número, cliente, vendedor ou tracking..."
                                class="w-full rounded-lg border border-sidebar-border/70 bg-background pl-10 pr-4 py-2 text-sm placeholder:text-muted-foreground focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary dark:border-sidebar-border"
                            />
                        </div>
                    </div>
                    
                    <div class="flex items-center gap-2">
                        <Filter class="h-4 w-4 text-muted-foreground" />
                        <select
                            :value="props.filters?.system || ''"
                            @change="updateFilter('system', ($event.target as HTMLSelectElement).value)"
                            class="rounded-lg border border-sidebar-border/70 bg-background px-3 py-2 text-sm focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary dark:border-sidebar-border"
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
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-muted-foreground mb-1">Status Rastreamento</label>
                        <select
                            :value="props.filters?.tracking_status || ''"
                            @change="updateFilter('tracking_status', ($event.target as HTMLSelectElement).value)"
                            class="w-full rounded-lg border border-sidebar-border/70 bg-background px-3 py-2 text-sm focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary dark:border-sidebar-border"
                        >
                            <option value="">Todos os status</option>
                            <option v-for="(label, value) in props.tracking_statuses || {}" :key="value" :value="value">
                                {{ label }}
                            </option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-muted-foreground mb-1">Transportadora</label>
                        <select
                            :value="props.filters?.logistics_provider || ''"
                            @change="updateFilter('logistics_provider', ($event.target as HTMLSelectElement).value)"
                            class="w-full rounded-lg border border-sidebar-border/70 bg-background px-3 py-2 text-sm focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary dark:border-sidebar-border"
                        >
                            <option value="">Todas as transportadoras</option>
                            <option v-for="(label, value) in props.logistics_providers || {}" :key="value" :value="value">
                                {{ label }}
                            </option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-muted-foreground mb-1">Vendedor</label>
                        <input
                            type="text"
                            :value="props.filters?.seller || ''"
                            @input="updateFilter('seller', ($event.target as HTMLInputElement).value)"
                            placeholder="Nome do vendedor..."
                            class="w-full rounded-lg border border-sidebar-border/70 bg-background px-3 py-2 text-sm placeholder:text-muted-foreground focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary dark:border-sidebar-border"
                        />
                    </div>
                </div>

                <!-- Active filters display -->
                <div v-if="Object.values(props.filters || {}).some(filter => filter !== undefined && filter !== '')" class="text-sm text-muted-foreground">
                    <span v-if="props.filters?.search">Busca: "{{ props.filters.search }}"</span>
                    <span v-if="props.filters?.system"> • Sistema: {{ props.systems?.[props.filters.system] }}</span>
                    <span v-if="props.filters?.tracking_status"> • Status: {{ props.tracking_statuses?.[props.filters.tracking_status] }}</span>
                    <span v-if="props.filters?.logistics_provider"> • Transportadora: {{ props.logistics_providers?.[props.filters.logistics_provider] }}</span>
                    <span v-if="props.filters?.seller"> • Vendedor: "{{ props.filters.seller }}"</span>
                </div>
            </div>

            <!-- Orders Table -->
            <div class="rounded-lg border border-sidebar-border/70 dark:border-sidebar-border">
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="border-b border-sidebar-border/70 dark:border-sidebar-border">
                            <tr>
                                <th class="px-4 py-3 text-left text-sm font-medium text-muted-foreground">Sistema</th>
                                <th class="px-4 py-3 text-left text-sm font-medium text-muted-foreground">Pedido</th>
                                <th class="px-4 py-3 text-left text-sm font-medium text-muted-foreground">Cliente</th>
                                <th class="px-4 py-3 text-left text-sm font-medium text-muted-foreground">Data</th>
                                <th class="px-4 py-3 text-left text-sm font-medium text-muted-foreground">NFE</th>
                                <th class="px-4 py-3 text-left text-sm font-medium text-muted-foreground">Rastreamento</th>
                                <th class="px-4 py-3 text-left text-sm font-medium text-muted-foreground">Transportadora</th>
                                <th class="px-4 py-3 text-left text-sm font-medium text-muted-foreground">Status</th>
                                <th class="px-4 py-3 text-left text-sm font-medium text-muted-foreground">Vendedor</th>
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
                                    <span
                                        class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium"
                                        :class="getSystemBadgeClass(order.system)"
                                    >
                                        {{ order.system_display_name }}
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="font-medium">{{ order.order_number }}</div>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="font-medium">{{ order.customer_name }}</div>
                                </td>
                                <td class="px-4 py-3 text-sm text-muted-foreground">
                                    {{ formatDate(order.order_date) }}
                                </td>
                                <td class="px-4 py-3">
                                    <div v-if="order.has_nfe" class="flex items-center gap-2">
                                        <a
                                            v-if="order.nfe_pdf_url"
                                            :href="order.nfe_pdf_url"
                                            target="_blank"
                                            class="text-sm font-medium text-primary hover:text-primary/80 hover:underline"
                                            :title="`Baixar PDF da NFE ${order.nfe_number}`"
                                        >
                                            {{ order.nfe_number }}
                                        </a>
                                        <span v-else class="text-sm">{{ order.nfe_number }}</span>
                                        <a
                                            v-if="order.nfe_pdf_url"
                                            :href="order.nfe_pdf_url"
                                            target="_blank"
                                            class="text-primary hover:text-primary/80"
                                            :title="`Baixar PDF da NFE ${order.nfe_number}`"
                                        >
                                            <ExternalLink class="h-3 w-3" />
                                        </a>
                                    </div>
                                    <span v-else class="text-sm text-muted-foreground">-</span>
                                </td>
                                <td class="px-4 py-3">
                                    <div v-if="order.has_tracking_code" class="flex items-center gap-2">
                                        <span class="text-sm">{{ order.tracking_code }}</span>
                                        <a 
                                            v-if="order.tracking_url" 
                                            :href="order.tracking_url" 
                                            target="_blank" 
                                            class="inline-flex items-center gap-1 rounded-md px-1 py-0.5 text-xs hover:bg-sidebar-border/70 text-blue-600 dark:text-blue-400"
                                            title="Abrir rastreamento"
                                        >
                                            <ExternalLink class="h-3 w-3" />
                                        </a>
                                    </div>
                                    <span v-else class="text-sm text-muted-foreground">-</span>
                                </td>
                                <td class="px-4 py-3 text-sm text-muted-foreground">
                                    {{ order.logistics_provider || '-' }}
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-2">
                                        <span v-if="order.tracking_status" class="text-sm">{{ order.tracking_status }}</span>
                                        <span v-else class="text-sm text-muted-foreground">-</span>
                                        
                                        <!-- Refresh tracking button - only show if order has actual tracking code -->
                                        <button
                                            v-if="order.tracking_code"
                                            @click="refreshTracking(order)"
                                            :disabled="trackingRefreshLoading[order.id]"
                                            class="p-1 rounded hover:bg-sidebar-accent transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                                            :title="trackingRefreshLoading[order.id] ? 'Atualizando...' : 'Atualizar status de rastreamento'"
                                        >
                                            <RefreshCw 
                                                :class="[
                                                    'h-4 w-4 text-muted-foreground hover:text-foreground transition-colors',
                                                    trackingRefreshLoading[order.id] ? 'animate-spin' : ''
                                                ]"
                                            />
                                        </button>
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-sm text-muted-foreground">
                                    {{ order.seller_name || '-' }}
                                </td>
                                <td class="px-4 py-3">
                                    <button
                                        v-if="canUpdateStatus"
                                        @click="updateOrderStatus(order.id, 'picking', order.order_status?.picking || false)"
                                        class="inline-flex items-center gap-1 rounded-md px-2 py-1 text-sm hover:bg-sidebar-border/70"
                                        :class="order.order_status?.picking ? 'text-green-600 dark:text-green-400' : 'text-muted-foreground'"
                                    >
                                        <CheckCircle v-if="order.order_status?.picking" class="h-3 w-3" />
                                        <XCircle v-else class="h-3 w-3" />
                                        <Package class="h-3 w-3" />
                                    </button>
                                    <div v-else class="inline-flex items-center gap-1 px-2 py-1 text-sm text-muted-foreground">
                                        <CheckCircle v-if="order.order_status?.picking" class="h-3 w-3 text-green-600 dark:text-green-400" />
                                        <XCircle v-else class="h-3 w-3" />
                                        <Package class="h-3 w-3" />
                                    </div>
                                </td>
                                <td class="px-4 py-3">
                                    <button
                                        v-if="canUpdateStatus"
                                        @click="updateOrderStatus(order.id, 'packing', order.order_status?.packing || false)"
                                        class="inline-flex items-center gap-1 rounded-md px-2 py-1 text-sm hover:bg-sidebar-border/70"
                                        :class="order.order_status?.packing ? 'text-green-600 dark:text-green-400' : 'text-muted-foreground'"
                                    >
                                        <CheckCircle v-if="order.order_status?.packing" class="h-3 w-3" />
                                        <XCircle v-else class="h-3 w-3" />
                                        <Package class="h-3 w-3" />
                                    </button>
                                    <div v-else class="inline-flex items-center gap-1 px-2 py-1 text-sm text-muted-foreground">
                                        <CheckCircle v-if="order.order_status?.packing" class="h-3 w-3 text-green-600 dark:text-green-400" />
                                        <XCircle v-else class="h-3 w-3" />
                                        <Package class="h-3 w-3" />
                                    </div>
                                </td>
                                <td class="px-4 py-3">
                                    <button
                                        v-if="canUpdateStatus"
                                        @click="updateOrderStatus(order.id, 'picked_up', order.order_status?.picked_up || false)"
                                        class="inline-flex items-center gap-1 rounded-md px-2 py-1 text-sm hover:bg-sidebar-border/70"
                                        :class="order.order_status?.picked_up ? 'text-green-600 dark:text-green-400' : 'text-muted-foreground'"
                                    >
                                        <CheckCircle v-if="order.order_status?.picked_up" class="h-3 w-3" />
                                        <XCircle v-else class="h-3 w-3" />
                                        <Truck class="h-3 w-3" />
                                    </button>
                                    <div v-else class="inline-flex items-center gap-1 px-2 py-1 text-sm text-muted-foreground">
                                        <CheckCircle v-if="order.order_status?.picked_up" class="h-3 w-3 text-green-600 dark:text-green-400" />
                                        <XCircle v-else class="h-3 w-3" />
                                        <Truck class="h-3 w-3" />
                                    </div>
                                </td>
                                <td class="px-4 py-3">
                                    <Link
                                        :href="`/orders/${order.id}`"
                                        class="inline-flex items-center gap-1 rounded-md px-2 py-1 text-sm text-muted-foreground hover:bg-sidebar-border/70"
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
                <div class="text-sm text-muted-foreground">
                    Mostrando {{ props.orders.from || 0 }} a {{ props.orders.to || 0 }} de {{ props.orders.total || 0 }} resultados
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
    </AppLayout>
</template>
