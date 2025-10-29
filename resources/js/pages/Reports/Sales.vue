<template>
   <Head title="Relatório de Vendas" />

  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
    <!-- Page Header -->
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-bold text-foreground">Relatório de Vendas</h1>
        <p class="text-muted-foreground">Análise de vendas por produto e período</p>
      </div>
    </div>

    <!-- Filters -->
    <div class="rounded-lg border border-sidebar-border/70 dark:border-sidebar-border p-4">
      <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <!-- Search Filter -->
        <div>
          <label class="block text-sm font-medium text-foreground mb-2">Buscar Produto</label>
          <input 
            type="text" 
            v-model="filters.search" 
            placeholder="Digite o nome do produto..."
            class="w-full px-3 py-2 border border-input rounded-md bg-background text-foreground"
            @input="applyFilters"
          />
        </div>

        <!-- Source Filter -->
        <div>
          <label class="block text-sm font-medium text-foreground mb-2">Sistema</label>
          <select 
            v-model="filters.source" 
            class="w-full px-3 py-2 border border-input rounded-md bg-background text-foreground"
            @change="applyFilters"
          >
            <option value="">Todos</option>
            <option value="Pro">Pro</option>
            <option value="Atacadista">Atacadista</option>
          </select>
        </div>

        <!-- Period Filter -->
        <div>
          <label class="block text-sm font-medium text-foreground mb-2">Período</label>
          <select 
            v-model="filters.months_back" 
            class="w-full px-3 py-2 border border-input rounded-md bg-background text-foreground"
            @change="updateDateRange"
          >
            <option value="1">Último mês</option>
            <option value="3">Últimos 3 meses</option>
            <option value="6">Últimos 6 meses</option>
            <option value="12">Último ano</option>
            <option value="24">Últimos 2 anos</option>
          </select>
        </div>

        <!-- Per Page -->
        <div>
          <label class="block text-sm font-medium text-foreground mb-2">Por Página</label>
          <select 
            v-model="filters.per_page" 
            class="w-full px-3 py-2 border border-input rounded-md bg-background text-foreground"
            @change="applyFilters"
          >
            <option value="10">10</option>
            <option value="25">25</option>
            <option value="50">50</option>
            <option value="100">100</option>
          </select>
        </div>
      </div>

      <!-- Action Buttons -->
      <div class="flex gap-2 mt-4">
        <button 
          @click="applyFilters"
          :disabled="loading"
          class="px-4 py-2 bg-primary text-primary-foreground rounded-md hover:bg-primary/90 disabled:opacity-50"
        >
          <span v-if="loading" class="animate-spin mr-2">⟳</span>
          Aplicar Filtros
        </button>
        <button 
          @click="resetFilters"
          :disabled="loading"
          class="px-4 py-2 border border-input text-foreground rounded-md hover:bg-muted disabled:opacity-50"
        >
          Limpar
        </button>
      </div>
    </div>

    <!-- Results Summary -->
    <div v-if="products.data && Object.keys(products.data).length > 0" class="text-sm text-muted-foreground">
      Mostrando {{ Object.keys(products.data).length }} de {{ products.total }} produtos
      <span v-if="filters.months_back">
        • Período: {{ getPeriodDescription() }}
      </span>
    </div>

    <!-- Loading State -->
    <div v-if="loading" class="flex items-center justify-center py-12">
      <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-primary"></div>
      <span class="ml-2 text-muted-foreground">Carregando relatório...</span>
    </div>

    <!-- Error State -->
    <div v-if="error" class="rounded-lg border border-destructive/50 bg-destructive/10 p-4">
      <p class="text-destructive">{{ error }}</p>
    </div>


    <!-- No Data State -->
    <div v-if="products.data && Object.keys(products.data).length === 0 && !loading" class="text-center py-12">
      <div class="text-muted-foreground">
        <svg class="mx-auto h-12 w-12 text-muted-foreground/50 mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
        </svg>
        <h3 class="text-lg font-medium text-foreground mb-2">Nenhum produto encontrado</h3>
        <p class="text-muted-foreground">Tente ajustar os filtros para ver mais resultados.</p>
      </div>
    </div>

    <!-- Sales Report Table -->
    <div v-if="products.data && Object.keys(products.data).length > 0 && !loading" class="rounded-lg border border-sidebar-border/70 dark:border-sidebar-border">
      <div>
        <!-- Table Header -->
        <div class="table-header">
          <div class="header-cell product-col">Produto</div>
          <div class="header-cell system-col">Sistema</div>
          <div class="header-cell stock-col">Estoque Atual</div>
          <div 
            v-for="month in months" 
            :key="month"
            class="header-cell month-col"
          >
            {{ formatMonth(month) }}
          </div>
        </div>
        
        <!-- Table Body -->
        <div class="table-body">
          <div 
            v-for="(product, key) in products.data" 
            :key="key"
            class="table-row"
          >
            <div class="data-cell product-col">
              <div class="font-medium text-foreground">{{ product.product_name }}</div>
            </div>
            <div class="data-cell system-col">
              <span class="text-xs px-2 py-1 rounded-full" :class="getSourceBadgeClass(product.source)">
                {{ product.source }}
              </span>
            </div>
            <div class="data-cell stock-col">
              <span class="text-muted-foreground font-medium">{{ product.current_stock }}</span>
            </div>
            <div 
              v-for="month in months" 
              :key="month"
              class="data-cell month-col"
            >
              <span class="font-medium text-foreground">
                {{ product.sales_by_month[month] || 0 }}
              </span>
            </div>
          </div>
        </div>
      </div>

      <!-- Pagination -->
      <div v-if="products.last_page > 1" class="flex items-center justify-between px-4 py-3 border-t border-sidebar-border/70 dark:border-sidebar-border">
        <div class="text-sm text-muted-foreground">
          Página {{ products.current_page }} de {{ products.last_page }}
        </div>
        
        <div class="flex items-center space-x-2">
          <!-- Previous Button -->
          <button 
            @click="goToPage(products.current_page - 1)"
            :disabled="products.current_page <= 1"
            class="px-3 py-1 text-sm border border-input rounded-md hover:bg-muted disabled:opacity-50 disabled:cursor-not-allowed"
          >
            Anterior
          </button>
          
          <!-- Page Numbers -->
          <div class="flex space-x-1">
            <button 
              v-for="page in getVisiblePages()" 
              :key="page"
              @click="goToPage(page)"
              :class="[
                'px-3 py-1 text-sm rounded-md',
                page === products.current_page 
                  ? 'bg-primary text-primary-foreground' 
                  : 'border border-input hover:bg-muted'
              ]"
            >
              {{ page }}
            </button>
          </div>
          
          <!-- Next Button -->
          <button 
            @click="goToPage(products.current_page + 1)"
            :disabled="products.current_page >= products.last_page"
            class="px-3 py-1 text-sm border border-input rounded-md hover:bg-muted disabled:opacity-50 disabled:cursor-not-allowed"
          >
            Próximo
          </button>
        </div>
      </div>
    </div>

  </div>
