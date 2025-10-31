<template>
  <div class="order-card">
    <!-- Header with order number and date -->
    <div class="flex justify-between items-start mb-3">
      <div class="flex items-center gap-2">
        <a :href="`https://www.bling.com.br/vendas.php#edit/${order?.bling_id}`" target="_blank" class="font-black text-gray-900 text-lg sm:text-xl lg:text-2xl" :title="`Abrir pedido no Bling`">
        <span class="text-sm">{{ order.numero }}</span>
            <ExternalLink class="h-3 w-3" />
        </a>
      </div>
      <!-- <span 
        :class="[
          'px-2 py-1 rounded-full text-xs font-bold',
          getSystemBadgeColor(order.source)
        ]"
      >
        {{ order.source }}
      </span> -->
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
      <!-- NF (Invoice) - More prominent display -->
      <div class="flex items-center gap-2">
        <span class="font-bold text-gray-800">NF:</span>
        <span v-if="order.invoice_number && order.invoice_number.trim() !== ''" class="font-black text-blue-900 bg-blue-100 px-2 py-1 rounded">
          {{ order.invoice_number }}
        </span>
        <span v-else class="text-gray-600 font-bold">Sem NF</span>
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

    <!-- Prominent tracking code for collection columns -->
    <div v-if="showProminentTracking && order.tracking_code" class="bg-blue-100 border-2 border-blue-300 rounded-lg p-3 mb-3">
      <div class="flex items-center justify-between">
        <div class="flex items-center gap-2">
          <span class="text-blue-800 font-bold text-sm">Código de Rastreio:</span>
          <span class="font-black text-blue-900 text-base">{{ order.tracking_code }}</span>
        </div>
        <div class="text-blue-600 text-xs font-semibold">RASTREAMENTO</div>
      </div>
    </div>

    <!-- Prominent invoice number for collection columns -->
    <div v-if="showProminentTracking && order.invoice_number" class="bg-green-100 border-2 border-green-300 rounded-lg p-3 mb-3">
      <div class="flex items-center justify-between">
        <div class="flex items-center gap-2">
          <span class="text-green-800 font-bold text-sm">Nota Fiscal:</span>
          <span class="font-black text-green-900 text-base">{{ order.invoice_number }}</span>
        </div>
        <div class="text-green-600 text-xs font-semibold">NF</div>
      </div>
    </div>

    <!-- System indicator -->
    <div class="flex items-center gap-2 text-xs">
      <div
        :class="[
          'w-3 h-3 rounded-full',
          getSystemIndicatorColor(order.source)
        ]"
      ></div>
      <span class="font-bold text-gray-600">{{ order.source }}</span>
    </div>
  </div>
</template>

<script setup lang="ts">
import { getSystemBadgeColor, getSystemIndicatorColor } from '@/helpers/systemMapping'

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
  showProminentTracking?: boolean
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
      
      // Handle ISO format - extract date part only to avoid timezone conversion
      if (dateString.includes('T')) {
        const datePart = dateString.split('T')[0];
        const [year, month, day] = datePart.split('-');
        date = new Date(parseInt(year), parseInt(month) - 1, parseInt(day));
      } else {
        date = new Date(dateString);
      }
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

</script>

<style scoped>
.order-card {
  @apply w-full;
}
</style>
