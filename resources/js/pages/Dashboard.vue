<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import PlaceholderPattern from '../components/PlaceholderPattern.vue';
import KanbanCompact from '../components/KanbanCompact.vue';
import MetricCard from '../components/MetricCard.vue';
import { ref, onMounted, computed, watch } from 'vue';
import { Package, CheckCircle } from 'lucide-vue-next';
import axios from 'axios';
import { useOrderBroadcasting } from '@/composables/useBroadcasting';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Painel',
        href: dashboard().url,
    },
];

const props = defineProps<{
    metrics: any;
    ordersByStatus: any;
    recentActivity: any;
    kanbanData: any;
}>();

const showKanbanFullscreen = ref(false);
const selectedPeriod = ref(7); // Default: 7 days
const metrics = ref(props.metrics);
const kanbanData = ref(props.kanbanData);
const loading = ref(false);

// Broadcasting setup
const { lastNewOrder } = useOrderBroadcasting();

// Watch for new orders from broadcasting and update total count
watch(lastNewOrder, (newOrderEvent) => {
    if (newOrderEvent && newOrderEvent.order) {
        console.log('Dashboard received new order via broadcast:', newOrderEvent.order);
        
        // Increment total orders count
        metrics.value.total_orders = (metrics.value.total_orders || 0) + 1;
        
        console.log('Dashboard total orders updated:', metrics.value.total_orders);
    }
});

const openKanbanFullscreen = () => {
    router.visit('/dashboard/painel');
};

const fetchDashboardData = async () => {
    loading.value = true;
    try {
        const response = await axios.get('/dashboard/data', {
            params: {
                days_back: selectedPeriod.value
            }
        });
        
        metrics.value = response.data.metrics;
        kanbanData.value = response.data.kanbanData;
        
        console.log('Dashboard data updated:', {
            period: selectedPeriod.value,
            totalOrders: metrics.value.total_orders,
            completedOrders: metrics.value.completed_orders,
            kanbanColumns: Object.keys(kanbanData.value).map(key => ({
                name: key,
                count: kanbanData.value[key]?.length || 0
            }))
        });
    } catch (error) {
        console.error('Error fetching dashboard data:', error);
    } finally {
        // Small delay to show loading animation
        setTimeout(() => {
            loading.value = false;
        }, 500);
    }
};

onMounted(() => {
    fetchDashboardData();
});
</script>

<template>
    <Head title="Dashboard" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div
            class="flex h-full flex-1 flex-col gap-4 overflow-hidden rounded-xl p-4"
        >
            <!-- Period Filter -->
            <div class="flex items-center gap-4">
                <div class="flex items-center gap-2">
                    <label for="period-select" class="text-sm font-medium text-foreground">
                        Período:
                    </label>
                    <select 
                        id="period-select"
                        v-model="selectedPeriod" 
                        @change="fetchDashboardData"
                        :disabled="loading"
                        class="px-3 py-2 bg-card border border-border rounded-lg text-foreground focus:ring-2 focus:ring-ring focus:border-transparent disabled:opacity-50"
                    >
                        <option value="7">Últimos 7 dias</option>
                        <option value="15">Últimos 15 dias</option>
                        <option value="30">Últimos 30 dias</option>
                        <option value="60">Últimos 60 dias</option>
                    </select>
                </div>
                
                <div v-if="loading" class="text-sm text-muted-foreground">
                    Carregando...
                </div>
            </div>

            <!-- Metrics Cards -->
            <div class="grid auto-rows-min gap-4 md:grid-cols-2">
                <MetricCard
                    :value="metrics.total_orders || 0"
                    label="Total de Pedidos"
                    :icon="Package"
                />
                <MetricCard
                    :value="metrics.completed_orders || 0"
                    label="Concluídos"
                    :icon="CheckCircle"
                />
            </div>
            
            <!-- Kanban Section -->
            <div
                class="relative flex-1 rounded-xl border border-border p-4 bg-card"
            >
                <!-- Loading Overlay -->
                <div v-if="loading" class="absolute inset-0 bg-background/90 backdrop-blur-sm flex items-center justify-center z-50 rounded-xl">
                    <div class="text-center">
                        <div class="w-16 h-16 border-4 border-muted border-t-primary rounded-full animate-spin mx-auto mb-4"></div>
                        <h3 class="text-xl font-bold text-foreground mb-2">Carregando dados...</h3>
                        <p class="text-sm text-muted-foreground">
                            Período: {{ selectedPeriod }} dias
                        </p>
                    </div>
                </div>
                
                <KanbanCompact 
                    :kanban-data="kanbanData"
                    :selected-period="selectedPeriod"
                    @open-fullscreen="openKanbanFullscreen"
                />
            </div>
        </div>
    </AppLayout>
</template>