</AppLayout>
</template>

<script setup>
import { ref, reactive, onMounted, watch, computed } from 'vue'
import { router } from '@inertiajs/vue3'
import { debounce } from 'lodash-es'
import AppLayout from '@/layouts/AppLayout.vue'
import { Head } from '@inertiajs/vue3'

const breadcrumbs = computed(() => [
    {
        title: 'Relatório de Vendas',
        href: '/dashboard/reports/sales',
    },
])

// Props
const props = defineProps({
  products: Object,
  months: Array,
  filters: Object,
  sources: Object,
})


// Reactive data
const loading = ref(false)
const error = ref(null)

// Local filters state
const filters = reactive({
  search: props.filters?.search || '',
  source: props.filters?.source || '',
  months_back: props.filters?.months_back || 6,
  start_month: props.filters?.start_month || '',
  end_month: props.filters?.end_month || '',
  per_page: props.filters?.per_page || 25,
})

// Methods
const applyFilters = debounce(() => {
  loading.value = true
  error.value = null
  
  router.get('/dashboard/reports/sales', filters, {
    preserveState: true,
    onSuccess: () => {
      loading.value = false
    },
    onError: (errors) => {
      loading.value = false
      error.value = 'Erro ao carregar relatório. Tente novamente.'
    }
  })
}, 500)

