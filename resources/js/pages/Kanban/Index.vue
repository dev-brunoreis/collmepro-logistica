<template>
  <div class="kanban-container">
    <!-- Header -->
    <div class="kanban-header mb-6">
      <h1 class="text-2xl font-bold text-gray-900 mb-4">Painel Kanban</h1>
    </div>

    <!-- Simplified Kanban Board -->
    <div class="kanban-board grid grid-cols-5 gap-6">
      <!-- Aguardando Column -->
      <div class="kanban-column">
        <div class="column-header bg-yellow-100 p-4 rounded-t-lg">
          <h3 class="font-semibold text-yellow-800">Aguardando</h3>
          <span class="badge bg-yellow-200 text-yellow-800 px-2 py-1 rounded-full text-sm">
            {{ kanbanData.aguardando.length }}
          </span>
        </div>
        <div class="column-content bg-yellow-50 p-4 rounded-b-lg min-h-96">
          <div
            v-for="order in kanbanData.aguardando"
            :key="order.id"
            class="order-item bg-white p-3 mb-2 rounded border border-yellow-200"
          >
            <div class="flex justify-between items-start">
              <div>
                <span class="font-semibold text-gray-900">#{{ order.numero }}</span>
                <div class="text-sm text-gray-700">{{ order.contato_nome }}</div>
                <div class="text-sm font-medium text-green-600">R$ {{ formatCurrency(order.total) }}</div>
              </div>
              <span class="text-xs text-gray-500">{{ formatDateOnly(order.data) }}</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Separado Column -->
      <div class="kanban-column">
        <div class="column-header bg-blue-100 p-4 rounded-t-lg">
          <h3 class="font-semibold text-blue-800">Separado</h3>
          <span class="badge bg-blue-200 text-blue-800 px-2 py-1 rounded-full text-sm">
            {{ kanbanData.separado.length }}
          </span>
        </div>
        <div class="column-content bg-blue-50 p-4 rounded-b-lg min-h-96">
          <div
            v-for="order in kanbanData.separado"
            :key="order.id"
            class="order-item bg-white p-3 mb-2 rounded border border-blue-200"
          >
            <div class="flex justify-between items-start">
              <div>
                <span class="font-semibold text-gray-900">#{{ order.numero }}</span>
                <div class="text-sm text-gray-700">{{ order.contato_nome }}</div>
                <div class="text-sm font-medium text-green-600">R$ {{ formatCurrency(order.total) }}</div>
                <div v-if="getMovementHistory(order, 'picking')" class="text-xs text-blue-600 mt-1">
                  {{ getMovementHistory(order, 'picking') }}
                </div>
              </div>
              <span class="text-xs text-gray-500">{{ formatDateOnly(order.data) }}</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Embalado Column -->
      <div class="kanban-column">
        <div class="column-header bg-purple-100 p-4 rounded-t-lg">
          <h3 class="font-semibold text-purple-800">Embalado</h3>
          <span class="badge bg-purple-200 text-purple-800 px-2 py-1 rounded-full text-sm">
            {{ kanbanData.embalado.length }}
          </span>
        </div>
        <div class="column-content bg-purple-50 p-4 rounded-b-lg min-h-96">
          <div
            v-for="order in kanbanData.embalado"
            :key="order.id"
            class="order-item bg-white p-3 mb-2 rounded border border-purple-200"
          >
            <div class="flex justify-between items-start">
              <div>
                <span class="font-semibold text-gray-900">#{{ order.numero }}</span>
                <div class="text-sm text-gray-700">{{ order.contato_nome }}</div>
                <div class="text-sm font-medium text-green-600">R$ {{ formatCurrency(order.total) }}</div>
                <div v-if="getMovementHistory(order, 'packing')" class="text-xs text-purple-600 mt-1">
                  {{ getMovementHistory(order, 'packing') }}
                </div>
              </div>
              <span class="text-xs text-gray-500">{{ formatDateOnly(order.data) }}</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Coletado Column -->
      <div class="kanban-column">
        <div class="column-header bg-orange-100 p-4 rounded-t-lg">
          <h3 class="font-semibold text-orange-800">Coletado</h3>
          <span class="badge bg-orange-200 text-orange-800 px-2 py-1 rounded-full text-sm">
            {{ kanbanData.coletado.length }}
          </span>
        </div>
        <div class="column-content bg-orange-50 p-4 rounded-b-lg min-h-96">
          <div
            v-for="order in kanbanData.coletado"
            :key="order.id"
            class="order-item bg-white p-3 mb-2 rounded border border-orange-200"
          >
            <div class="flex justify-between items-start">
              <div>
                <span class="font-semibold text-gray-900">#{{ order.numero }}</span>
                <div class="text-sm text-gray-700">{{ order.contato_nome }}</div>
                <div class="text-sm font-medium text-green-600">R$ {{ formatCurrency(order.total) }}</div>
                <div v-if="getMovementHistory(order, 'picked_up')" class="text-xs text-orange-600 mt-1">
                  {{ getMovementHistory(order, 'picked_up') }}
                </div>
              </div>
              <span class="text-xs text-gray-500">{{ formatDateOnly(order.data) }}</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Sucesso Column -->
      <div class="kanban-column">
        <div class="column-header bg-green-100 p-4 rounded-t-lg">
          <h3 class="font-semibold text-green-800">Sucesso</h3>
          <span class="badge bg-green-200 text-green-800 px-2 py-1 rounded-full text-sm">
            {{ kanbanData.sucesso.length }}
          </span>
        </div>
        <div class="column-content bg-green-50 p-4 rounded-b-lg min-h-96">
          <div
            v-for="order in kanbanData.sucesso"
            :key="order.id"
            class="order-item bg-white p-3 mb-2 rounded border border-green-200"
          >
            <div class="flex justify-between items-start">
              <div>
                <span class="font-semibold text-gray-900">#{{ order.numero }}</span>
                <div class="text-sm text-gray-700">{{ order.contato_nome }}</div>
                <div class="text-sm font-medium text-green-600">R$ {{ formatCurrency(order.total) }}</div>
                <div v-if="getMovementHistory(order, 'completed')" class="text-xs text-green-600 mt-1">
                  {{ getMovementHistory(order, 'completed') }}
                </div>
              </div>
              <span class="text-xs text-gray-500">{{ formatDateOnly(order.data) }}</span>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted, onUnmounted } from 'vue'
