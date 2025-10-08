<template>
  <div class="kanban-container">
    <!-- Header -->
    <div class="kanban-header mb-6 sm:mb-8 lg:mb-10">
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <h1 class="text-3xl sm:text-4xl lg:text-5xl xl:text-6xl font-black text-white">Kanban Board</h1>
        <!-- Real-time connection indicator -->
        <div class="flex items-center gap-3">
          <div 
            :class="[
              'h-4 w-4 rounded-full animate-pulse',
              isConnected ? 'bg-green-500' : 'bg-red-500'
            ]"
          ></div>
          <span class="text-base sm:text-lg lg:text-xl font-bold text-white">
            {{ isConnected ? 'Real-time active' : 'No connection' }}
          </span>
        </div>
      </div>
    </div>

    <!-- Kanban Board with new structure -->
    <div class="kanban-board flex gap-3 sm:gap-4 lg:gap-6 xl:gap-8 pb-4 px-2 sm:px-4">
      
      <!-- Collme Pro Column -->
      <div class="kanban-column">
        <div class="column-header bg-blue-600 p-5 sm:p-6 lg:p-7 xl:p-8 rounded-t-lg xl:rounded-t-xl shadow-lg">
          <div class="flex items-center justify-between w-full">
            <h3 class="font-black text-white text-xl sm:text-2xl lg:text-3xl xl:text-4xl truncate mr-2">Collme Pro</h3>
            <span class="badge font-black text-blue-900 bg-white rounded-full px-4 py-2 sm:px-6 sm:py-3 text-lg sm:text-xl lg:text-2xl xl:text-3xl shadow-lg flex-shrink-0">
              {{ (kanbanData.pedidos_emitidos || []).filter(o => o.source === 'Collme Pro').length }}
            </span>
          </div>
        </div>
        <div class="column-content bg-blue-50 p-4 sm:p-5 lg:p-6 xl:p-7 rounded-b-lg xl:rounded-b-xl min-h-72 sm:min-h-96 lg:min-h-[400px] xl:min-h-[500px]">
          <div
            v-for="order in (kanbanData.pedidos_emitidos || []).filter(o => o.source === 'Collme Pro')"
            :key="order.id"
            class="order-item bg-white p-4 sm:p-5 lg:p-6 xl:p-7 mb-3 sm:mb-4 lg:mb-5 rounded-lg border-2 border-blue-300 shadow-md hover:shadow-lg lg:hover:shadow-xl transition-all duration-300"
          >
            <OrderCard :order="order" />
          </div>
        </div>
      </div>

      <!-- Collme Beauty Column -->
      <div class="kanban-column">
        <div class="column-header bg-yellow-600 p-5 sm:p-6 lg:p-7 xl:p-8 rounded-t-lg xl:rounded-t-xl shadow-lg">
          <div class="flex items-center justify-between w-full">
            <h3 class="font-black text-white text-xl sm:text-2xl lg:text-3xl xl:text-4xl truncate mr-2">Collme Beauty</h3>
            <span class="badge font-black text-yellow-900 bg-white rounded-full px-4 py-2 sm:px-6 sm:py-3 text-lg sm:text-xl lg:text-2xl xl:text-3xl shadow-lg flex-shrink-0">
              {{ (kanbanData.pedidos_emitidos || []).filter(o => o.source === 'Collme Beauty').length }}
            </span>
          </div>
        </div>
        <div class="column-content bg-yellow-50 p-4 sm:p-5 lg:p-6 xl:p-7 rounded-b-lg xl:rounded-b-xl min-h-72 sm:min-h-96 lg:min-h-[400px] xl:min-h-[500px]">
          <div
            v-for="order in (kanbanData.pedidos_emitidos || []).filter(o => o.source === 'Collme Beauty')"
            :key="order.id"
            class="order-item bg-white p-4 sm:p-5 lg:p-6 xl:p-7 mb-3 sm:mb-4 lg:mb-5 rounded-lg border-2 border-yellow-300 shadow-md hover:shadow-lg lg:hover:shadow-xl transition-all duration-300"
          >
            <OrderCard :order="order" />
          </div>
        </div>
      </div>

      <!-- Faturamento / Emissão de NF Column -->
      <div class="kanban-column">
        <div class="column-header bg-green-600 p-5 sm:p-6 lg:p-7 xl:p-8 rounded-t-lg xl:rounded-t-xl shadow-lg">
          <div class="flex items-center justify-between w-full">
            <h3 class="font-black text-white text-xl sm:text-2xl lg:text-3xl xl:text-4xl truncate mr-2">Invoicing / NF Issuance</h3>
            <span class="badge font-black text-green-900 bg-white rounded-full px-4 py-2 sm:px-6 sm:py-3 text-lg sm:text-xl lg:text-2xl xl:text-3xl shadow-lg flex-shrink-0">
              {{ kanbanData.faturamento?.length || 0 }}
            </span>
          </div>
        </div>
        <div class="column-content bg-green-50 p-4 sm:p-5 lg:p-6 xl:p-7 rounded-b-lg xl:rounded-b-xl min-h-72 sm:min-h-96 lg:min-h-[400px] xl:min-h-[500px]">
          <div
            v-for="order in kanbanData.faturamento || []"
            :key="order.id"
            class="order-item bg-white p-4 sm:p-5 lg:p-6 xl:p-7 mb-3 sm:mb-4 lg:mb-5 rounded-lg border-2 border-green-400 shadow-md hover:shadow-lg lg:hover:shadow-xl transition-all duration-300"
          >
            <OrderCard :order="order" />
          </div>
        </div>
      </div>

      <!-- Checkout (Picking) Column -->
      <div class="kanban-column">
        <div class="column-header bg-blue-600 p-5 sm:p-6 lg:p-7 xl:p-8 rounded-t-lg xl:rounded-t-xl shadow-lg">
          <div class="flex items-center justify-between w-full">
            <h3 class="font-black text-white text-xl sm:text-2xl lg:text-3xl xl:text-4xl truncate mr-2">Checkout (Picking)</h3>
            <span class="badge font-black text-blue-900 bg-white rounded-full px-4 py-2 sm:px-6 sm:py-3 text-lg sm:text-xl lg:text-2xl xl:text-3xl shadow-lg flex-shrink-0">
              {{ kanbanData.checkout?.length || 0 }}
            </span>
          </div>
        </div>
        <div class="column-content bg-blue-50 p-4 sm:p-5 lg:p-6 xl:p-7 rounded-b-lg xl:rounded-b-xl min-h-72 sm:min-h-96 lg:min-h-[400px] xl:min-h-[500px]">
          <div
            v-for="order in kanbanData.checkout || []"
            :key="order.id"
            class="order-item bg-white p-4 sm:p-5 lg:p-6 xl:p-7 mb-3 sm:mb-4 lg:mb-5 rounded-lg border-2 border-blue-400 shadow-md hover:shadow-lg lg:hover:shadow-xl transition-all duration-300"
          >
            <OrderCard :order="order" />
            <div v-if="getMovementHistory(order, 'picking')" class="text-sm sm:text-base lg:text-lg text-blue-800 font-bold bg-blue-200 p-2 sm:p-3 rounded-lg mt-3">
              {{ getMovementHistory(order, 'picking') }}
            </div>
          </div>
        </div>
      </div>

      <!-- Embalagem Column -->
      <div class="kanban-column">
        <div class="column-header bg-purple-600 p-5 sm:p-6 lg:p-7 xl:p-8 rounded-t-lg xl:rounded-t-xl shadow-lg">
          <div class="flex items-center justify-between w-full">
            <h3 class="font-black text-white text-xl sm:text-2xl lg:text-3xl xl:text-4xl truncate mr-2">Packing</h3>
            <span class="badge font-black text-purple-900 bg-white rounded-full px-4 py-2 sm:px-6 sm:py-3 text-lg sm:text-xl lg:text-2xl xl:text-3xl shadow-lg flex-shrink-0">
              {{ kanbanData.embalagem?.length || 0 }}
            </span>
          </div>
        </div>
        <div class="column-content bg-purple-50 p-4 sm:p-5 lg:p-6 xl:p-7 rounded-b-lg xl:rounded-b-xl min-h-72 sm:min-h-96 lg:min-h-[400px] xl:min-h-[500px]">
          <div
            v-for="order in kanbanData.embalagem || []"
            :key="order.id"
            class="order-item bg-white p-4 sm:p-5 lg:p-6 xl:p-7 mb-3 sm:mb-4 lg:mb-5 rounded-lg border-2 border-purple-400 shadow-md hover:shadow-lg lg:hover:shadow-xl transition-all duration-300"
          >
            <OrderCard :order="order" />
            <div v-if="getMovementHistory(order, 'packing')" class="text-sm sm:text-base lg:text-lg text-purple-800 font-bold bg-purple-200 p-2 sm:p-3 rounded-lg mt-3">
              {{ getMovementHistory(order, 'packing') }}
            </div>
          </div>
        </div>
      </div>

      <!-- Etiqueta (cód. rastreio) Column -->
      <div class="kanban-column">
        <div class="column-header bg-indigo-600 p-5 sm:p-6 lg:p-7 xl:p-8 rounded-t-lg xl:rounded-t-xl shadow-lg">
          <div class="flex items-center justify-between w-full">
            <h3 class="font-black text-white text-xl sm:text-2xl lg:text-3xl xl:text-4xl truncate mr-2">Label (tracking code)</h3>
            <span class="badge font-black text-indigo-900 bg-white rounded-full px-4 py-2 sm:px-6 sm:py-3 text-lg sm:text-xl lg:text-2xl xl:text-3xl shadow-lg flex-shrink-0">
              {{ kanbanData.etiqueta?.length || 0 }}
            </span>
          </div>
        </div>
        <div class="column-content bg-indigo-50 p-4 sm:p-5 lg:p-6 xl:p-7 rounded-b-lg xl:rounded-b-xl min-h-72 sm:min-h-96 lg:min-h-[400px] xl:min-h-[500px]">
          <div
            v-for="order in kanbanData.etiqueta || []"
            :key="order.id"
            class="order-item bg-white p-4 sm:p-5 lg:p-6 xl:p-7 mb-3 sm:mb-4 lg:mb-5 rounded-lg border-2 border-indigo-400 shadow-md hover:shadow-lg lg:hover:shadow-xl transition-all duration-300"
          >
            <OrderCard :order="order" />
          </div>
        </div>
      </div>

      <!-- Coleta Column -->
      <div class="kanban-column">
        <div class="column-header bg-orange-600 p-5 sm:p-6 lg:p-7 xl:p-8 rounded-t-lg xl:rounded-t-xl shadow-lg">
          <div class="flex items-center justify-between w-full">
            <h3 class="font-black text-white text-xl sm:text-2xl lg:text-3xl xl:text-4xl truncate mr-2">Collection</h3>
            <span class="badge font-black text-orange-900 bg-white rounded-full px-4 py-2 sm:px-6 sm:py-3 text-lg sm:text-xl lg:text-2xl xl:text-3xl shadow-lg flex-shrink-0">
              {{ (kanbanData.coleta_aguardando?.length || 0) + (kanbanData.coleta_realizada?.length || 0) }}
            </span>
          </div>
        </div>
        <div class="column-content bg-orange-50 p-4 sm:p-5 lg:p-6 xl:p-7 rounded-b-lg xl:rounded-b-xl min-h-72 sm:min-h-96 lg:min-h-[400px] xl:min-h-[500px]">
          <!-- Sub-columns side by side -->
          <div class="flex gap-4 h-full">
            <!-- Aguardando Sub-column -->
            <div class="sub-column flex-1">
              <div class="sub-header bg-orange-500 p-3 rounded-lg mb-3">
                <h4 class="font-bold text-white text-lg">Pending</h4>
              </div>
              <div class="sub-content">
                <div
                  v-for="order in kanbanData.coleta_aguardando || []"
                  :key="order.id"
                  class="order-item bg-white p-4 sm:p-5 lg:p-6 xl:p-7 mb-3 sm:mb-4 lg:mb-5 rounded-lg border-2 border-orange-300 shadow-md hover:shadow-lg lg:hover:shadow-xl transition-all duration-300"
                >
                  <OrderCard :order="order" />
                </div>
              </div>
            </div>
            
            <!-- Realizada Sub-column -->
            <div class="sub-column flex-1">
              <div class="sub-header bg-green-500 p-3 rounded-lg mb-3">
                <h4 class="font-bold text-white text-lg">Completed</h4>
              </div>
              <div class="sub-content">
                <div
                  v-for="order in kanbanData.coleta_realizada || []"
                  :key="order.id"
                  class="order-item bg-white p-4 sm:p-5 lg:p-6 xl:p-7 mb-3 sm:mb-4 lg:mb-5 rounded-lg border-2 border-green-300 shadow-md hover:shadow-lg lg:hover:shadow-xl transition-all duration-300"
                >
                  <OrderCard :order="order" />
                  <div v-if="getMovementHistory(order, 'picked_up')" class="text-sm sm:text-base lg:text-lg text-green-800 font-bold bg-green-200 p-2 sm:p-3 rounded-lg mt-3">
                    {{ getMovementHistory(order, 'picked_up') }}
                  </div>
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
import OrderCard from '@/components/OrderCard.vue'

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
  tracking_code?: string
  order_status?: any
  raw_invoice_data?: any
}

