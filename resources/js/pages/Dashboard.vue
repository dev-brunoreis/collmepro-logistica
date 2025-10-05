<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';
import { ref, watch, reactive } from 'vue';
import { Package, CheckCircle, Clock, Truck, AlertCircle } from 'lucide-vue-next';
import { useOrderBroadcasting } from '@/composables/useBroadcasting';

interface Metrics {
    total_orders: number;
    pending_orders: number;
    picking_orders: number;
    packing_orders: number;
    picked_up_orders: number;
    completed_orders: number;
}

interface Order {
    id: number;
    numero: string;
    contato_nome: string;
    last_action?: string;
    last_user?: string;
    last_time?: string;
    status?: any;
}

interface OrdersByStatus {
    pending: Order[];
    picking: Order[];
    packing: Order[];
    picked_up: Order[];
    completed: Order[];
}

interface Props {
    metrics: Metrics;
    ordersByStatus: OrdersByStatus;
    recentActivity: Order[];
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: dashboard().url,
    },
];

// Broadcasting setup
const { isConnected, lastUpdate } = useOrderBroadcasting();

// Create reactive copy of metrics for real-time updates
const metrics = ref(props.metrics);
const ordersByStatus = ref(props.ordersByStatus);
const recentActivity = ref(props.recentActivity);

// Watch for real-time updates and update dashboard data
watch(lastUpdate, (update) => {
    if (update) {
        console.log('Dashboard received update:', update);
        
        // Update metrics based on the status change
        const action = update.action;
        const status = update.status;
        
        // Find the order in recent activity and update it
        const activityIndex = recentActivity.value.findIndex(order => order.id === update.order.id);
        if (activityIndex !== -1) {
            const order = recentActivity.value[activityIndex];
            if (order.status) {
                order.status[action] = status;
                
                if (status) {
                    order.last_action = action;
                    order.last_user = update.user.name;
                    order.last_time = update.timestamp;
                }
            }
        }
        
        // Update orders by status
        // Remove from current status
        Object.keys(ordersByStatus.value).forEach(statusKey => {
            const index = ordersByStatus.value[statusKey as keyof OrdersByStatus].findIndex(
                order => order.id === update.order.id
            );
            if (index !== -1) {
                ordersByStatus.value[statusKey as keyof OrdersByStatus].splice(index, 1);
            }
        });
        
        // Add to appropriate status based on current state
        // This is a simplified version - in a real implementation you'd need to determine
        // the correct status based on all three flags (picking, packing, picked_up)
        
        console.log('Dashboard updated with new data');
    }
});
</script>

<template>
    <Head title="Dashboard" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
            <!-- Header with broadcasting status -->
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <h1 class="text-2xl font-bold">Dashboard</h1>
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
                    </div>
                </div>
            </div>

            <!-- Metrics Cards -->
            <div class="grid auto-rows-min gap-4 md:grid-cols-3 lg:grid-cols-6">
                <!-- Total Orders -->
                <div class="rounded-xl border border-sidebar-border/70 dark:border-sidebar-border p-6">
                    <div class="flex items-center gap-3">
                        <Package class="h-8 w-8 text-blue-500" />
                        <div>
                            <p class="text-sm text-muted-foreground">Total</p>
                            <p class="text-2xl font-bold">{{ metrics.total_orders }}</p>
                        </div>
                    </div>
                </div>

                <!-- Pending Orders -->
                <div class="rounded-xl border border-sidebar-border/70 dark:border-sidebar-border p-6">
                    <div class="flex items-center gap-3">
                        <Clock class="h-8 w-8 text-yellow-500" />
                        <div>
                            <p class="text-sm text-muted-foreground">Pendentes</p>
                            <p class="text-2xl font-bold">{{ metrics.pending_orders }}</p>
                        </div>
                    </div>
                </div>

                <!-- Picking Orders -->
                <div class="rounded-xl border border-sidebar-border/70 dark:border-sidebar-border p-6">
                    <div class="flex items-center gap-3">
                        <Package class="h-8 w-8 text-orange-500" />
                        <div>
                            <p class="text-sm text-muted-foreground">Separando</p>
                            <p class="text-2xl font-bold">{{ metrics.picking_orders }}</p>
                        </div>
                    </div>
                </div>

                <!-- Packing Orders -->
                <div class="rounded-xl border border-sidebar-border/70 dark:border-sidebar-border p-6">
                    <div class="flex items-center gap-3">
                        <Package class="h-8 w-8 text-purple-500" />
                        <div>
                            <p class="text-sm text-muted-foreground">Embalando</p>
                            <p class="text-2xl font-bold">{{ metrics.packing_orders }}</p>
                        </div>
                    </div>
                </div>

                <!-- Picked Up Orders -->
                <div class="rounded-xl border border-sidebar-border/70 dark:border-sidebar-border p-6">
                    <div class="flex items-center gap-3">
                        <Truck class="h-8 w-8 text-indigo-500" />
                        <div>
                            <p class="text-sm text-muted-foreground">Coletados</p>
                            <p class="text-2xl font-bold">{{ metrics.picked_up_orders }}</p>
                        </div>
                    </div>
                </div>

                <!-- Completed Orders -->
                <div class="rounded-xl border border-sidebar-border/70 dark:border-sidebar-border p-6">
                    <div class="flex items-center gap-3">
                        <CheckCircle class="h-8 w-8 text-green-500" />
                        <div>
                            <p class="text-sm text-muted-foreground">Concluídos</p>
                            <p class="text-2xl font-bold">{{ metrics.completed_orders }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Activity -->
            <div class="rounded-xl border border-sidebar-border/70 dark:border-sidebar-border p-6">
                <h2 class="text-lg font-semibold mb-4">Atividade Recente</h2>
                <div class="space-y-3">
                    <div 
                        v-for="activity in recentActivity" 
                        :key="activity.id"
                        class="flex items-center justify-between p-3 bg-sidebar-border/20 rounded-lg"
                    >
                        <div class="flex items-center gap-3">
                            <div class="h-2 w-2 rounded-full bg-green-500"></div>
                            <div>
                                <p class="font-medium">Pedido {{ activity.numero }}</p>
                                <p class="text-sm text-muted-foreground">{{ activity.contato_nome }}</p>
                            </div>
                        </div>
                        <div class="text-right">
                            <p class="text-sm font-medium">
                                {{ activity.last_action === 'picking' ? 'Separado' : 
                                   activity.last_action === 'packing' ? 'Embalado' : 
                                   activity.last_action === 'picked_up' ? 'Coletado' : '' }}
                            </p>
                            <p class="text-xs text-muted-foreground">{{ activity.last_user }}</p>
                        </div>
                    </div>
                    
                    <div v-if="recentActivity.length === 0" class="text-center py-8 text-muted-foreground">
                        Nenhuma atividade recente
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
