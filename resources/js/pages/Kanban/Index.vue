<template>
  <div class="kanban-container">
    <!-- Header -->
    <div class="kanban-header mb-6 sm:mb-8 lg:mb-10">
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <h1 class="text-3xl sm:text-4xl lg:text-5xl xl:text-6xl font-black text-gray-900">Painel Kanban</h1>
        <!-- Real-time connection indicator -->
        <div class="flex items-center gap-3">
          <div 
            :class="[
              'h-4 w-4 rounded-full animate-pulse',
              isConnected ? 'bg-green-500' : 'bg-red-500'
            ]"
          ></div>
          <span class="text-base sm:text-lg lg:text-xl font-bold text-gray-800">
            {{ isConnected ? 'Tempo real ativo' : 'Sem conexão' }}
          </span>
        </div>
      </div>
    </div>

    <!-- Simplified Kanban Board -->
    <div class="kanban-board justify-center flex gap-3 sm:gap-4 lg:gap-6 xl:gap-8 overflow-x-auto pb-4">
      <!-- Aguardando Column -->
      <div class="kanban-column">
        <div class="column-header bg-yellow-500 p-5 sm:p-6 lg:p-7 xl:p-8 rounded-t-lg xl:rounded-t-xl shadow-lg">
          <div class="flex items-center justify-between w-full">
            <h3 class="font-black text-white text-xl sm:text-2xl lg:text-3xl xl:text-4xl">Aguardando</h3>
            <span class="badge font-black text-yellow-900 bg-white rounded-full px-4 py-2 sm:px-6 sm:py-3 text-lg sm:text-xl lg:text-2xl xl:text-3xl shadow-lg">
              {{ kanbanData.aguardando.length }}
            </span>
          </div>
        </div>
        <div class="column-content bg-yellow-50 p-4 sm:p-5 lg:p-6 xl:p-7 rounded-b-lg xl:rounded-b-xl min-h-72 sm:min-h-96 lg:min-h-[400px] xl:min-h-[500px]">
          <div
            v-for="order in kanbanData.aguardando"
            :key="order.id"
            class="order-item bg-white p-4 sm:p-5 lg:p-6 xl:p-7 mb-3 sm:mb-4 lg:mb-5 rounded-lg border-2 border-yellow-300 shadow-md hover:shadow-lg lg:hover:shadow-xl transition-all duration-300"
          >
            <div class="flex justify-between items-start">
              <div class="flex-1">
                <div class="flex justify-between items-start mb-3 sm:mb-4">
                  <span class="font-black text-gray-900 text-lg sm:text-xl lg:text-2xl xl:text-3xl">#{{ order.numero }}</span>
                  <span class="text-sm sm:text-base lg:text-lg text-gray-700 font-bold">{{ formatDateOnly(order.data) }}</span>
                </div>
                <div class="text-base sm:text-lg lg:text-xl xl:text-2xl text-gray-800 mb-3 sm:mb-4 font-bold leading-tight">{{ order.contato_nome }}</div>
                <div class="text-lg sm:text-xl lg:text-2xl xl:text-3xl font-black text-green-700 mb-3 sm:mb-4">R$ {{ formatCurrency(order.total) }}</div>
                <div class="flex justify-between items-center text-sm sm:text-base lg:text-lg text-gray-700">
                  <div v-if="order.invoice_number" class="flex items-center gap-2">
                    <span class="font-black text-gray-800">NF:</span>
                    <span class="font-bold text-gray-900">{{ order.invoice_number }}</span>
                  </div>
                  <div v-else class="text-gray-600 font-bold">Sem NF</div>
                  <div class="flex items-center gap-2">
                    <span class="font-black text-gray-900">{{ order.items_quantity }}</span>
                    <span class="font-bold text-gray-800">{{ order.items_quantity === 1 ? 'item' : 'itens' }}</span>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Separado Column -->
      <div class="kanban-column">
        <div class="column-header bg-blue-600 p-5 sm:p-6 lg:p-7 xl:p-8 rounded-t-lg xl:rounded-t-xl shadow-lg">
          <div class="flex items-center justify-between w-full">
            <h3 class="font-black text-white text-xl sm:text-2xl lg:text-3xl xl:text-4xl">Separado</h3>
            <span class="badge font-black text-blue-900 bg-white rounded-full px-4 py-2 sm:px-6 sm:py-3 text-lg sm:text-xl lg:text-2xl xl:text-3xl shadow-lg">
              {{ kanbanData.separado.length }}
            </span>
          </div>
        </div>
        <div class="column-content bg-blue-50 p-4 sm:p-5 lg:p-6 xl:p-7 rounded-b-lg xl:rounded-b-xl min-h-72 sm:min-h-96 lg:min-h-[400px] xl:min-h-[500px]">
          <div
            v-for="order in kanbanData.separado"
            :key="order.id"
            class="order-item bg-white p-4 sm:p-5 lg:p-6 xl:p-7 mb-3 sm:mb-4 lg:mb-5 rounded-lg border-2 border-blue-400 shadow-md hover:shadow-lg lg:hover:shadow-xl transition-all duration-300"
          >
            <div class="flex justify-between items-start">
              <div class="flex-1">
                <div class="flex justify-between items-start mb-3 sm:mb-4">
                  <span class="font-black text-gray-900 text-lg sm:text-xl lg:text-2xl xl:text-3xl">#{{ order.numero }}</span>
                  <span class="text-sm sm:text-base lg:text-lg text-gray-700 font-bold">{{ formatDateOnly(order.data) }}</span>
                </div>
                <div class="text-base sm:text-lg lg:text-xl xl:text-2xl text-gray-800 mb-3 sm:mb-4 font-bold leading-tight">{{ order.contato_nome }}</div>
                <div class="text-lg sm:text-xl lg:text-2xl xl:text-3xl font-black text-green-700 mb-3 sm:mb-4">R$ {{ formatCurrency(order.total) }}</div>
                <div class="flex justify-between items-center text-sm sm:text-base lg:text-lg text-gray-700 mb-3 sm:mb-4">
                  <div v-if="order.invoice_number" class="flex items-center gap-2">
                    <span class="font-black text-gray-800">NF:</span>
                    <span class="font-bold text-gray-900">{{ order.invoice_number }}</span>
                  </div>
                  <div v-else class="text-gray-600 font-bold">Sem NF</div>
                  <div class="flex items-center gap-2">
                    <span class="font-black text-gray-900">{{ order.items_quantity }}</span>
                    <span class="font-bold text-gray-800">{{ order.items_quantity === 1 ? 'item' : 'itens' }}</span>
                  </div>
                </div>
                <div v-if="getMovementHistory(order, 'picking')" class="text-sm sm:text-base lg:text-lg text-blue-800 font-bold bg-blue-200 p-2 sm:p-3 rounded-lg">
                  {{ getMovementHistory(order, 'picking') }}
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Embalado Column -->
      <div class="kanban-column">
        <div class="column-header bg-purple-600 p-5 sm:p-6 lg:p-7 xl:p-8 rounded-t-lg xl:rounded-t-xl shadow-lg">
          <div class="flex items-center justify-between w-full">
            <h3 class="font-black text-white text-xl sm:text-2xl lg:text-3xl xl:text-4xl">Embalado</h3>
            <span class="badge font-black text-purple-900 bg-white rounded-full px-4 py-2 sm:px-6 sm:py-3 text-lg sm:text-xl lg:text-2xl xl:text-3xl shadow-lg">
              {{ kanbanData.embalado.length }}
            </span>
          </div>
        </div>
        <div class="column-content bg-purple-50 p-4 sm:p-5 lg:p-6 xl:p-7 rounded-b-lg xl:rounded-b-xl min-h-72 sm:min-h-96 lg:min-h-[400px] xl:min-h-[500px]">
          <div
            v-for="order in kanbanData.embalado"
            :key="order.id"
            class="order-item bg-white p-4 sm:p-5 lg:p-6 xl:p-7 mb-3 sm:mb-4 lg:mb-5 rounded-lg border-2 border-purple-400 shadow-md hover:shadow-lg lg:hover:shadow-xl transition-all duration-300"
          >
            <div class="flex justify-between items-start">
              <div class="flex-1">
                <div class="flex justify-between items-start mb-3 sm:mb-4">
                  <span class="font-black text-gray-900 text-lg sm:text-xl lg:text-2xl xl:text-3xl">#{{ order.numero }}</span>
                  <span class="text-sm sm:text-base lg:text-lg text-gray-700 font-bold">{{ formatDateOnly(order.data) }}</span>
                </div>
                <div class="text-base sm:text-lg lg:text-xl xl:text-2xl text-gray-800 mb-3 sm:mb-4 font-bold leading-tight">{{ order.contato_nome }}</div>
                <div class="text-lg sm:text-xl lg:text-2xl xl:text-3xl font-black text-green-700 mb-3 sm:mb-4">R$ {{ formatCurrency(order.total) }}</div>
                <div class="flex justify-between items-center text-sm sm:text-base lg:text-lg text-gray-700 mb-3 sm:mb-4">
                  <div v-if="order.invoice_number" class="flex items-center gap-2">
                    <span class="font-black text-gray-800">NF:</span>
                    <span class="font-bold text-gray-900">{{ order.invoice_number }}</span>
                  </div>
                  <div v-else class="text-gray-600 font-bold">Sem NF</div>
                  <div class="flex items-center gap-2">
                    <span class="font-black text-gray-900">{{ order.items_quantity }}</span>
                    <span class="font-bold text-gray-800">{{ order.items_quantity === 1 ? 'item' : 'itens' }}</span>
                  </div>
                </div>
                <div v-if="getMovementHistory(order, 'packing')" class="text-sm sm:text-base lg:text-lg text-purple-800 font-bold bg-purple-200 p-2 sm:p-3 rounded-lg">
                  {{ getMovementHistory(order, 'packing') }}
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Coletado Column -->
      <div class="kanban-column">
        <div class="column-header bg-orange-600 p-5 sm:p-6 lg:p-7 xl:p-8 rounded-t-lg xl:rounded-t-xl shadow-lg">
          <div class="flex items-center justify-between w-full">
            <h3 class="font-black text-white text-xl sm:text-2xl lg:text-3xl xl:text-4xl">Coletado</h3>
            <span class="badge font-black text-orange-900 bg-white rounded-full px-4 py-2 sm:px-6 sm:py-3 text-lg sm:text-xl lg:text-2xl xl:text-3xl shadow-lg">
              {{ kanbanData.coletado.length }}
            </span>
          </div>
        </div>
        <div class="column-content bg-orange-50 p-4 sm:p-5 lg:p-6 xl:p-7 rounded-b-lg xl:rounded-b-xl min-h-72 sm:min-h-96 lg:min-h-[400px] xl:min-h-[500px]">
          <div
            v-for="order in kanbanData.coletado"
            :key="order.id"
            class="order-item bg-white p-4 sm:p-5 lg:p-6 xl:p-7 mb-3 sm:mb-4 lg:mb-5 rounded-lg border-2 border-orange-400 shadow-md hover:shadow-lg lg:hover:shadow-xl transition-all duration-300"
          >
            <div class="flex justify-between items-start">
              <div class="flex-1">
                <div class="flex justify-between items-start mb-3 sm:mb-4">
                  <span class="font-black text-gray-900 text-lg sm:text-xl lg:text-2xl xl:text-3xl">#{{ order.numero }}</span>
                  <span class="text-sm sm:text-base lg:text-lg text-gray-700 font-bold">{{ formatDateOnly(order.data) }}</span>
                </div>
                <div class="text-base sm:text-lg lg:text-xl xl:text-2xl text-gray-800 mb-3 sm:mb-4 font-bold leading-tight">{{ order.contato_nome }}</div>
                <div class="text-lg sm:text-xl lg:text-2xl xl:text-3xl font-black text-green-700 mb-3 sm:mb-4">R$ {{ formatCurrency(order.total) }}</div>
                <div class="flex justify-between items-center text-sm sm:text-base lg:text-lg text-gray-700 mb-3 sm:mb-4">
                  <div v-if="order.invoice_number" class="flex items-center gap-2">
                    <span class="font-black text-gray-800">NF:</span>
                    <span class="font-bold text-gray-900">{{ order.invoice_number }}</span>
                  </div>
                  <div v-else class="text-gray-600 font-bold">Sem NF</div>
                  <div class="flex items-center gap-2">
                    <span class="font-black text-gray-900">{{ order.items_quantity }}</span>
                    <span class="font-bold text-gray-800">{{ order.items_quantity === 1 ? 'item' : 'itens' }}</span>
                  </div>
                </div>
                <div v-if="getMovementHistory(order, 'picked_up')" class="text-sm sm:text-base lg:text-lg text-orange-800 font-bold bg-orange-200 p-2 sm:p-3 rounded-lg">
                  {{ getMovementHistory(order, 'picked_up') }}
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted, onUnmounted, watch } from 'vue'
import { router } from '@inertiajs/vue3'
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

