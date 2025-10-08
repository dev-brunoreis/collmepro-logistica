<template>
  <div class="order-card">
    <!-- Header with order number and date -->
    <div class="flex justify-between items-start mb-3">
      <div class="flex items-center gap-2">
        <span class="font-black text-gray-900 text-lg sm:text-xl lg:text-2xl">#{{ order.numero }}</span>
        <span 
          :class="[
            'px-2 py-1 rounded-full text-xs font-bold',
            getSystemBadgeClass(order.source)
          ]"
        >
          {{ order.source }}
        </span>
      </div>
      <span class="text-sm text-gray-700 font-bold">{{ formatDateOnly(order.data) }}</span>
    </div>

    <!-- Customer name -->
    <div class="text-base sm:text-lg text-gray-800 mb-3 font-bold leading-tight break-words">
      {{ order.contato_nome }}
    </div>

    <!-- Order total -->
    <div class="text-lg sm:text-xl font-black text-green-700 mb-3">
      R$ {{ formatCurrency(order.total) }}
    </div>

    <!-- Order details row -->
    <div class="flex justify-between items-center text-sm text-gray-700 mb-3">
      <!-- NF (Invoice) -->
      <div class="flex items-center gap-2">
        <span class="font-bold text-gray-800">Invoice:</span>
        <span v-if="order.invoice_number" class="font-bold text-gray-900">
          {{ order.invoice_number }}
        </span>
        <span v-else class="text-gray-600 font-bold">No Invoice</span>
      </div>

      <!-- Items quantity -->
      <div class="flex items-center gap-2">
        <span class="font-black text-gray-900">{{ order.items_quantity }}</span>
        <span class="font-bold text-gray-800">{{ order.items_quantity === 1 ? 'item' : 'items' }}</span>
      </div>
    </div>

    <!-- Tracking code -->
    <div v-if="order.tracking_code" class="flex items-center gap-2 text-sm text-gray-700 mb-2">
      <span class="font-bold text-gray-800">Tracking:</span>
      <span class="font-bold text-gray-900">{{ order.tracking_code }}</span>
    </div>

    <!-- System indicator -->
    <div class="flex items-center gap-2 text-xs">
      <div 
        :class="[
          'w-3 h-3 rounded-full',
          getSystemIndicatorClass(order.source)
        ]"
      ></div>
      <span class="font-bold text-gray-600">{{ order.source }}</span>
    </div>
  </div>
</template>

<script setup lang="ts">
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
}

const props = defineProps<{
  order: Order
}>()

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

const getSystemBadgeClass = (source: string): string => {
  switch (source) {
    case 'Collme Pro':
      return 'bg-blue-100 text-blue-800 border border-blue-200'
    case 'Collme Beauty':
      return 'bg-yellow-100 text-yellow-800 border border-yellow-200'
    default:
      return 'bg-gray-100 text-gray-800 border border-gray-200'
  }
}

const getSystemIndicatorClass = (source: string): string => {
  switch (source) {
    case 'Collme Pro':
      return 'bg-blue-500'
    case 'Collme Beauty':
      return 'bg-yellow-500'
    default:
      return 'bg-gray-500'
  }
}
</script>

<style scoped>
.order-card {
  @apply w-full;
}
</style>
