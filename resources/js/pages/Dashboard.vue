<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import PlaceholderPattern from '../components/PlaceholderPattern.vue';
import KanbanCompact from '../components/KanbanCompact.vue';
import MetricCard from '../components/MetricCard.vue';
import { ref } from 'vue';
import { Package, ShoppingCart, CheckCircle } from 'lucide-vue-next';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
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

const openKanbanFullscreen = () => {
    router.visit('/dashboard/painel');
};
</script>

<template>
    <Head title="Dashboard" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div
            class="flex h-full flex-1 flex-col gap-4 overflow-hidden rounded-xl p-4"
        >
            <!-- Metrics Cards -->
            <div class="grid auto-rows-min gap-4 md:grid-cols-3">
                <MetricCard
                    :value="metrics.total_orders || 0"
                    label="Total de Pedidos"
                    :icon="Package"
                />
                <MetricCard
                    :value="metrics.pending_orders || 0"
                    label="Aguardando"
                    :icon="ShoppingCart"
                />
                <MetricCard
                    :value="metrics.completed_orders || 0"
                    label="Concluídos"
                    :icon="CheckCircle"
                />
            </div>
            
            <!-- Kanban Section -->
            <div
                class="relative flex-1 rounded-xl border border-sidebar-border/70 dark:border-sidebar-border p-4 bg-gradient-to-br from-gray-900 to-gray-800"
            >
                <KanbanCompact 
                    :kanban-data="kanbanData"
                    @open-fullscreen="openKanbanFullscreen"
                />
            </div>
        </div>
    </AppLayout>
</template>
