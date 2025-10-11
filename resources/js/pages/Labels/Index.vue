<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import { Search, Filter, Download, Calendar, Tag, CheckCircle, XCircle, FileSpreadsheet, FileText } from 'lucide-vue-next';
import { BreadcrumbItem } from '@/types';
import { dashboard } from '@/routes';

interface Label {
    id: number;
    bling_label_id: number;
    tracking_code: string | null;
    format: 'PDF' | 'ZPL';
    issue_date: string;
    link: string | null;
    observation: string | null;
    is_posted: boolean;
    status_text: string;
    order_summary?: {
        id: number;
        numero: string;
        contato_nome: string;
    };
}

interface Props {
    labels: {
        data: Label[];
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
        start_date?: string;
        end_date?: string;
        status?: string;
        today?: boolean;
    };
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Etiquetas',
        href: '/dashboard/labels',
    }
];

// Form data for filters
const form = ref({
    search: props.filters.search || '',
    start_date: props.filters.start_date || '',
    end_date: props.filters.end_date || '',
    status: props.filters.status || '',
    today: props.filters.today || false,
    per_page: props.labels.per_page || 50,
});

// Show/hide filters
const showFilters = ref(false);

// Loading states
const isExporting = ref(false);

// Watch for form changes and apply filters
watch(form, () => {
    applyFilters();
}, { deep: true });

// Apply filters with debounce for search
let searchTimeout: NodeJS.Timeout;
const applyFilters = () => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        router.get('/dashboard/labels', {
            search: form.value.search,
            start_date: form.value.start_date,
            end_date: form.value.end_date,
            status: form.value.status,
            today: form.value.today ? '1' : '',
            per_page: form.value.per_page,
        }, {
            preserveState: true,
            preserveScroll: true,
        });
    }, form.value.search ? 300 : 0);
};

// Clear all filters
const clearFilters = () => {
    form.value = {
        search: '',
        start_date: '',
        end_date: '',
        status: '',
        today: false,
        per_page: 50,
    };
};

// Quick filter: Labels generated today
const filterToday = () => {
    form.value.today = !form.value.today;
    if (form.value.today) {
        form.value.start_date = '';
        form.value.end_date = '';
    }
};

// Export to XLS
const exportToXls = () => {
    isExporting.value = true;
    
    const params = new URLSearchParams({
        format: 'xls',
        search: form.value.search,
        start_date: form.value.start_date,
        end_date: form.value.end_date,
        status: form.value.status,
        today: form.value.today ? '1' : '',
    });
    
    window.location.href = `/dashboard/labels/export?${params.toString()}`;
    
    setTimeout(() => {
        isExporting.value = false;
    }, 2000);
};

// Export to PDF
const exportToPdf = () => {
    isExporting.value = true;
    
    const params = new URLSearchParams({
        format: 'pdf',
        search: form.value.search,
        start_date: form.value.start_date,
        end_date: form.value.end_date,
        status: form.value.status,
        today: form.value.today ? '1' : '',
    });
    
    window.location.href = `/dashboard/labels/export?${params.toString()}`;
    
    setTimeout(() => {
        isExporting.value = false;
    }, 2000);
};

// Format date for display
const formatDate = (dateString: string) => {
    if (!dateString) return 'N/A';
    
    try {
        let date: Date;
        
        // Handle ISO format - extract date part only to avoid timezone conversion
        if (dateString.includes('T')) {
            const datePart = dateString.split('T')[0];
            const [year, month, day] = datePart.split('-');
            date = new Date(parseInt(year), parseInt(month) - 1, parseInt(day));
        } else {
            date = new Date(dateString);
        }
        
        return date.toLocaleDateString('pt-BR', {
            day: '2-digit',
            month: '2-digit',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        });
    } catch (error) {
        console.warn('Error formatting date:', dateString, error);
        return 'N/A';
    }
};