interface KanbanData {
  pedidos_emitidos?: Order[]
  faturamento?: Order[]
  checkout?: Order[]
  embalagem?: Order[]
  etiqueta?: Order[]
  coleta_aguardando?: Order[]
  coleta_realizada?: Order[]
  // Legacy structure support
  aguardando?: Order[]
  separado?: Order[]
  embalado?: Order[]
  coletado?: Order[]
}

interface Stats {
  total?: number
  pedidos_emitidos?: number
  faturamento?: number
  checkout?: number
  embalagem?: number
  etiqueta?: number
  coleta_aguardando?: number
  coleta_realizada?: number
  // Legacy structure support
  aguardando?: number
  separado?: number
  embalado?: number
  coletado?: number
}

const props = defineProps<{
  kanbanData: KanbanData
  stats: Stats
}>()

// Initialize with safe defaults to handle both old and new data structures
const kanbanData = ref<KanbanData>({
  pedidos_emitidos: props.kanbanData?.pedidos_emitidos || props.kanbanData?.aguardando || [],
  faturamento: props.kanbanData?.faturamento || [],
  checkout: props.kanbanData?.checkout || props.kanbanData?.separado || [],
  embalagem: props.kanbanData?.embalagem || props.kanbanData?.embalado || [],
  etiqueta: props.kanbanData?.etiqueta || [],
  coleta_aguardando: props.kanbanData?.coleta_aguardando || [],
  coleta_realizada: props.kanbanData?.coleta_realizada || props.kanbanData?.coletado || [],
})