const updateDateRange = () => {
  const now = new Date()
  const monthsBack = parseInt(filters.months_back)
  
  // Calculate start date (months back from now)
  const startDate = new Date(now.getFullYear(), now.getMonth() - monthsBack + 1, 1)
  
  // Calculate end date (end of current month)
  const endDate = new Date(now.getFullYear(), now.getMonth() + 1, 0)
  
  filters.start_month = startDate.toISOString().slice(0, 7)
  filters.end_month = endDate.toISOString().slice(0, 7)
  
  applyFilters()
}

const getPeriodDescription = () => {
  const monthsBack = parseInt(filters.months_back)
  const now = new Date()
  const startDate = new Date(now.getFullYear(), now.getMonth() - monthsBack + 1, 1)
  const endDate = new Date(now.getFullYear(), now.getMonth() + 1, 0)
  
  return `${formatMonth(startDate.toISOString().slice(0, 7))} a ${formatMonth(endDate.toISOString().slice(0, 7))}`
}

const getSourceBadgeClass = (source) => {
  const classes = {
    'Pro': 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
    'Atacadista': 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
    'Beauty': 'bg-pink-100 text-pink-800 dark:bg-pink-900 dark:text-pink-200',
  }
  return classes[source] || 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200'
}

const resetFilters = () => {
  filters.search = ''
  filters.source = ''
  filters.months_back = 6
  filters.start_month = ''
  filters.end_month = ''
  filters.per_page = 25
  updateDateRange()
}

const goToPage = (page) => {
  if (page < 1 || page > props.products.last_page) return
  
  loading.value = true
  router.get('/dashboard/reports/sales', { ...filters, page }, {
    preserveState: true,
    onSuccess: () => {
      loading.value = false
    },
    onError: (errors) => {
      loading.value = false
      error.value = 'Erro ao carregar página. Tente novamente.'
    }
  })
}

const getVisiblePages = () => {
  const current = props.products.current_page
  const last = props.products.last_page
  const delta = 2
  
  let start = Math.max(1, current - delta)
  let end = Math.min(last, current + delta)
  
  if (end - start < 2 * delta) {
    if (start === 1) {
      end = Math.min(last, start + 2 * delta)
    } else {
      start = Math.max(1, end - 2 * delta)
    }
  }
  
  const pages = []
  for (let i = start; i <= end; i++) {
    pages.push(i)
  }
  
  return pages
}

const formatMonth = (monthString) => {
  if (!monthString) return ''
  
  const [year, month] = monthString.split('-')
  const monthNames = [
    'Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun',
    'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez'
  ]
  
  return `${monthNames[parseInt(month) - 1]}/${year}`
}

// Watch for filter changes
watch(filters, () => {
  // Don't auto-apply filters on mount
  if (filters.start_month && filters.end_month) {
    applyFilters()
  }
}, { deep: true })

// Initialize with default period
onMounted(() => {
  if (!filters.start_month || !filters.end_month) {
    updateDateRange()
  }
})
</script>

<style scoped>
/* Custom scrollbar for the table */
.overflow-x-auto::-webkit-scrollbar {
  height: 8px;
}

.overflow-x-auto::-webkit-scrollbar-track {
  background: rgba(0, 0, 0, 0.1);
  border-radius: 4px;
}

.overflow-x-auto::-webkit-scrollbar-thumb {
  background: rgba(0, 0, 0, 0.3);
  border-radius: 4px;
}

.overflow-x-auto::-webkit-scrollbar-thumb:hover {
  background: rgba(0, 0, 0, 0.5);
}

/* Dark mode scrollbar */
.dark .overflow-x-auto::-webkit-scrollbar-track {
  background: rgba(255, 255, 255, 0.1);
}

