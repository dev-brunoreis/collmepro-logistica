<template>
  <div class="kanban-compact">
    <!-- Header with fullscreen button -->
    <div class="flex items-center justify-between mb-4">
      <h2 class="text-xl font-bold text-white">Painel Kanban</h2>
      <button
        @click="openFullscreen"
        class="flex items-center gap-2 px-3 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition-colors duration-200"
        title="Abrir em tela cheia"
      >
        <Maximize class="w-4 h-4" />
        <span class="cursor-pointer hidden sm:inline">Tela Cheia</span>
      </button>
    </div>

    <!-- Compact Kanban Board -->
    <div class="kanban-board-compact flex gap-3 overflow-x-auto pb-2">
      <!-- Aguardando Column -->
      <div class="kanban-column-compact flex-shrink-0">
        <div class="column-header-compact bg-yellow-500 p-3 rounded-t-lg">
          <div class="flex items-center justify-between">
            <h3 class="font-bold text-white text-sm">Aguardando</h3>
            <span class="badge-compact bg-white text-yellow-900 rounded-full px-2 py-1 text-xs font-bold">
              {{ kanbanData.aguardando.length }}
            </span>
          </div>
        </div>
        <div class="column-content-compact bg-yellow-50 p-2 rounded-b-lg min-h-[200px] max-h-[300px] overflow-y-auto">
          <div
            v-for="order in kanbanData.aguardando"
            :key="order.id"
            class="order-item-compact bg-white p-3 mb-2 rounded border border-yellow-300 shadow-sm hover:shadow-md transition-shadow duration-200"
          >
            <div class="text-sm font-bold text-gray-900">#{{ order.numero }}</div>
            <div class="text-xs text-gray-700 mb-1">{{ formatDateOnly(order.data) }}</div>
            <div class="text-sm text-gray-800 mb-2 line-clamp-2">{{ order.contato_nome }}</div>
            <div class="text-sm font-bold text-green-700">R$ {{ formatCurrency(order.total) }}</div>
          </div>
        </div>
      </div>

      <!-- Separado Column -->
      <div class="kanban-column-compact flex-shrink-0">
        <div class="column-header-compact bg-blue-600 p-3 rounded-t-lg">
          <div class="flex items-center justify-between">
            <h3 class="font-bold text-white text-sm">Separado</h3>
            <span class="badge-compact bg-white text-blue-900 rounded-full px-2 py-1 text-xs font-bold">
              {{ kanbanData.separado.length }}
            </span>
          </div>
        </div>
        <div class="column-content-compact bg-blue-50 p-2 rounded-b-lg min-h-[200px] max-h-[300px] overflow-y-auto">
          <div
            v-for="order in kanbanData.separado"
            :key="order.id"
            class="order-item-compact bg-white p-3 mb-2 rounded border border-blue-400 shadow-sm hover:shadow-md transition-shadow duration-200"
          >
            <div class="text-sm font-bold text-gray-900">#{{ order.numero }}</div>
            <div class="text-xs text-gray-700 mb-1">{{ formatDateOnly(order.data) }}</div>
            <div class="text-sm text-gray-800 mb-2 line-clamp-2">{{ order.contato_nome }}</div>
            <div class="text-sm font-bold text-green-700">R$ {{ formatCurrency(order.total) }}</div>
            <div v-if="getMovementHistory(order, 'picking')" class="text-xs text-blue-800 bg-blue-200 p-1 rounded mt-1">
              {{ getMovementHistory(order, 'picking') }}
            </div>
          </div>
        </div>
      </div>

      <!-- Embalado Column -->
      <div class="kanban-column-compact flex-shrink-0">
        <div class="column-header-compact bg-purple-600 p-3 rounded-t-lg">
          <div class="flex items-center justify-between">
            <h3 class="font-bold text-white text-sm">Embalado</h3>
            <span class="badge-compact bg-white text-purple-900 rounded-full px-2 py-1 text-xs font-bold">
              {{ kanbanData.embalado.length }}
            </span>
          </div>
        </div>
        <div class="column-content-compact bg-purple-50 p-2 rounded-b-lg min-h-[200px] max-h-[300px] overflow-y-auto">
          <div
            v-for="order in kanbanData.embalado"
            :key="order.id"
            class="order-item-compact bg-white p-3 mb-2 rounded border border-purple-400 shadow-sm hover:shadow-md transition-shadow duration-200"
          >
            <div class="text-sm font-bold text-gray-900">#{{ order.numero }}</div>
            <div class="text-xs text-gray-700 mb-1">{{ formatDateOnly(order.data) }}</div>
            <div class="text-sm text-gray-800 mb-2 line-clamp-2">{{ order.contato_nome }}</div>
            <div class="text-sm font-bold text-green-700">R$ {{ formatCurrency(order.total) }}</div>
            <div v-if="getMovementHistory(order, 'packing')" class="text-xs text-purple-800 bg-purple-200 p-1 rounded mt-1">
              {{ getMovementHistory(order, 'packing') }}
            </div>
          </div>
        </div>
      </div>

      <!-- Coletado Column -->
      <div class="kanban-column-compact flex-shrink-0">
        <div class="column-header-compact bg-orange-600 p-3 rounded-t-lg">
          <div class="flex items-center justify-between">
            <h3 class="font-bold text-white text-sm">Coletado</h3>
            <span class="badge-compact bg-white text-orange-900 rounded-full px-2 py-1 text-xs font-bold">
              {{ kanbanData.coletado.length }}
            </span>
          </div>
        </div>
        <div class="column-content-compact bg-orange-50 p-2 rounded-b-lg min-h-[200px] max-h-[300px] overflow-y-auto">
          <div
            v-for="order in kanbanData.coletado"
            :key="order.id"
            class="order-item-compact bg-white p-3 mb-2 rounded border border-orange-400 shadow-sm hover:shadow-md transition-shadow duration-200"
          >
            <div class="text-sm font-bold text-gray-900">#{{ order.numero }}</div>
            <div class="text-xs text-gray-700 mb-1">{{ formatDateOnly(order.data) }}</div>
            <div class="text-sm text-gray-800 mb-2 line-clamp-2">{{ order.contato_nome }}</div>
            <div class="text-sm font-bold text-green-700">R$ {{ formatCurrency(order.total) }}</div>
            <div v-if="getMovementHistory(order, 'picked_up')" class="text-xs text-orange-800 bg-orange-200 p-1 rounded mt-1">
              {{ getMovementHistory(order, 'picked_up') }}
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, watch } from 'vue'
import { Maximize } from 'lucide-vue-next'
import { useOrderBroadcasting } from '@/composables/useBroadcasting'

