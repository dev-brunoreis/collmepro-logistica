<template>
  <div class="kanban-compact" :class="{ 'kanban-fullscreen': isFullscreen }">
    <!-- Header with fullscreen button -->
    <div class="flex items-center justify-between mb-4">
      <h2 class="text-xl font-bold text-white">Kanban Board</h2>
      <button @click="toggleFullscreen"
        class="flex items-center gap-2 px-3 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition-colors duration-200"
        :title="isFullscreen ? 'Exit fullscreen' : 'Open fullscreen'">
        <Maximize class="w-4 h-4" />
        <span class="cursor-pointer hidden sm:inline">{{ isFullscreen ? 'Diminuir' : 'Expandir' }}</span>
      </button>

    </div>
    <!-- Pedidos Emitidos Layout -->
    <div class="bg-gray-800 rounded-lg shadow-lg overflow-hidden">
      <!-- Seven Column Grid Layout - Always Horizontal -->
      <div class="grid grid-cols-8 min-h-96 gap-0 overflow-hidden" style="min-width: max-content; width: 100%;">
        <!-- Pedidos Emitidos Column (spans 2 columns) -->
        <div class="col-span-2 border-r border-gray-600 min-w-0 flex-shrink-0" style="min-width: 200px;">
          <div class="bg-gray-700 px-4 py-3 text-center border-b border-gray-600">
            <h3 class="text-white font-semibold">Pedidos Emitidos</h3>
          </div>
          <div class="grid grid-cols-2 h-full">
            <!-- Collme Pro Sub-column -->
            <div class="border-r border-gray-600">
              <div class="bg-gray-700 px-2 py-2 text-center border-b border-gray-600">
                <h4 class="text-white text-xs font-medium">Collme Pro</h4>
              </div>
              <div class="p-2 bg-gray-800 h-full overflow-y-auto">
                <div class="space-y-1">
                  <div v-for="order in getOrdersBySource('Collme Pro')" :key="order.id"
                    class="bg-gradient-to-br from-blue-600 to-blue-700 hover:from-blue-700 hover:to-blue-800 border border-blue-500 rounded p-1.5 cursor-pointer transition-all duration-200 hover:transform hover:-translate-y-1 hover:shadow-lg text-white"
                    @click="handleAddOrder(order)">
                    <div class="flex flex-col gap-0.5">
                      <div class="flex justify-between items-center">
                        <span class="font-bold text-xs">{{ order.numero }}</span>
                        <span class="text-xs font-semibold bg-white bg-opacity-20 px-1 py-0.5 rounded">{{
                          getOrderSource(order) }}</span>
                      </div>
                      <div class="flex flex-col gap-0.5 text-xs">
                        <div class="font-semibold truncate">{{ order.contato_nome }}</div>
                        <div class="opacity-90">{{ formatDateOnly(order.data) }}</div>
                        <div class="opacity-90">{{ order.items_quantity }} item(s)</div>
                        <div class="font-semibold border-t border-white border-opacity-20 pt-0.5 mt-0.5">R$ {{
                          formatCurrency(order.total) }}</div>
                      </div>
                    </div>
                  </div>
                  <div v-if="getOrdersBySource('Collme Pro').length === 0"
                    class="text-center text-gray-400 py-2 text-xs">
                    <div
                      class="flex flex-col gap-4"
                    >
                      <div class="w-full border border-gray-600 rounded p-2" v-for="n in 10" :key="n">
                        <div class="flex justify-between items-center mb-4">
                          <span class="font-bold p-1 rounded bg-gray-600">Atacadista</span>
                          <span class="flex font-bold">#3333333</span>
                        </div>

                        <div class="flex items-center justify-center mb-2">
                          <span class="flex">Bruno dos reis</span>
                        </div>
                       
                        <div class="flex items-center mb-2">
                          <span class="flex">Rastreio: asdadsasdadads</span>
                        </div>

                        <div class="flex items-center justify-between mb-2">
                          <span class="font-bold">Nf: 21321231</span>
                          <span>10 itens</span>
                        </div>
                        
                        <div class="flex items-center justify-between">
                          <span>08/10/2025</span>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Collme Beauty Sub-column -->
            <div>
              <div class="bg-gray-700 px-2 py-2 text-center border-b border-gray-600">
                <h4 class="text-white text-xs font-medium">Collme Beauty</h4>
              </div>
              <div class="p-2 bg-gray-800 h-full overflow-y-auto">
                <div class="space-y-1">
                  <div v-for="order in getOrdersBySource('Collme Beauty')" :key="order.id"
                    class="bg-gradient-to-br from-yellow-400 to-yellow-500 hover:from-yellow-500 hover:to-yellow-600 border border-yellow-400 rounded p-1.5 cursor-pointer transition-all duration-200 hover:transform hover:-translate-y-1 hover:shadow-lg text-gray-900"
                    @click="handleAddOrder(order)">
                    <div class="flex flex-col gap-0.5">
                      <div class="flex justify-between items-center">
                        <span class="font-bold text-xs">{{ order.numero }}</span>
                        <span class="text-xs font-semibold bg-black bg-opacity-10 px-1 py-0.5 rounded">{{
                          getOrderSource(order) }}</span>
                      </div>
                      <div class="flex flex-col gap-0.5 text-xs">
                        <div class="font-semibold truncate">{{ order.contato_nome }}</div>
                        <div class="opacity-90">{{ formatDateOnly(order.data) }}</div>
                        <div class="opacity-90">{{ order.items_quantity }} item(s)</div>
                        <div class="font-semibold border-t border-black border-opacity-20 pt-0.5 mt-0.5">R$ {{
                          formatCurrency(order.total) }}</div>
                      </div>
                    </div>
                  </div>
                  <div v-if="getOrdersBySource('Collme Beauty').length === 0"
                    class="text-center text-gray-400 py-2 text-xs italic">
                    Nenhum pedido
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Faturamento/Emissão de NF Column -->
        <div class="border-r border-gray-600 min-w-0 flex-shrink-0" style="min-width: 120px;">
          <div class="bg-gray-700 px-2 py-2 text-center border-b border-gray-600">
            <h3 class="text-white text-xs font-semibold leading-tight">Faturamento/Emissão de NF</h3>
          </div>
          <div class="p-2 bg-gray-800 h-full overflow-y-auto">
            <div class="space-y-1">
              <div v-for="order in kanbanData.faturamento || []" :key="order.id"
                class="bg-gradient-to-br from-green-600 to-green-700 hover:from-green-700 hover:to-green-800 border border-green-500 rounded p-1.5 cursor-pointer transition-all duration-200 hover:transform hover:-translate-y-1 hover:shadow-lg text-white"
                @click="handleAddOrder(order)">
                <div class="flex flex-col gap-0.5">
                  <div class="flex justify-between items-center">
                    <span class="font-bold text-xs">{{ order.numero }}</span>
                    <span class="text-xs font-semibold bg-white bg-opacity-20 px-1 py-0.5 rounded">{{
                      getOrderSource(order) }}</span>
                  </div>
                  <div class="flex flex-col gap-0.5 text-xs">
                    <div class="font-semibold truncate">{{ order.contato_nome }}</div>
                    <div class="opacity-90">{{ formatDateOnly(order.data) }}</div>
                    <div class="opacity-90" v-if="order.invoice_number">NF: {{ order.invoice_number }}</div>
                    <div class="font-semibold border-t border-white border-opacity-20 pt-0.5 mt-0.5">R$ {{
                      formatCurrency(order.total) }}</div>
                  </div>
                </div>
              </div>
              <div v-if="(kanbanData.faturamento || []).length === 0"
                class="text-center text-gray-400 py-2 text-xs italic">
                Nenhum pedido
              </div>
            </div>
          </div>
        </div>

        <!-- Checkout (Picking) Column -->
        <div class="border-r border-gray-600 min-w-0 flex-shrink-0" style="min-width: 120px;">
          <div class="bg-gray-700 px-2 py-2 text-center border-b border-gray-600">
            <h3 class="text-white text-xs font-semibold">Checkout (Picking)</h3>
          </div>
          <div class="p-2 bg-gray-800 h-full overflow-y-auto">
            <div class="space-y-1">
              <div v-for="order in kanbanData.checkout || []" :key="order.id"
                class="bg-gradient-to-br from-orange-600 to-orange-700 hover:from-orange-700 hover:to-orange-800 border border-orange-500 rounded p-1.5 cursor-pointer transition-all duration-200 hover:transform hover:-translate-y-1 hover:shadow-lg text-white"
                @click="handleAddOrder(order)">
                <div class="flex flex-col gap-0.5">
                  <div class="flex justify-between items-center">
                    <span class="font-bold text-xs">{{ order.numero }}</span>
                    <span class="text-xs font-semibold bg-white bg-opacity-20 px-1 py-0.5 rounded">{{
                      getOrderSource(order) }}</span>
                  </div>
                  <div class="flex flex-col gap-0.5 text-xs">
                    <div class="font-semibold truncate">{{ order.contato_nome }}</div>
                    <div class="opacity-90">{{ formatDateOnly(order.data) }}</div>
                    <div class="opacity-90" v-if="order.order_status?.picking_marked_by">
                      Separado por: {{ order.order_status.picking_marked_by }}
                    </div>
                    <div class="font-semibold border-t border-white border-opacity-20 pt-0.5 mt-0.5">R$ {{
                      formatCurrency(order.total) }}</div>
                  </div>
                </div>
              </div>
              <div v-if="(kanbanData.checkout || []).length === 0"
                class="text-center text-gray-400 py-2 text-xs italic">
                Nenhum pedido
              </div>
            </div>
          </div>
        </div>

        <!-- Embalagem (Packing) Column -->
        <div class="border-r border-gray-600 min-w-0 flex-shrink-0" style="min-width: 120px;">
          <div class="bg-gray-700 px-2 py-2 text-center border-b border-gray-600">
            <h3 class="text-white text-xs font-semibold">Embalagem (Packing)</h3>
          </div>
          <div class="p-2 bg-gray-800 h-full overflow-y-auto">
            <div class="space-y-1">
              <div v-for="order in kanbanData.embalagem || []" :key="order.id"
                class="bg-gradient-to-br from-purple-600 to-purple-700 hover:from-purple-700 hover:to-purple-800 border border-purple-500 rounded p-1.5 cursor-pointer transition-all duration-200 hover:transform hover:-translate-y-1 hover:shadow-lg text-white"
                @click="handleAddOrder(order)">
                <div class="flex flex-col gap-0.5">
                  <div class="flex justify-between items-center">
                    <span class="font-bold text-xs">{{ order.numero }}</span>
                    <span class="text-xs font-semibold bg-white bg-opacity-20 px-1 py-0.5 rounded">{{
                      getOrderSource(order) }}</span>
                  </div>
                  <div class="flex flex-col gap-0.5 text-xs">
                    <div class="font-semibold truncate">{{ order.contato_nome }}</div>
                    <div class="opacity-90">{{ formatDateOnly(order.data) }}</div>
                    <div class="opacity-90" v-if="order.order_status?.packing_marked_by">
                      Embalado por: {{ order.order_status.packing_marked_by }}
                    </div>
                    <div class="font-semibold border-t border-white border-opacity-20 pt-0.5 mt-0.5">R$ {{
                      formatCurrency(order.total) }}</div>
                  </div>
                </div>
              </div>
              <div v-if="(kanbanData.embalagem || []).length === 0"
                class="text-center text-gray-400 py-2 text-xs italic">
                Nenhum pedido
              </div>
            </div>
          </div>
        </div>

        <!-- Etiqueta (Cód. Rastreio) Column -->
        <div class="border-r border-gray-600 min-w-0 flex-shrink-0" style="min-width: 120px;">
          <div class="bg-gray-700 px-2 py-2 text-center border-b border-gray-600">
            <h3 class="text-white text-xs font-semibold">Etiqueta (Cód. Rastreio)</h3>
          </div>
          <div class="p-2 bg-gray-800 h-full overflow-y-auto">
            <div class="space-y-1">
              <div v-for="order in kanbanData.etiqueta || []" :key="order.id"
                class="bg-gradient-to-br from-teal-600 to-teal-700 hover:from-teal-700 hover:to-teal-800 border border-teal-500 rounded p-1.5 cursor-pointer transition-all duration-200 hover:transform hover:-translate-y-1 hover:shadow-lg text-white"
                @click="handleAddOrder(order)">
                <div class="flex flex-col gap-0.5">
                  <div class="flex justify-between items-center">
                    <span class="font-bold text-xs">{{ order.numero }}</span>
                    <span class="text-xs font-semibold bg-white bg-opacity-20 px-1 py-0.5 rounded">{{
                      getOrderSource(order) }}</span>
                  </div>
                  <div class="flex flex-col gap-0.5 text-xs">
                    <div class="font-semibold truncate">{{ order.contato_nome }}</div>
                    <div class="opacity-90">{{ formatDateOnly(order.data) }}</div>
                    <div class="opacity-90" v-if="(order as any).tracking_code">
                      Código: {{ (order as any).tracking_code }}
                    </div>
                    <div class="font-semibold border-t border-white border-opacity-20 pt-0.5 mt-0.5">R$ {{
                      formatCurrency(order.total) }}</div>
                  </div>
                </div>
              </div>
              <div v-if="(kanbanData.etiqueta || []).length === 0"
                class="text-center text-gray-400 py-2 text-xs italic">
                Nenhum pedido
              </div>
            </div>
          </div>
        </div>

        <div class="col-span-2 border-r border-gray-600 min-w-0">
          <div class="bg-gray-700 px-4 py-3 text-center border-b border-gray-600">
            <h3 class="text-white font-semibold">Pedidos Emitidos</h3>
          </div>
          <div class="grid grid-cols-2 h-full">
            <!-- Collme Pro Sub-column -->
            <div class="border-r border-gray-600">
              <div class="bg-gray-700 px-2 py-2 text-center border-b border-gray-600">
                <h4 class="text-white text-xs font-medium">Collme Pro</h4>
              </div>
              <div class="p-2 bg-gray-800 h-full overflow-y-auto">
                <div class="space-y-1">
                  <div v-for="order in getOrdersBySource('Collme Pro')" :key="order.id"
                    class="bg-gradient-to-br from-blue-600 to-blue-700 hover:from-blue-700 hover:to-blue-800 border border-blue-500 rounded p-1.5 cursor-pointer transition-all duration-200 hover:transform hover:-translate-y-1 hover:shadow-lg text-white"
                    @click="handleAddOrder(order)">
                    <div class="flex flex-col gap-0.5">
                      <div class="flex justify-between items-center">
                        <span class="font-bold text-xs">{{ order.numero }}</span>
                        <span class="text-xs font-semibold bg-white bg-opacity-20 px-1 py-0.5 rounded">{{
                          getOrderSource(order) }}</span>
                      </div>
                      <div class="flex flex-col gap-0.5 text-xs">
                        <div class="font-semibold truncate">{{ order.contato_nome }}</div>
                        <div class="opacity-90">{{ formatDateOnly(order.data) }}</div>
                        <div class="opacity-90">{{ order.items_quantity }} item(s)</div>
                        <div class="font-semibold border-t border-white border-opacity-20 pt-0.5 mt-0.5">R$ {{
                          formatCurrency(order.total) }}</div>
                      </div>
                    </div>
                  </div>
                  <div v-if="getOrdersBySource('Collme Pro').length === 0"
                    class="text-center text-gray-400 py-2 text-xs italic">
                    Nenhum pedido
                  </div>
                </div>
              </div>
            </div>

            <!-- Collme Beauty Sub-column -->
            <div>
              <div class="bg-gray-700 px-2 py-2 text-center border-b border-gray-600">
                <h4 class="text-white text-xs font-medium">Collme Beauty</h4>
              </div>
              <div class="p-2 bg-gray-800 h-full overflow-y-auto">
                <div class="space-y-1">
                  <div v-for="order in getOrdersBySource('Collme Beauty')" :key="order.id"
                    class="bg-gradient-to-br from-yellow-400 to-yellow-500 hover:from-yellow-500 hover:to-yellow-600 border border-yellow-400 rounded p-1.5 cursor-pointer transition-all duration-200 hover:transform hover:-translate-y-1 hover:shadow-lg text-gray-900"
                    @click="handleAddOrder(order)">
                    <div class="flex flex-col gap-0.5">
                      <div class="flex justify-between items-center">
                        <span class="font-bold text-xs">{{ order.numero }}</span>
                        <span class="text-xs font-semibold bg-black bg-opacity-10 px-1 py-0.5 rounded">{{
                          getOrderSource(order) }}</span>
                      </div>
                      <div class="flex flex-col gap-0.5 text-xs">
                        <div class="font-semibold truncate">{{ order.contato_nome }}</div>
                        <div class="opacity-90">{{ formatDateOnly(order.data) }}</div>
                        <div class="opacity-90">{{ order.items_quantity }} item(s)</div>
                        <div class="font-semibold border-t border-black border-opacity-20 pt-0.5 mt-0.5">R$ {{
                          formatCurrency(order.total) }}</div>
                      </div>
                    </div>
                  </div>
                  <div v-if="getOrdersBySource('Collme Beauty').length === 0"
                    class="text-center text-gray-400 py-2 text-xs italic">
                    Nenhum pedido
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
import { ref, watch, onUnmounted } from 'vue'
import { Maximize, Plus } from 'lucide-vue-next'
import { useOrderBroadcasting } from '@/composables/useBroadcasting'
import OrderCard from './OrderCard.vue'

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