const stats = ref<Stats>({
  total: props.stats?.total || 0,
  pedidos_emitidos: props.stats?.pedidos_emitidos || props.stats?.aguardando || 0,
  faturamento: props.stats?.faturamento || 0,
  checkout: props.stats?.checkout || props.stats?.separado || 0,
  embalagem: props.stats?.embalagem || props.stats?.embalado || 0,
  etiqueta: props.stats?.etiqueta || 0,
  coleta_aguardando: props.stats?.coleta_aguardando || 0,
  coleta_realizada: props.stats?.coleta_realizada || props.stats?.coletado || 0,
})

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
        if (!order.tracking_code && (update.order as any).tracking_code) {
          order.tracking_code = (update.order as any).tracking_code
        }
        
        // Remove from current column
        column.splice(orderIndex, 1)
        
        // Determine new column based on status
        let newColumn = 'pedidos_emitidos'
        if (order.order_status.picked_up) {
          newColumn = 'coleta_realizada'
        } else if (order.order_status.packing) {
          newColumn = 'coleta_aguardando'
        } else if (order.order_status.picking) {
          newColumn = 'embalagem'
        } else if (order.invoice_number) {
          newColumn = 'faturamento'
        } else if (order.tracking_code) {
          newColumn = 'etiqueta'
        }
        
        // Add to new column
        kanbanData.value[newColumn as keyof KanbanData].push(order)
        
        console.log(`Moved order ${update.order.id} from ${columnKey} to ${newColumn}`)
      }
    })
    
    // Update stats
    stats.value.total = Object.values(kanbanData.value).reduce((sum, column) => sum + column.length, 0)
    stats.value.pedidos_emitidos = kanbanData.value.pedidos_emitidos.length
    stats.value.faturamento = kanbanData.value.faturamento.length
    stats.value.checkout = kanbanData.value.checkout.length
    stats.value.embalagem = kanbanData.value.embalagem.length
    stats.value.etiqueta = kanbanData.value.etiqueta.length
    stats.value.coleta_aguardando = kanbanData.value.coleta_aguardando.length
    stats.value.coleta_realizada = kanbanData.value.coleta_realizada.length
    
    console.log('Kanban updated with new data')
  }
})