interface Order {
  id: number
  bling_id: number
  numero: string
  contato_nome: string
  total: number
  data: string
  source: string
  invoice_number?: string
  items_quantity: number
  order_status: any
  raw_invoice_data?: any
}

interface KanbanData {
  aguardando: Order[]
  separado: Order[]
  embalado: Order[]
  coletado: Order[]
}

const props = defineProps<{
  kanbanData: KanbanData
}>()

const emit = defineEmits<{
  openFullscreen: []
}>()

const kanbanData = ref<KanbanData>(props.kanbanData)

// Broadcasting setup
const { isConnected, lastUpdate } = useOrderBroadcasting()

// Watch for real-time updates and update kanban data
watch(lastUpdate, (update) => {
  if (update) {
    console.log('Kanban Compact received update:', update)
    
    // Find the order in all columns and update it
    Object.keys(kanbanData.value).forEach(columnKey => {
      const column = kanbanData.value[columnKey as keyof KanbanData]
      const orderIndex = column.findIndex(order => order.id === update.order.id)
      
      if (orderIndex !== -1) {
        const order = column[orderIndex]
        
        // Ensure order_status exists
        if (!order.order_status) {
          order.order_status = {
            picking: false,
            packing: false,
            picked_up: false
          }
        }
        
        // Update the specific status
        order.order_status[update.action] = update.status
        
        if (update.status) {
          // Set the marked by and marked at fields
          order.order_status[`${update.action}_marked_by`] = update.user.name
          order.order_status[`${update.action}_marked_at`] = update.timestamp
        } else {
          // Clear the marked by and marked at fields
          order.order_status[`${update.action}_marked_by`] = null
          order.order_status[`${update.action}_marked_at`] = null
        }
        
        // Ensure new fields are preserved
        if (!order.invoice_number && (update.order as any).invoice_number) {
          order.invoice_number = (update.order as any).invoice_number
        }
        if (!order.items_quantity && (update.order as any).items_quantity) {
          order.items_quantity = (update.order as any).items_quantity
        }
        
        // Remove from current column
        column.splice(orderIndex, 1)
        
        // Determine new column based on status
        let newColumn = 'aguardando'
        if (order.order_status.picked_up) {
          newColumn = 'coletado'
        } else if (order.order_status.packing) {
          newColumn = 'embalado'
        } else if (order.order_status.picking) {
          newColumn = 'separado'
        }
        
        // Add to new column
        kanbanData.value[newColumn as keyof KanbanData].push(order)
        
        console.log(`Moved order ${update.order.id} from ${columnKey} to ${newColumn}`)
      }
    })
    
    console.log('Kanban Compact updated with new data')
  }
})

const formatCurrency = (value: number): string => {
  return new Intl.NumberFormat('pt-BR', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(value)
}

const formatDateOnly = (dateString: string): string => {
  if (!dateString) return '-';
  
  try {
    let date: Date;
    
    if (typeof dateString === 'string') {
      if (dateString.includes(' ')) {
        const [datePart] = dateString.split(' ');
        return datePart;
      }
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
  if (!order.order_status) {
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
    }
  } catch (error) {
    console.warn('Error getting movement history:', error);
  }
  
  return '';
}

const openFullscreen = () => {
  emit('openFullscreen')
}
</script>

<style scoped>
.kanban-compact {
  height: 100%;
  display: flex;
  flex-direction: column;
}

.kanban-board-compact {
  flex: 1;
  width: 100%;
}

.kanban-column-compact {
  width: 180px;
  min-width: 180px;
}

.column-content-compact {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.line-clamp-2 {
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

/* Custom scrollbar */
.column-content-compact::-webkit-scrollbar {
  width: 4px;
}

.column-content-compact::-webkit-scrollbar-track {
  background: #f1f1f1;
  border-radius: 2px;
}

.column-content-compact::-webkit-scrollbar-thumb {
  background: #c1c1c1;
  border-radius: 2px;
}

.column-content-compact::-webkit-scrollbar-thumb:hover {
  background: #a1a1a1;
}

/* Responsive adjustments */
@media (min-width: 640px) {
  .kanban-column-compact {
    width: 200px;
    min-width: 200px;
  }
}

@media (min-width: 1024px) {
  .kanban-column-compact {
    width: 220px;
    min-width: 220px;
  }
}

@media (min-width: 1280px) {
  .kanban-column-compact {
    width: 240px;
    min-width: 240px;
  }
}
</style>