// Get status badge color
const getStatusColor = (isPosted: boolean) => {
    return isPosted ? 'bg-green-500/20 text-green-400' : 'bg-red-500/20 text-red-400';
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Etiquetas" />

        <div class="py-6">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <!-- Header -->
                <div class="flex justify-between items-center mb-6">
                    <div>
                        <h1 class="text-2xl font-bold text-white">Etiquetas</h1>
                        <p class="text-gray-400 text-sm mt-1">
                            Gerenciamento de etiquetas de envio
                        </p>
                    </div>
                    
                    <div class="flex gap-2">
                        <button
                            @click="exportToXls"
                            :disabled="isExporting"
                            class="inline-flex items-center px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded-lg transition-colors disabled:opacity-50"
                        >
                            <FileSpreadsheet class="w-4 h-4 mr-2" />
                            Exportar XLS
                        </button>
                        <button
                            @click="exportToPdf"
                            :disabled="isExporting"
                            class="inline-flex items-center px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded-lg transition-colors disabled:opacity-50"
                        >
                            <FileText class="w-4 h-4 mr-2" />
                            Exportar PDF
                        </button>
                    </div>
                </div>

                <!-- Search and Filters -->
                <div class="bg-gray-800 rounded-lg p-4 mb-6">
                    <div class="flex flex-col md:flex-row gap-4">
                        <!-- Search -->
                        <div class="flex-1">
                            <div class="relative">
                                <Search class="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400 w-5 h-5" />
                                <input
                                    v-model="form.search"
                                    type="text"
                                    placeholder="Buscar por número do pedido, etiqueta, observação..."
                                    class="w-full pl-10 pr-4 py-2 bg-gray-900 border border-gray-700 rounded-lg text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                                />
                            </div>
                        </div>

                        <!-- Quick Filter: Today -->
                        <button
                            @click="filterToday"
                            :class="[
                                'px-4 py-2 rounded-lg transition-colors flex items-center gap-2',
                                form.today
                                    ? 'bg-indigo-600 text-white'
                                    : 'bg-gray-900 text-gray-300 hover:bg-gray-700'
                            ]"
                        >
                            <Calendar class="w-4 h-4" />
                            Geradas Hoje
                        </button>

                        <!-- Toggle Filters -->
                        <button
                            @click="showFilters = !showFilters"
                            class="px-4 py-2 bg-gray-900 hover:bg-gray-700 text-white rounded-lg transition-colors flex items-center gap-2"
                        >
                            <Filter class="w-4 h-4" />
                            Filtros
                        </button>
                    </div>

                    <!-- Advanced Filters -->
                    <div v-if="showFilters" class="mt-4 pt-4 border-t border-gray-700">
                        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                            <!-- Start Date -->
                            <div>
                                <label class="block text-sm font-medium text-gray-300 mb-1">
                                    Data Inicial
                                </label>
                                <input
                                    v-model="form.start_date"
                                    type="date"
                                    :disabled="form.today"
                                    class="w-full px-3 py-2 bg-gray-900 border border-gray-700 rounded-lg text-white focus:outline-none focus:ring-2 focus:ring-indigo-500 disabled:opacity-50"
                                />
                            </div>

                            <!-- End Date -->
                            <div>
                                <label class="block text-sm font-medium text-gray-300 mb-1">
                                    Data Final
                                </label>
                                <input
                                    v-model="form.end_date"
                                    type="date"
                                    :disabled="form.today"
                                    class="w-full px-3 py-2 bg-gray-900 border border-gray-700 rounded-lg text-white focus:outline-none focus:ring-2 focus:ring-indigo-500 disabled:opacity-50"
                                />
                            </div>

                            <!-- Status Filter -->
                            <div>
                                <label class="block text-sm font-medium text-gray-300 mb-1">
                                    Status
                                </label>
                                <select
                                    v-model="form.status"
                                    class="w-full px-3 py-2 bg-gray-900 border border-gray-700 rounded-lg text-white focus:outline-none focus:ring-2 focus:ring-indigo-500"
                                >
                                    <option value="">Todos</option>
                                    <option value="posted">Postado</option>
                                    <option value="not_posted">Não Postado</option>
                                </select>
                            </div>

                            <!-- Per Page -->
                            <div>
                                <label class="block text-sm font-medium text-gray-300 mb-1">
                                    Por Página
                                </label>
                                <select
                                    v-model.number="form.per_page"
                                    class="w-full px-3 py-2 bg-gray-900 border border-gray-700 rounded-lg text-white focus:outline-none focus:ring-2 focus:ring-indigo-500"
                                >
                                    <option :value="25">25</option>
                                    <option :value="50">50</option>
                                    <option :value="100">100</option>
                                    <option :value="200">200</option>
                                </select>
                            </div>
                        </div>

                        <!-- Clear Filters Button -->
                        <div class="mt-4">
                            <button
                                @click="clearFilters"
                                class="px-4 py-2 text-sm text-gray-400 hover:text-white transition-colors"
                            >
                                Limpar Filtros
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Results Info -->
                <div class="flex justify-between items-center mb-4 text-sm text-gray-400">
                    <div>
                        Mostrando {{ labels.from ?? 0 }} - {{ labels.to ?? 0 }} de {{ labels.total ?? 0 }} etiquetas
                    </div>
                </div>

                <!-- Table -->
                <div class="bg-gray-800 rounded-lg overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead class="bg-gray-900">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">
                                        Número do Pedido
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">
                                        Número da Etiqueta
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">
                                        Data de Emissão
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">
                                        Formato
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">
                                        Status
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">
                                        Ações
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-700">
                                <tr
                                    v-for="label in labels.data"
                                    :key="label.id"
                                    class="hover:bg-gray-700/50 transition-colors"
                                >
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-white">
                                            {{ label.order_summary?.numero || 'N/A' }}
                                        </div>
                                        <div class="text-xs text-gray-400">
                                            {{ label.order_summary?.contato_nome || '' }}
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-white font-mono">
                                            {{ label.tracking_code || label.bling_label_id }}
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-300">
                                        {{ formatDate(label.issue_date) }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="px-2 py-1 text-xs rounded-full bg-indigo-500/20 text-indigo-400">
                                            {{ label.format }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span
                                            :class="[
                                                'inline-flex items-center px-2 py-1 text-xs rounded-full',
                                                getStatusColor(label.is_posted)
                                            ]"
                                        >
                                            <component
                                                :is="label.is_posted ? CheckCircle : XCircle"
                                                class="w-3 h-3 mr-1"
                                            />
                                            {{ label.status_text }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                                        <a
                                            v-if="label.link"
                                            :href="label.link"
                                            target="_blank"
                                            class="text-indigo-400 hover:text-indigo-300 transition-colors inline-flex items-center"
                                        >
                                            <Download class="w-4 h-4 mr-1" />
                                            Download
                                        </a>
                                        <span v-else class="text-gray-500">
                                            Sem link
                                        </span>
                                    </td>
                                </tr>

                                <!-- Empty State -->
                                <tr v-if="labels.data.length === 0">
                                    <td colspan="6" class="px-6 py-12 text-center">
                                        <Tag class="w-12 h-12 mx-auto text-gray-600 mb-3" />
                                        <p class="text-gray-400">Nenhuma etiqueta encontrada</p>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Pagination -->
                <div v-if="labels.links && labels.links.length > 3" class="mt-6 flex justify-center">
                    <nav class="flex gap-2">
                        <template v-for="(link, index) in labels.links" :key="index">
                            <button
                                v-if="link.url"
                                @click="router.visit(link.url)"
                                :class="[
                                    'px-4 py-2 rounded-lg transition-colors',
                                    link.active
                                        ? 'bg-indigo-600 text-white'
                                        : 'bg-gray-800 text-gray-300 hover:bg-gray-700'
                                ]"
                                v-html="link.label"
                            />
                            <span
                                v-else
                                :class="[
                                    'px-4 py-2 rounded-lg',
                                    'bg-gray-900 text-gray-600 cursor-not-allowed'
                                ]"
                                v-html="link.label"
                            />
                        </template>
                    </nav>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