.dark .overflow-x-auto::-webkit-scrollbar-thumb {
  background: rgba(255, 255, 255, 0.3);
}

.dark .overflow-x-auto::-webkit-scrollbar-thumb:hover {
  background: rgba(255, 255, 255, 0.5);
}

/* Table container with horizontal scroll support */
.table-container {
  display: flex;
  flex-direction: column;
  height: 400px;
  overflow: hidden;
  position: relative;
}

.table-header {
  display: flex;
  background-color: hsl(var(--muted) / 0.3);
  border-bottom: 1px solid hsl(var(--sidebar-border) / 0.7);
  flex-shrink: 0;
  overflow-x: auto;
  overflow-y: hidden;
  min-width: max-content;
  position: sticky;
  top: 0;
  z-index: 20;
}

.table-body {
  flex: 1;
  overflow-y: auto;
  overflow-x: auto;
  min-height: 0;
}

.table-row {
  display: flex;
  border-bottom: 1px solid hsl(var(--sidebar-border) / 0.7);
  transition: background-color 0.2s;
  min-width: max-content;
}

.table-row:hover {
  background-color: hsl(var(--muted) / 0.5);
}

/* Header and data cells */
.header-cell,
.data-cell {
  display: flex;
  align-items: center;
  padding: 12px 16px;
  border-right: 1px solid hsl(var(--sidebar-border) / 0.7);
  font-size: 0.875rem;
  line-height: 1.25rem;
}

.header-cell {
  font-weight: 500;
  color: hsl(var(--muted-foreground));
  background-color: hsl(var(--muted) / 0.3);
}

.data-cell {
  color: hsl(var(--foreground));
}

/* Column widths */
.product-col {
  width: 256px; /* 16rem */
  justify-content: flex-start;
}

.system-col {
  width: 96px; /* 6rem */
  justify-content: center;
}

.stock-col {
  width: 128px; /* 8rem */
  justify-content: center;
}

.month-col {
  width: 100px; /* 6.25rem */
  min-width: 100px;
  justify-content: center;
  flex-shrink: 0;
}

/* Remove border from last column */
.header-cell:last-child,
.data-cell:last-child {
  border-right: none;
}

/* Mobile responsive adjustments */
@media (max-width: 768px) {
  .table-container {
    height: 300px;
    overflow: auto; /* Allow both horizontal and vertical scroll on mobile */
  }
  
  .table-header {
    position: sticky;
    top: 0;
    z-index: 30;
    background-color: hsl(var(--muted) / 0.9);
    backdrop-filter: blur(8px);
  }
  
  .table-body {
    overflow: visible; /* Let the container handle scrolling */
  }
  
  .product-col {
    width: 200px; /* Smaller product column on mobile */
  }
  
  .system-col {
    width: 80px; /* Smaller system column on mobile */
  }
  
  .stock-col {
    width: 100px; /* Smaller stock column on mobile */
  }
  
  .month-col {
    width: 80px; /* Smaller month columns on mobile */
    min-width: 80px;
  }
  
  .header-cell,
  .data-cell {
    padding: 8px 12px; /* Smaller padding on mobile */
    font-size: 0.8rem;
  }
}

/* Custom scrollbar for table */
.table-container::-webkit-scrollbar {
  height: 8px;
  width: 8px;
}

.table-container::-webkit-scrollbar-track {
  background: rgba(0, 0, 0, 0.1);
  border-radius: 4px;
}

.table-container::-webkit-scrollbar-thumb {
  background: rgba(0, 0, 0, 0.3);
  border-radius: 4px;
}

.table-container::-webkit-scrollbar-thumb:hover {
  background: rgba(0, 0, 0, 0.5);
}

/* Dark mode scrollbar */
.dark .table-container::-webkit-scrollbar-track {
  background: rgba(255, 255, 255, 0.1);
}

.dark .table-container::-webkit-scrollbar-thumb {
  background: rgba(255, 255, 255, 0.3);
}

.dark .table-container::-webkit-scrollbar-thumb:hover {
  background: rgba(255, 255, 255, 0.5);
}
</style>