interface Stats {
  total: number
  aguardando: number
  separado: number
  embalado: number
  coletado: number
}

const props = defineProps<{
  kanbanData: KanbanData
  stats: Stats
}>()

const kanbanData = ref<KanbanData>(props.kanbanData)
const stats = ref<Stats>(props.stats)

// Broadcasting setup
const { isConnected, lastUpdate } = useOrderBroadcasting()

// Watch for real-time updates and update kanban data
watch(lastUpdate, (update) => {
  if (update) {
    console.log('Kanban received update:', update)
    
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
    
    // Update stats
    stats.value.total = Object.values(kanbanData.value).reduce((sum, column) => sum + column.length, 0)
    stats.value.aguardando = kanbanData.value.aguardando.length
    stats.value.separado = kanbanData.value.separado.length
    stats.value.embalado = kanbanData.value.embalado.length
    stats.value.coletado = kanbanData.value.coletado.length
    
    console.log('Kanban updated with new data')
  }
})

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
  padding: 1rem 0.5rem;
}

@media (min-width: 640px) {
  .kanban-container {
    padding: 1rem 1rem;
  }
}

@media (min-width: 1024px) {
  .kanban-container {
    padding: 1.5rem 1.5rem;
  }
}

@media (min-width: 1280px) {
  .kanban-container {
    padding: 2rem 2rem;
  }
}

