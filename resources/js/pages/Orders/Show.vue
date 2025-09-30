<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, ExternalLink, Package, Truck, CheckCircle, XCircle } from 'lucide-vue-next';

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

interface StatusHistoryItem {
    id: number;
    status_type: string;
    status_value: boolean;
    user_name: string;
    user_username: string;
    changed_at: string;
    formatted_changed_at: string;
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
    order_status?: OrderStatus;
    invoices?: any[];
    labels?: any[];
    status_history?: StatusHistoryItem[];
}

interface Props {
    order: Order;
}

const props = defineProps<Props>();

const formatDate = (dateString: string) => {
    return new Date(dateString).toLocaleDateString('pt-BR', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
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

const getStatusTypeDisplayName = (statusType: string) => {
    switch (statusType) {
        case 'picking':
            return 'Picking';
        case 'packing':
            return 'Packing';
        case 'picked_up':
            return 'Retirado';
        default:
            return statusType;
    }
};

const getStatusValueDisplay = (statusValue: boolean) => {
    return statusValue ? 'Marcado' : 'Desmarcado';
};

const getStatusValueClass = (statusValue: boolean) => {
    return statusValue 
        ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300'
        : 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300';
};
</script>

<template>
    <Head :title="`Pedido ${props.order.order_number}`" />

    <AppLayout>
        <div class="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
            <!-- Header -->
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-4">
                    <Link
                        href="/orders"
                        class="inline-flex items-center gap-2 rounded-lg border border-sidebar-border/70 px-3 py-2 text-sm font-medium text-foreground hover:bg-sidebar-border/70 dark:border-sidebar-border"
                    >
                        <ArrowLeft class="h-4 w-4" />
                        Voltar aos Pedidos
                    </Link>
                    <h1 class="text-2xl font-bold">Pedido {{ props.order.order_number }}</h1>
                </div>
            </div>

            <!-- Order Details -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Main Info -->
                <div class="lg:col-span-2 space-y-6">
                    <!-- Basic Info -->
                    <div class="rounded-lg border border-sidebar-border/70 p-6 dark:border-sidebar-border">
                        <h2 class="text-lg font-semibold mb-4">Informações do Pedido</h2>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="text-sm font-medium text-muted-foreground">Sistema</label>
                                <div class="mt-1">
                                    <span
                                        class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium"
                                        :class="getSystemBadgeClass(props.order.system)"
                                    >
                                        {{ props.order.system_display_name }}
                                    </span>
                                </div>
                            </div>
                            <div>
                                <label class="text-sm font-medium text-muted-foreground">Número do Pedido</label>
                                <div class="mt-1 text-sm font-medium">{{ props.order.order_number }}</div>
                            </div>
                            <div>
                                <label class="text-sm font-medium text-muted-foreground">Cliente</label>
                                <div class="mt-1 text-sm">{{ props.order.customer_name }}</div>
                            </div>
                            <div>
                                <label class="text-sm font-medium text-muted-foreground">Vendedor</label>
                                <div class="mt-1 text-sm">{{ props.order.seller_name || 'N/A' }}</div>
                            </div>
                            <div>
                                <label class="text-sm font-medium text-muted-foreground">Data do Pedido</label>
                                <div class="mt-1 text-sm">{{ formatDate(props.order.order_date) }}</div>
                            </div>
                        </div>
                    </div>

                    <!-- NFE Info -->
                    <div class="rounded-lg border border-sidebar-border/70 p-6 dark:border-sidebar-border">
                        <h2 class="text-lg font-semibold mb-4">Nota Fiscal</h2>
                        <div v-if="props.order.nfe_number" class="flex items-center gap-4">
                            <div>
                                <label class="text-sm font-medium text-muted-foreground">Número da NFE</label>
                                <div class="mt-1">
                                    <a
                                        v-if="props.order.nfe_pdf_url"
                                        :href="props.order.nfe_pdf_url"
                                        target="_blank"
                                        class="text-sm font-medium text-primary hover:text-primary/80 hover:underline"
                                        :title="`Baixar PDF da NFE ${props.order.nfe_number}`"
                                    >
                                        {{ props.order.nfe_number }}
                                    </a>
                                    <span v-else class="text-sm font-medium">{{ props.order.nfe_number }}</span>
                                </div>
                            </div>
                            <div v-if="props.order.nfe_pdf_url">
                                <a
                                    :href="props.order.nfe_pdf_url"
                                    target="_blank"
                                    class="inline-flex items-center gap-2 rounded-lg bg-primary px-4 py-2 text-sm font-medium text-primary-foreground hover:bg-primary/90"
                                >
                                    <ExternalLink class="h-4 w-4" />
                                    Baixar PDF
                                </a>
                            </div>
                        </div>
                        <div v-else class="text-sm text-muted-foreground">
                            NFE não disponível
                        </div>
                    </div>

                    <!-- Tracking Info -->
                    <div class="rounded-lg border border-sidebar-border/70 p-6 dark:border-sidebar-border">
                        <h2 class="text-lg font-semibold mb-4">Rastreamento</h2>
                        <div v-if="props.order.tracking_code" class="space-y-4">
                            <div>
                                <label class="text-sm font-medium text-muted-foreground">Código de Rastreamento</label>
                                <div class="mt-1 text-sm font-medium">{{ props.order.tracking_code }}</div>
                            </div>
                            <div v-if="props.order.logistics_provider">
                                <label class="text-sm font-medium text-muted-foreground">Transportadora</label>
                                <div class="mt-1 text-sm">{{ props.order.logistics_provider }}</div>
                            </div>
                            <div v-if="props.order.tracking_status">
                                <label class="text-sm font-medium text-muted-foreground">Status</label>
                                <div class="mt-1 text-sm">{{ props.order.tracking_status }}</div>
                            </div>
                        </div>
                        <div v-else class="text-sm text-muted-foreground">
                            Rastreamento não disponível
                        </div>
                    </div>
                </div>

                <!-- Status Sidebar -->
                <div class="space-y-6">
                    <!-- Order Status -->
                    <div class="rounded-lg border border-sidebar-border/70 p-6 dark:border-sidebar-border">
                        <h2 class="text-lg font-semibold mb-4">Status do Pedido</h2>
                        <div class="space-y-4">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <Package class="h-4 w-4 text-muted-foreground" />
                                    <span class="text-sm font-medium">Picking</span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <CheckCircle v-if="props.order.order_status?.picking" class="h-4 w-4 text-green-600" />
                                    <XCircle v-else class="h-4 w-4 text-muted-foreground" />
                                    <span class="text-xs text-muted-foreground">
                                        {{ props.order.order_status?.picking ? 'Concluído' : 'Pendente' }}
                                    </span>
                                </div>
                            </div>

                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <Package class="h-4 w-4 text-muted-foreground" />
                                    <span class="text-sm font-medium">Packing</span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <CheckCircle v-if="props.order.order_status?.packing" class="h-4 w-4 text-green-600" />
                                    <XCircle v-else class="h-4 w-4 text-muted-foreground" />
                                    <span class="text-xs text-muted-foreground">
                                        {{ props.order.order_status?.packing ? 'Concluído' : 'Pendente' }}
                                    </span>
                                </div>
                            </div>

                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <Truck class="h-4 w-4 text-muted-foreground" />
                                    <span class="text-sm font-medium">Retirado</span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <CheckCircle v-if="props.order.order_status?.picked_up" class="h-4 w-4 text-green-600" />
                                    <XCircle v-else class="h-4 w-4 text-muted-foreground" />
                                    <span class="text-xs text-muted-foreground">
                                        {{ props.order.order_status?.picked_up ? 'Concluído' : 'Pendente' }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Invoices -->
                    <div v-if="props.order.invoices && props.order.invoices.length > 0" class="rounded-lg border border-sidebar-border/70 p-6 dark:border-sidebar-border">
                        <h2 class="text-lg font-semibold mb-4">Faturas</h2>
                        <div class="space-y-2">
                            <div v-for="invoice in props.order.invoices" :key="invoice.id" class="text-sm">
                                <div class="font-medium">{{ invoice.invoice_number }}</div>
                                <div class="text-muted-foreground">{{ formatDate(invoice.issued_at) }}</div>
                            </div>
                        </div>
                    </div>

                    <!-- Status History -->
                    <div v-if="props.order.status_history && props.order.status_history.length > 0" class="rounded-lg border border-sidebar-border/70 p-6 dark:border-sidebar-border">
                        <h2 class="text-lg font-semibold mb-4">Histórico de Alterações</h2>
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead>
                                    <tr class="border-b border-sidebar-border/70 dark:border-sidebar-border">
                                        <th class="text-left py-2 font-medium text-muted-foreground">Status</th>
                                        <th class="text-left py-2 font-medium text-muted-foreground">Ação</th>
                                        <th class="text-left py-2 font-medium text-muted-foreground">Usuário</th>
                                        <th class="text-left py-2 font-medium text-muted-foreground">Data/Hora</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr 
                                        v-for="history in props.order.status_history" 
                                        :key="history.id"
                                        class="border-b border-sidebar-border/70 dark:border-sidebar-border last:border-b-0"
                                    >
                                        <td class="py-2">
                                            <span class="font-medium">{{ getStatusTypeDisplayName(history.status_type) }}</span>
                                        </td>
                                        <td class="py-2">
                                            <span 
                                                class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium"
                                                :class="getStatusValueClass(history.status_value)"
                                            >
                                                {{ getStatusValueDisplay(history.status_value) }}
                                            </span>
                                        </td>
                                        <td class="py-2">
                                            <div>
                                                <div class="font-medium">{{ history.user_name }}</div>
                                                <div class="text-xs text-muted-foreground">@{{ history.user_username }}</div>
                                            </div>
                                        </td>
                                        <td class="py-2 text-muted-foreground">
                                            {{ history.formatted_changed_at }}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Labels -->
                    <div v-if="props.order.labels && props.order.labels.length > 0" class="rounded-lg border border-sidebar-border/70 p-6 dark:border-sidebar-border">
                        <h2 class="text-lg font-semibold mb-4">Etiquetas</h2>
                        <div class="space-y-2">
                            <div v-for="label in props.order.labels" :key="label.id" class="text-sm">
                                <div class="font-medium">{{ label.label_number }}</div>
                                <div class="text-muted-foreground">{{ formatDate(label.issued_at) }}</div>
                                <div class="text-xs">
                                    <span
                                        class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium"
                                        :class="{
                                            'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300': label.status === 'posted',
                                            'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-300': label.status === 'issued',
                                            'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-300': !label.status,
                                        }"
                                    >
                                        {{ label.status === 'posted' ? 'Postado' : label.status === 'issued' ? 'Emitido' : 'Pendente' }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