// Debug: log the data to see what we're working with
console.log('Kanban data:', props.kanbanData)

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
  width: 100%;
  max-width: 100%;
  overflow-x: hidden;
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
  width: 100%;
  display: flex;
}

.kanban-column {
  display: flex;
  flex-direction: column;
  flex: 1;
  min-width: 200px;
  max-width: calc(14.28% - 0.75rem);
}

@media (min-width: 640px) {
  .kanban-column {
    min-width: 200px;
    max-width: calc(14.28% - 1rem);
  }
}

@media (min-width: 1024px) {
  .kanban-column {
    min-width: 220px;
    max-width: calc(14.28% - 1.5rem);
  }
}

@media (min-width: 1280px) {
  .kanban-column {
    min-width: 240px;
    max-width: calc(14.28% - 2rem);
  }
}

@media (min-width: 1536px) {
  .kanban-column {
    min-width: 260px;
    max-width: calc(14.28% - 2rem);
  }
}

@media (min-width: 1920px) {
  .kanban-column {
    min-width: 280px;
    max-width: calc(14.28% - 2rem);
  }
}

/* Para telas muito pequenas, permitir scroll horizontal */
@media (max-width: 1024px) {
  .kanban-board {
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
  }
  
  .kanban-column {
    flex-shrink: 0;
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