@media (min-width: 1920px) {
  .kanban-container {
    padding: 2.5rem 2.5rem;
  }
}

.kanban-board {
  overflow-x: auto;
  -webkit-overflow-scrolling: touch;
}

.kanban-column {
  display: flex;
  flex-direction: column;
  width: 300px;
  flex-shrink: 0;
}

@media (min-width: 640px) {
  .kanban-column {
    width: 350px;
  }
}

@media (min-width: 1024px) {
  .kanban-column {
    width: 380px;
  }
}

@media (min-width: 1280px) {
  .kanban-column {
    width: 420px;
  }
}

@media (min-width: 1536px) {
  .kanban-column {
    width: 480px;
  }
}

@media (min-width: 1920px) {
  .kanban-column {
    width: 550px;
  }
}

.order-item {
  transition: all 0.3s ease-in-out;
  border: 2px solid #e5e7eb;
  cursor: pointer;
  background: linear-gradient(135deg, #ffffff 0%, #f9fafb 100%);
}

.order-item:hover {
  box-shadow: 0 8px 25px -5px rgb(0 0 0 / 0.2);
  transform: translateY(-3px) scale(1.02);
  border-color: #d1d5db;
}

@media (min-width: 1024px) {
  .order-item {
    border: 3px solid #e5e7eb;
  }
  
  .order-item:hover {
    box-shadow: 0 12px 35px -8px rgb(0 0 0 / 0.25);
    transform: translateY(-4px) scale(1.03);
  }
}

@media (min-width: 1920px) {
  .order-item:hover {
    box-shadow: 0 16px 45px -10px rgb(0 0 0 / 0.3);
    transform: translateY(-5px) scale(1.04);
  }
}

.stats-grid {
  max-width: 56rem;
}

.stat-card {
  text-align: center;
}

.badge {
  flex-shrink: 0;
}

.column-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.column-content {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
  overflow-y: auto;
  max-height: calc(100vh - 240px);
  padding-right: 4px;
}

@media (min-width: 640px) {
  .column-content {
    max-height: calc(100vh - 200px);
    gap: 1rem;
  }
}

@media (min-width: 1024px) {
  .column-content {
    max-height: calc(100vh - 180px);
    gap: 1.25rem;
  }
}

@media (min-width: 1280px) {
  .column-content {
    max-height: calc(100vh - 170px);
    gap: 1.5rem;
  }
}

@media (min-width: 1920px) {
  .column-content {
    max-height: calc(100vh - 160px);
    gap: 2rem;
  }
}

</style>