const props = defineProps<{
  kanbanData: KanbanData
}>()

const emit = defineEmits<{
  openFullscreen: []
}>()

// Fullscreen state
const isFullscreen = ref(false)

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

// Broadcasting setup
const { isConnected, lastUpdate } = useOrderBroadcasting()

// Watch for real-time updates and update kanban data
watch(lastUpdate, (update) => {
  if (update) {
    console.log('Kanban Compact received update:', update)

    // Find the order in all columns and update it
    Object.keys(kanbanData.value).forEach(columnKey => {
      const column = kanbanData.value[columnKey as keyof KanbanData]
      if (!column) return
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
        if (column) {
          column.splice(orderIndex, 1)
        }

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
        } else if ((order as any).tracking_code) {
          newColumn = 'etiqueta'
        }

        // Add to new column
        const targetColumn = kanbanData.value[newColumn as keyof KanbanData]
        if (targetColumn) {
          targetColumn.push(order)
        }

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

// Get orders by source (Collme Pro or Collme Beauty)
const getOrdersBySource = (source: string): Order[] => {
  const pedidosEmitidos = kanbanData.value.pedidos_emitidos || []
  return pedidosEmitidos.filter(order => {
    // Map source names to match the data
    if (source === 'Collme Pro') {
      return order.source === 'Collmepro' || order.source === 'Collmepro Atacadista'
    } else if (source === 'Collme Beauty') {
      return order.source === 'Collme Beauty'
    }
    return false
  })
}

// Get order source abbreviation
const getOrderSource = (order: Order): string => {
  if (order.source === 'Collmepro' || order.source === 'Collmepro Atacadista') {
    return 'CP'
  } else if (order.source === 'Collme Beauty') {
    return 'CB'
  }
  return order.source?.substring(0, 2).toUpperCase() || 'UN'
}

// Get order card class based on source
const getOrderCardClass = (order: Order): string => {
  if (order.source === 'Collmepro' || order.source === 'Collmepro Atacadista') {
    return 'collme-pro'
  } else if (order.source === 'Collme Beauty') {
    return 'collme-beauty'
  }
  return 'default'
}

// Handle add order action
const handleAddOrder = (order: Order) => {
  console.log('Adding order to next stage:', order)
  // Here you can implement the logic to move the order to the next stage
  // For now, just emit an event or call a method
  emit('openFullscreen')
}

const toggleFullscreen = () => {
  isFullscreen.value = !isFullscreen.value

  if (isFullscreen.value) {
    // Add escape key listener
    document.addEventListener('keydown', handleEscapeKey)
  } else {
    // Remove escape key listener
    document.removeEventListener('keydown', handleEscapeKey)
  }
}

const handleEscapeKey = (event: KeyboardEvent) => {
  if (event.key === 'Escape' && isFullscreen.value) {
    isFullscreen.value = false
    document.removeEventListener('keydown', handleEscapeKey)
  }
}

// Cleanup on unmount
onUnmounted(() => {
  document.removeEventListener('keydown', handleEscapeKey)
})
</script>

<style scoped>
.kanban-compact {
  height: 100%;
  display: flex;
  flex-direction: column;
  transition: all 0.3s ease;
}

.kanban-fullscreen {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  z-index: 9999;
  background: linear-gradient(135deg, #1f2937 0%, #111827 100%);
  padding: 2rem;
  overflow: auto;
}

.kanban-fullscreen .kanban-board-compact {
  height: calc(100vh - 8rem);
  overflow-y: auto;
}

.kanban-fullscreen .kanban-column-compact {
  width: 300px;
  min-width: 300px;
}

.kanban-fullscreen .column-content-compact {
  max-height: none;
  min-height: calc(100vh - 12rem);
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
  line-clamp: 2;
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

/* Kanban Flow Layout */
.kanban-flow {
  display: grid;
  grid-template-columns: repeat(6, 1fr);
  gap: 0;
  background: transparent;
  border: 1px solid #e0e0e0;
  border-bottom: none;
  border-radius: 8px 8px 0 0;
  overflow: hidden;
}

.kanban-column {
  background: transparent;
  border-right: 1px solid #e0e0e0;
  min-height: 300px;
  display: flex;
  flex-direction: column;
}

.kanban-column:last-child {
  border-right: none;
}

.kanban-column.special-column {
  background: transparent;
}

.column-title {
  font-size: 0.9rem;
  font-weight: 600;
  color: #e2e8f0;
  padding: 0.75rem;
  text-align: center;
  border-bottom: 1px solid #e0e0e0;
  background: transparent;
  margin: 0;
}

.column-content {
  flex: 1;
  padding: 0.75rem;
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.column-content.sub-columns {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 0;
  padding: 0.5rem;
  border-right: 1px solid #e0e0e0;
}

.sub-column {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.sub-column-title {
  font-size: 0.8rem;
  font-weight: 600;
  color: #cbd5e1;
  text-align: center;
  margin: 0;
  padding: 0.25rem;
  background: transparent;
  border-radius: 4px;
  border-bottom: 1px solid #e0e0e0;
}

.orders-list {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
  flex: 1;
}

.order-card {
  border-radius: 6px;
  padding: 0.75rem;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
  transition: transform 0.2s ease, box-shadow 0.2s ease;
  cursor: pointer;
  min-height: 80px;
  display: flex;
  align-items: stretch;
}

.order-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
}

.order-card.collme-pro {
  background: linear-gradient(135deg, #1976d2 0%, #1565c0 100%);
  color: white;
}

.order-card.collme-beauty {
  background: linear-gradient(135deg, #ffeb3b 0%, #fbc02d 100%);
  color: #333;
}

.order-info {
  display: flex;
  align-items: stretch;
  justify-content: space-between;
  gap: 0.5rem;
  width: 100%;
}

.order-details {
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
}

.order-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 0.25rem;
}

.order-number {
  font-weight: 700;
  font-size: 0.9rem;
}

.order-system {
  font-size: 0.7rem;
  font-weight: 600;
  opacity: 0.8;
  background: rgba(255, 255, 255, 0.2);
  padding: 0.125rem 0.25rem;
  border-radius: 3px;
}

.collme-beauty .order-system {
  background: rgba(0, 0, 0, 0.1);
}

.order-data {
  display: flex;
  flex-direction: column;
  gap: 0.125rem;
  font-size: 0.75rem;
  opacity: 0.9;
}

.order-date,
.order-customer,
.order-items,
.order-nf,
.order-tracking {
  font-size: 0.7rem;
  line-height: 1.2;
}

.status-indicator {
  width: 8px;
  height: 8px;
  background-color: #4caf50;
  border-radius: 50%;
  margin-right: 0.25rem;
}

.add-button {
  background: rgba(255, 255, 255, 0.2);
  border: none;
  border-radius: 3px;
  padding: 0.125rem;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: background-color 0.2s ease;
  width: 20px;
  height: 20px;
}

.add-button:hover {
  background: rgba(255, 255, 255, 0.3);
}

.collme-beauty .add-button {
  background: rgba(0, 0, 0, 0.1);
  color: #333;
}

.collme-beauty .add-button:hover {
  background: rgba(0, 0, 0, 0.2);
}

.kanban-card {
  border-radius: 6px;
  padding: 0.75rem;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
  transition: transform 0.2s ease, box-shadow 0.2s ease;
  cursor: pointer;
  min-height: 80px;
  display: flex;
  align-items: stretch;
}

.kanban-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
}

.kanban-card.collme-pro {
  background: linear-gradient(135deg, #1976d2 0%, #1565c0 100%);
  color: white;
}

.kanban-card.collme-beauty {
  background: linear-gradient(135deg, #ffeb3b 0%, #fbc02d 100%);
  color: #333;
}

.kanban-card.default {
  background: linear-gradient(135deg, #9e9e9e 0%, #757575 100%);
  color: white;
}

.card-content {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
  width: 100%;
}

.card-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 0.25rem;
}

.card-details {
  display: flex;
  flex-direction: column;
  gap: 0.125rem;
  font-size: 0.7rem;
  opacity: 0.9;
}

.card-date,
.card-customer,
.card-items,
.card-nf,
.card-tracking {
  font-size: 0.65rem;
  line-height: 1.2;
}

.order-source {
  font-size: 0.7rem;
  font-weight: 600;
  opacity: 0.8;
  background: rgba(255, 255, 255, 0.2);
  padding: 0.125rem 0.25rem;
  border-radius: 3px;
}

.collme-beauty .order-source {
  background: rgba(0, 0, 0, 0.1);
}

.order-number {
  font-size: 0.9rem;
  font-weight: 700;
}

/* Responsive adjustments */
@media (max-width: 1400px) {
  .kanban-flow {
    grid-template-columns: repeat(3, 1fr);
  }

  .kanban-flow .kanban-column:nth-child(n+4) {
    grid-column: span 1;
  }
}

@media (max-width: 1000px) {
  .kanban-flow {
    grid-template-columns: repeat(2, 1fr);
  }

  .kanban-flow .kanban-column:nth-child(n+3) {
    grid-column: span 1;
  }
}

@media (max-width: 768px) {
  .kanban-flow {
    grid-template-columns: 1fr;
    gap: 0.5rem;
  }

  .kanban-column {
    border-right: none;
    border-bottom: 1px solid #e0e0e0;
    min-height: 200px;
  }

  .kanban-column:last-child {
    border-bottom: none;
  }

  .column-content.sub-columns {
    grid-template-columns: 1fr;
    gap: 0.75rem;
  }

  .sub-column-title {
    font-size: 0.8rem;
  }

  .order-number {
    font-size: 0.9rem;
  }
}
</style>