import { router } from '@inertiajs/vue3'

interface Order {
  id: number
  bling_id: number
  numero: string
  contato_nome: string
  total: number
  data: string
  source: string
  order_status: any
}

interface KanbanData {
  aguardando: Order[]
  separado: Order[]
  embalado: Order[]
  coletado: Order[]
  sucesso: Order[]
}

interface Stats {
  total: number
  aguardando: number
  separado: number
  embalado: number
  coletado: number
  sucesso: number
}

const props = defineProps<{
  kanbanData: KanbanData
  stats: Stats
}>()

const kanbanData = ref<KanbanData>(props.kanbanData)
const stats = ref<Stats>(props.stats)

// Debug: log the data to see what we're working with
console.log('Kanban data:', props.kanbanData)

const formatCurrency = (value: number): string => {
  return new Intl.NumberFormat('pt-BR', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(value)
}

const formatDateOnly = (dateString: string): string => {
  if (!dateString) return '-';
  
  try {
    // Handle different date formats
    let date: Date;
    
    if (typeof dateString === 'string') {
      // If it's already formatted as dd/mm/yyyy hh:mm, extract just the date part
      if (dateString.includes(' ')) {
        const [datePart] = dateString.split(' ');
        return datePart; // Return dd/mm/yyyy
      }
      
      // Otherwise, parse as ISO string or other format
      date = new Date(dateString);
    } else {
      date = dateString;
    }
    
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
}

const formatDateTime = (dateString: string): string => {
  if (!dateString) return '-';
  
  try {
    const date = new Date(dateString);
    if (isNaN(date.getTime())) {
      return '-';
    }
    
    return date.toLocaleString('pt-BR', {
      day: '2-digit',
      month: '2-digit',
      year: 'numeric',
      hour: '2-digit',
      minute: '2-digit'
    });
  } catch (error) {
    console.warn('Error formatting date time:', dateString, error);
    return '-';
  }
}

const getMovementHistory = (order: Order, statusType: string): string => {
  console.log('Getting movement history for order:', order.numero, 'status:', statusType, 'order_status:', order.order_status);
  
  if (!order.order_status) {
    console.log('No order_status found for order:', order.numero);
    return '';
  }
  
  const status = order.order_status;
  
  try {
    switch (statusType) {
      case 'picking':
        if (status.picking_marked_by && status.picking_marked_at) {
          const dateTime = formatDateTime(status.picking_marked_at);
          return `Separado por: ${status.picking_marked_by} em ${dateTime}`;
        }
        break;
      case 'packing':
        if (status.packing_marked_by && status.packing_marked_at) {
          const dateTime = formatDateTime(status.packing_marked_at);
          return `Embalado por: ${status.packing_marked_by} em ${dateTime}`;
        }
        break;
      case 'picked_up':
        if (status.picked_up_marked_by && status.picked_up_marked_at) {
          const dateTime = formatDateTime(status.picked_up_marked_at);
          return `Coletado por: ${status.picked_up_marked_by} em ${dateTime}`;
        }
        break;
      case 'completed':
        if (status.picking && status.packing && status.picked_up) {
          const lastDate = status.picked_up_marked_at || status.packing_marked_at || status.picking_marked_at;
          if (lastDate) {
            const dateTime = formatDateTime(lastDate);
            return `Finalizado em ${dateTime}`;
          }
        }
        break;
    }
  } catch (error) {
    console.warn('Error getting movement history:', error);
  }
  
  return '';
}

onMounted(() => {
    // Component mounted
})
</script>

<style scoped>
.kanban-container {
  padding: 1.5rem;
}

.kanban-board {
  overflow-x: auto;
}

.kanban-column {
  display: flex;
  flex-direction: column;
  min-width: 20rem;
}

.order-item {
  transition: all 0.2s;
  border: 1px solid #e5e7eb;
}

.order-item:hover {
  box-shadow: 0 2px 4px -1px rgb(0 0 0 / 0.1);
}

.stats-grid {
  max-width: 56rem;
}

.stat-card {
  text-align: center;
}

.badge {
  margin-left: 0.5rem;
}

.column-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.column-content {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}
</style>
