<template>
  <div class="kanban-compact" :class="{ 'kanban-fullscreen': isFullscreen }">
    <!-- Loading Indicator -->
    <div v-if="loading" class="loading-overlay-compact">
      <div class="loading-content-compact">
        <div class="spinner-compact"></div>
        <h3 class="text-xl font-bold text-white mb-2">Carregando pedidos...</h3>
        <p class="text-sm text-white/80">
          {{ loadingProgress }} / {{ totalOrders }} orders
        </p>
      </div>
    </div>

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
    <div class="bg-gray-800 rounded-lg shadow-lg">
      <!-- Flex Layout - Responsive -->
      <div class="flex gap-0 w-full" style="max-height: 600px;">
        <!-- Pedidos Emitidos Column (spans 3 columns) -->
        <div class="border-r border-gray-600 flex-1" style="max-width: 30%; min-width: 200px;">
          <div class="bg-gray-700 px-4 py-3 text-center border-b border-gray-600">
            <h3 class="text-white font-semibold">Pedidos Emitidos</h3>
          </div>
          <div class="grid grid-cols-3 h-full">
            <!-- Collme Pro Sub-column -->
            <div class="border-r border-gray-600">
              <div class="bg-gray-700 px-2 py-2 text-center border-b border-gray-600">
                <h4 class="text-white text-xs font-medium">Collme Pro</h4>
              </div>
              <div class="p-2 bg-gray-800 overflow-y-auto kanban-column-scroll" style="max-height: 500px;">
                <div class="space-y-1">
                  <div v-for="order in getOrdersBySource('Collme Pro')" :key="order.id"
                    class="bg-gradient-to-br from-blue-600 to-blue-700 hover:from-blue-700 hover:to-blue-800 border border-blue-500 rounded p-1.5 transition-all duration-200 hover:transform hover:shadow-lg text-white"
                    >
                    <div class="flex flex-col gap-0.5">
                      <div class="flex justify-between items-center">
                        <a :href="`/dashboard/orders/${order?.id}`" target="_blank" class="text-primary gap-1 font-medium rounded-md text-xs flex items-center hover:bg-sidebar-border/70" :title="`Abrir pedido no Bling`">
                            <span class="text-sm">{{ order.numero }}</span>
                            <ExternalLink class="h-3 w-3" />
                        </a>
                        <span :class="['text-xs font-semibold px-1 py-0.5 rounded border', getSystemBadgeColor(order.source)]">{{
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
            <div class="border-r border-gray-600">
              <div class="bg-gray-700 px-2 py-2 text-center border-b border-gray-600">
                <h4 class="text-white text-xs font-medium">Collme Beauty</h4>
              </div>
              <div class="p-2 bg-gray-800 overflow-y-auto kanban-column-scroll" style="max-height: 500px;">
                <div class="space-y-1">
                  <div v-for="order in getOrdersBySource('Collme Beauty')" :key="order.id"
                    class="bg-gradient-to-br from-yellow-400 to-yellow-500 hover:from-yellow-500 hover:to-yellow-600 border border-yellow-400 rounded p-1.5 transition-all duration-200 hover:transform hover:shadow-lg text-gray-900"
                    >
                    <div class="flex flex-col gap-0.5">
                      <div class="flex justify-between items-center">
                        <a :href="`/dashboard/orders/${order?.id}`" target="_blank" class="text-primary gap-1 font-medium rounded-md text-xs flex items-center hover:bg-sidebar-border/70" :title="`Abrir pedido no Bling`">
                            <span class="text-sm">{{ order.numero }}</span>
                            <ExternalLink class="h-3 w-3" />
                        </a>
                        <span :class="['text-xs font-semibold px-1 py-0.5 rounded border', getSystemBadgeColor(order.source)]">{{
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

            <!-- Atacadista Sub-column -->
            <div>
              <div class="bg-gray-700 px-2 py-2 text-center border-b border-gray-600">
                <h4 class="text-white text-xs font-medium">Atacadista</h4>
              </div>
              <div class="p-2 bg-gray-800 overflow-y-auto kanban-column-scroll" style="max-height: 500px;">
                <div class="space-y-1">
                  <div v-for="order in getOrdersBySource('Atacadista')" :key="order.id"
                    class="bg-gradient-to-br from-blue-600 to-blue-700 hover:from-blue-700 hover:to-blue-800 border border-blue-500 rounded p-1.5 transition-all duration-200 hover:transform hover:shadow-lg text-white"
                    >
                    <div class="flex flex-col gap-0.5">
                      <div class="flex justify-between items-center">
                        <a :href="`/dashboard/orders/${order?.id}`" target="_blank" class="text-primary gap-1 font-medium rounded-md text-xs flex items-center hover:bg-sidebar-border/70" :title="`Abrir pedido no Bling`">
                            <span class="text-sm">{{ order.numero }}</span>
                            <ExternalLink class="h-3 w-3" />
                        </a>
                        <span :class="['text-xs font-semibold px-1 py-0.5 rounded border', getSystemBadgeColor(order.source)]">{{
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
                  <div v-if="getOrdersBySource('Atacadista').length === 0"
                    class="text-center text-gray-400 py-2 text-xs italic">
                    Nenhum pedido
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Faturamento/Emissão de NF Column -->
        <div class="border-r border-gray-600 flex-1" style="max-width: 12.5%; min-width: 120px;">
          <div class="bg-gray-700 px-2 py-2 text-center border-b border-gray-600">
            <h3 class="text-white text-xs font-semibold leading-tight">Faturamento/Emissão de NF</h3>
          </div>
          <div class="p-2 bg-gray-800 overflow-y-auto kanban-column-scroll" style="max-height: 500px;">
            <div class="space-y-1">
              <div v-for="order in kanbanData.faturamento || []" :key="order.id"
                class="bg-gradient-to-br from-emerald-600 to-emerald-700 hover:from-emerald-700 hover:to-emerald-800 border border-emerald-500 rounded p-1.5 transition-all duration-200 hover:transform hover:shadow-lg text-white">
                <div class="flex flex-col gap-0.5">
                  <div class="flex justify-between items-center">
                    <a :href="`/dashboard/orders/${order?.id}`" target="_blank" class="text-primary gap-1 font-medium rounded-md text-xs flex items-center hover:bg-sidebar-border/70" :title="`Abrir pedido no Bling`">
                            <span class="text-sm">{{ order.numero }}</span>
                            <ExternalLink class="h-3 w-3" />
                        </a>
                    <span :class="['text-xs font-semibold px-1 py-0.5 rounded border', getSystemBadgeColor(order.source)]">{{
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
        <div class="border-r border-gray-600 flex-1" style="max-width: 12.5%; min-width: 120px;">
          <div class="bg-gray-700 px-2 py-2 text-center border-b border-gray-600">
            <h3 class="text-white text-xs font-semibold">Checkout (Picking)</h3>
          </div>
          <div class="p-2 bg-gray-800 overflow-y-auto kanban-column-scroll" style="max-height: 500px;">
            <div class="space-y-1">
              <div v-for="order in kanbanData.checkout || []" :key="order.id"
                class="bg-gradient-to-br from-amber-600 to-amber-700 hover:from-amber-700 hover:to-amber-800 border border-amber-500 rounded p-1.5 transition-all duration-200 hover:transform hover:shadow-lg text-white">
                <div class="flex flex-col gap-0.5">
                  <div class="flex justify-between items-center">
                    <a :href="`/dashboard/orders/${order?.id}`" target="_blank" class="text-primary gap-1 font-medium rounded-md text-xs flex items-center hover:bg-sidebar-border/70" :title="`Abrir pedido no Bling`">
                            <span class="text-sm">{{ order.numero }}</span>
                            <ExternalLink class="h-3 w-3" />
                        </a>
                    <span :class="['text-xs font-semibold px-1 py-0.5 rounded border', getSystemBadgeColor(order.source)]">{{
                      getOrderSource(order) }}</span>
                  </div>
                  <div class="flex flex-col gap-0.5 text-xs">
                    <div class="font-semibold truncate">{{ order.contato_nome }}</div>
                    <div class="opacity-90">{{ formatDateOnly(order.data) }}</div>
                    <div v-if="order.invoice_number" class="bg-green-200 text-green-900 px-1 py-0.5 rounded text-xs font-bold text-center">
                          NF: {{ order.invoice_number }}
                    </div>
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
        <div class="border-r border-gray-600 flex-1" style="max-width: 12.5%; min-width: 120px;">
          <div class="bg-gray-700 px-2 py-2 text-center border-b border-gray-600">
            <h3 class="text-white text-xs font-semibold">Embalagem (Packing)</h3>
          </div>
          <div class="p-2 bg-gray-800 overflow-y-auto kanban-column-scroll" style="max-height: 500px;">
            <div class="space-y-1">
              <div v-for="order in kanbanData.embalagem || []" :key="order.id"
                class="bg-gradient-to-br from-purple-600 to-purple-700 hover:from-purple-700 hover:to-purple-800 border border-purple-500 rounded p-1.5 transition-all duration-200 hover:transform hover:shadow-lg text-white">
                <div class="flex flex-col gap-0.5">
                  <div class="flex justify-between items-center">
                    <a :href="`/dashboard/orders/${order?.id}`" target="_blank" class="text-primary gap-1 font-medium rounded-md text-xs flex items-center hover:bg-sidebar-border/70" :title="`Abrir pedido no Bling`">
                            <span class="text-sm">{{ order.numero }}</span>
                            <ExternalLink class="h-3 w-3" />
                        </a>
                    <span :class="['text-xs font-semibold px-1 py-0.5 rounded border', getSystemBadgeColor(order.source)]">{{
                      getOrderSource(order) }}</span>
                  </div>
                  <div class="flex flex-col gap-0.5 text-xs">
                    <div class="font-semibold truncate">{{ order.contato_nome }}</div>
                    <div class="opacity-90">{{ formatDateOnly(order.data) }}</div>
                    <div v-if="order.invoice_number" class="bg-green-200 text-green-900 px-1 py-0.5 rounded text-xs font-bold text-center">
                          NF: {{ order.invoice_number }}
                    </div>
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
        <div class="border-r border-gray-600 flex-1" style="max-width: 12.5%; min-width: 120px;">
          <div class="bg-gray-700 px-2 py-2 text-center border-b border-gray-600">
            <h3 class="text-white text-xs font-semibold">Etiqueta (Cód. Rastreio)</h3>
          </div>
          <div class="p-2 bg-gray-800 overflow-y-auto kanban-column-scroll" style="max-height: 500px;">
            <div class="space-y-1">
              <div v-for="order in kanbanData.etiqueta || []" :key="order.id"
                class="bg-gradient-to-br from-cyan-600 to-cyan-700 hover:from-cyan-700 hover:to-cyan-800 border border-cyan-500 rounded p-1.5 transition-all duration-200 hover:transform hover:shadow-lg text-white">
                <div class="flex flex-col gap-0.5">
                  <div class="flex justify-between items-center">
                    <a :href="`/dashboard/orders/${order?.id}`" target="_blank" class="text-primary gap-1 font-medium rounded-md text-xs flex items-center hover:bg-sidebar-border/70" :title="`Abrir pedido no Bling`">
                            <span class="text-sm">{{ order.numero }}</span>
                            <ExternalLink class="h-3 w-3" />
                        </a>
                    <span :class="['text-xs font-semibold px-1 py-0.5 rounded border', getSystemBadgeColor(order.source)]">{{
                      getOrderSource(order) }}</span>
                  </div>
                  <div class="flex flex-col gap-0.5 text-xs">
                    <div class="font-semibold truncate">{{ order.contato_nome }}</div>
                    <div class="opacity-90">{{ formatDateOnly(order.data) }}</div>
                    <div v-if="order.invoice_number" class="bg-green-200 text-green-900 px-1 py-0.5 rounded text-xs font-bold text-center">
                          NF: {{ order.invoice_number }}
                    </div>
                    <a
                            v-if="order.tracking_code" 
                            :href="order.tracking_url" 
                            target="_blank" 
                            class="justify-center inline-flex items-center gap-1 font-medium rounded-md px-1 py-0.5 text-xs hover:bg-sidebar-border/70"
                            title="Abrir rastreamento"
                        >
                            <span class="text-sm">{{ order.tracking_code }}</span>
                            <ExternalLink class="h-3 w-3" />
                        </a>
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

        <!-- Coleta Column -->
        <div class="flex-1" style="max-width: 25%; min-width: 200px;">
          <div class="bg-gray-700 px-4 py-3 text-center border-b border-gray-600">
            <h3 class="text-white font-semibold">Coleta</h3>
          </div>
          <div class="grid grid-cols-2 h-full">
            <!-- Aguardando Sub-column -->
            <div class="border-r border-gray-600">
              <div class="bg-gray-700 px-2 py-2 text-center border-b border-gray-600">
                <h4 class="text-white text-xs font-medium">Aguardando</h4>
              </div>
              <div class="p-2 bg-gray-800 overflow-y-auto kanban-column-scroll" style="max-height: 500px;">
                <div class="space-y-1">
                  <div v-for="order in kanbanData.coleta_aguardando || []" :key="order.id"
                    class="bg-gradient-to-br from-red-600 to-red-700 hover:from-red-700 hover:to-red-800 border border-red-500 rounded p-1.5 transition-all duration-200 hover:transform hover:shadow-lg text-white"
                    >
                    <div class="flex flex-col gap-0.5">
                      <div class="flex justify-between items-center">
                        <a :href="`/dashboard/orders/${order?.id}`" target="_blank" class="text-primary gap-1 font-medium rounded-md text-xs flex items-center hover:bg-sidebar-border/70" :title="`Abrir pedido no Bling`">
                            <span class="text-sm">{{ order.numero }}</span>
                            <ExternalLink class="h-3 w-3" />
                        </a>
                        <span :class="['text-xs font-semibold px-1 py-0.5 rounded border', getSystemBadgeColor(order.source)]">{{
                          getOrderSource(order) }}</span>
                      </div>
                      <div class="flex flex-col gap-0.5 text-xs">
                        <div class="font-semibold truncate">{{ order.contato_nome }}</div>
                        <div class="opacity-90">{{ formatDateOnly(order.data) }}</div>
                        <div class="opacity-90">{{ order.items_quantity }} item(s)</div>
                        <a
                            v-if="order.tracking_code" 
                            :href="order.tracking_url" 
                            target="_blank" 
                            class="justify-center inline-flex items-center gap-1 font-medium rounded-md px-1 py-0.5 text-xs hover:bg-sidebar-border/70"
                            title="Abrir rastreamento"
                        >
                            <span class="text-sm">{{ order.tracking_code }}</span>
                            <ExternalLink class="h-3 w-3" />
                        </a>
                        <div v-if="order.invoice_number" class="text-center bg-green-200 text-green-900 px-1 py-0.5 rounded text-xs font-bold">
                          NF: {{ order.invoice_number }}
                        </div>
                        <div class="font-semibold border-t border-white border-opacity-20 pt-0.5 mt-0.5">R$ {{
                          formatCurrency(order.total) }}</div>
                      </div>
                    </div>
                  </div>
                  <div v-if="(kanbanData.coleta_aguardando || []).length === 0"
                    class="text-center text-gray-400 py-2 text-xs italic">
                    Nenhum pedido
                  </div>
                </div>
              </div>
            </div>

            <!-- Realizada Sub-column -->
            <div>
              <div class="bg-gray-700 px-2 py-2 text-center border-b border-gray-600">
                <h4 class="text-white text-xs font-medium">Realizada</h4>
              </div>
              <div class="p-2 bg-gray-800 overflow-y-auto kanban-column-scroll" style="max-height: 500px;">
                <div class="space-y-1">
                  <div v-for="order in kanbanData.coleta_realizada || []" :key="order.id"
                    class="bg-gradient-to-br from-lime-600 to-lime-700 hover:from-lime-700 hover:to-lime-800 border border-lime-500 rounded p-1.5 transition-all duration-200 hover:transform hover:shadow-lg text-white"
                    >
                    <div class="flex flex-col gap-0.5">
                      <div class="flex justify-between items-center">
                        <a :href="`/dashboard/orders/${order?.id}`" target="_blank" class="text-primary gap-1 font-medium rounded-md text-xs flex items-center hover:bg-sidebar-border/70" :title="`Abrir pedido no Bling`">
                            <span class="text-sm">{{ order.numero }}</span>
                            <ExternalLink class="h-3 w-3" />
                        </a>
                        <span :class="['text-xs font-semibold px-1 py-0.5 rounded border', getSystemBadgeColor(order.source)]">{{
                          getOrderSource(order) }}</span>
                      </div>
                      <div class="flex flex-col gap-0.5 text-xs">
                        <div class="font-semibold truncate">{{ order.contato_nome }}</div>
                        <div class="opacity-90">{{ formatDateOnly(order.data) }}</div>
                        <div class="opacity-90">{{ order.items_quantity }} item(s)</div>
                        <div v-if="order.invoice_number" class="bg-green-200 text-green-900 px-1 py-0.5 rounded text-xs font-bold text-center">
                          NF: {{ order.invoice_number }}
                        </div>
                        <a
                            v-if="order.tracking_code" 
                            :href="order.tracking_url" 
                            target="_blank" 
                            class="justify-center inline-flex items-center gap-1 font-medium rounded-md px-1 py-0.5 text-xs hover:bg-sidebar-border/70"
                            title="Abrir rastreamento"
                        >
                            <span class="text-sm">{{ order.tracking_code }}</span>
                            <ExternalLink class="h-3 w-3" />
                        </a>
                        <span
                            v-else-if="order.tracking_code && !order.tracking?.url"
                            class="inline-flex items-center gap-1 font-medium rounded-md px-1 py-0.5 text-xs text-gray-400"
                            title="URL de rastreamento não disponível"
                        >
                            <span class="text-sm">{{ order.tracking_code }}</span>
                        </span>
                        <div v-if="order.invoice_number" class="bg-green-200 text-green-900 px-1 py-0.5 rounded text-xs font-bold text-center">
                          NF: {{ order.invoice_number }}
                        </div>
                        <div class="font-semibold border-t border-white border-opacity-20 pt-0.5 mt-0.5">R$ {{
                          formatCurrency(order.total) }}</div>
                      </div>
                    </div>
                  </div>
                  <div v-if="(kanbanData.coleta_realizada || []).length === 0"
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
import { ref, watch, onMounted, onUnmounted, watchEffect } from 'vue'
import { Maximize, Plus } from 'lucide-vue-next'
import { useOrderBroadcasting } from '@/composables/useBroadcasting'
import { getSystemShortName, getSystemBadgeColor } from '@/helpers/systemMapping'
import OrderCard from './OrderCard.vue'
import { ExternalLink } from 'lucide-vue-next'
import axios from 'axios'

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
  tracking_code?: string
  tracking_url?: string
  rastreamento?: {
    codigo?: string
    descricao?: string
    situacao?: number
    origem?: string
    destino?: string
    ultimaAlteracao?: string
    url?: string
  }
  tracking?: {
    codigo?: string
    descricao?: string
    situacao?: number
    origem?: string
    destino?: string
    ultimaAlteracao?: string
    url?: string
  }
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

// Loading state
const loading = ref(false) // Start as false since data comes from props
const loadingProgress = ref(0)
const totalOrders = ref(0)

// Initialize with empty data for async loading
const kanbanData = ref<KanbanData>({
  pedidos_emitidos: props.kanbanData?.pedidos_emitidos || [],
  faturamento: props.kanbanData?.faturamento || [],
  checkout: props.kanbanData?.checkout || [],
  embalagem: props.kanbanData?.embalagem || [],
  etiqueta: props.kanbanData?.etiqueta || [],
  coleta_aguardando: props.kanbanData?.coleta_aguardando || [],
  coleta_realizada: props.kanbanData?.coleta_realizada || [],
})

// Broadcasting setup
const { isConnected, lastUpdate, lastNewOrder } = useOrderBroadcasting()

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

        // Determine new column based on status (following backend logic)
        // Priority order:
        // 1. Coleta Realizada: picked_up=true
        // 2. Coleta Aguardando: invoice + picking + packing + tracking_code + !picked_up
        // 3. Etiqueta: invoice + picking + packing + !tracking_code
        // 4. Embalagem: invoice + picking + !packing
        // 5. Checkout: invoice + !picking
        // 6. Faturamento: !invoice
        // 7. Pedidos Emitidos: default
        
        let newColumn = 'pedidos_emitidos'
        const hasInvoice = !!order.invoice_number
        const hasTracking = !!(order as any).tracking_code
        
        if (order.order_status.picked_up) {
          // 1. Coleta Realizada
          newColumn = 'coleta_realizada'
        } else if (hasInvoice && order.order_status.picking && order.order_status.packing && hasTracking) {
          // 2. Coleta Aguardando
          newColumn = 'coleta_aguardando'
        } else if (hasInvoice && order.order_status.picking && order.order_status.packing && !hasTracking) {
          // 3. Etiqueta
          newColumn = 'etiqueta'
        } else if (hasInvoice && order.order_status.picking && !order.order_status.packing) {
          // 4. Embalagem
          newColumn = 'embalagem'
        } else if (hasInvoice && !order.order_status.picking) {
          // 5. Checkout
          newColumn = 'checkout'
        } else if (!hasInvoice) {
          // 6. Faturamento
          newColumn = 'faturamento'
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

// Watch for new orders from broadcasting
watch(lastNewOrder, (newOrderEvent) => {
  if (newOrderEvent && newOrderEvent.order) {
    console.log('Kanban Compact received new order:', newOrderEvent)

    const order = newOrderEvent.order

    // Check if order already exists to avoid duplicates
    const orderExists = Object.keys(kanbanData.value).some(columnKey => {
      const column = kanbanData.value[columnKey as keyof KanbanData]
      return column && column.some(existingOrder => existingOrder.id === order.id)
    })

    if (orderExists) {
      console.log('Order already exists in Kanban, skipping:', order.id)
      return
    }

    // Determine the appropriate column based on order status and data
    // Priority order (same as backend logic):
    // 1. Coleta Realizada: picked_up=true
    // 2. Coleta Aguardando: invoice + picking + packing + tracking_code + !picked_up
    // 3. Etiqueta: invoice + picking + packing + !tracking_code
    // 4. Embalagem: invoice + picking + !packing
    // 5. Checkout: invoice + !picking
    // 6. Faturamento: !invoice
    // 7. Pedidos Emitidos: default
    
    let targetColumn = 'pedidos_emitidos'
    const hasInvoice = !!order.invoice_number
    const hasTracking = !!order.tracking_code
    const orderStatus = order.order_status || { picking: false, packing: false, picked_up: false }
    
    if (orderStatus.picked_up) {
      // 1. Coleta Realizada
      targetColumn = 'coleta_realizada'
    } else if (hasInvoice && orderStatus.picking && orderStatus.packing && hasTracking) {
      // 2. Coleta Aguardando
      targetColumn = 'coleta_aguardando'
    } else if (hasInvoice && orderStatus.picking && orderStatus.packing && !hasTracking) {
      // 3. Etiqueta
      targetColumn = 'etiqueta'
    } else if (hasInvoice && orderStatus.picking && !orderStatus.packing) {
      // 4. Embalagem
      targetColumn = 'embalagem'
    } else if (hasInvoice && !orderStatus.picking) {
      // 5. Checkout
      targetColumn = 'checkout'
    } else if (!hasInvoice) {
      // 6. Faturamento
      targetColumn = 'faturamento'
    }

    // Add the new order to the appropriate column
    const column = kanbanData.value[targetColumn as keyof KanbanData]
    if (column) {
      column.push(order as any)
      console.log(`New order ${order.id} (${order.numero}) added to column: ${targetColumn}`)
    } else {
      console.warn(`Target column ${targetColumn} not found for new order ${order.id}`)
    }
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

// Get orders by source (Pro, Beauty or Atacadista) - just show what backend provides
const getOrdersBySource = (source: string): Order[] => {
  // Get orders from pedidos_emitidos column (backend already filtered)
  const pedidosEmitidos = kanbanData.value.pedidos_emitidos || []
  
  return pedidosEmitidos.filter(order => {
    // Must match the source
    if (source === 'Collme Pro') {
      return order.source === 'Pro'
    } else if (source === 'Collme Beauty') {
      return order.source === 'Beauty'
    } else if (source === 'Atacadista') {
      return order.source === 'Atacadista'
    }
    
    return false
  })
}

// Get order source abbreviation
const getOrderSource = (order: Order): string => {
  return getSystemShortName(order.source)
}

// Get order card class based on source
const getOrderCardClass = (order: Order): string => {
  if (order.source === 'Collme Pro') {
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

// Fetch orders asynchronously
const fetchOrdersAsync = async (daysBack: number = 15, loadAll: boolean = false) => {
  try {
    console.log('KanbanCompact: Starting to fetch orders...')
    loading.value = true
    
    const response = await axios.get('/dashboard/data', {
      params: {
        days_back: daysBack,
        load_all: loadAll
      }
    })
    
    console.log('KanbanCompact: Response received:', response.data)
    const data = response.data
    
    // Update kanban data with received orders
    kanbanData.value = {
      pedidos_emitidos: data.kanbanData.pedidos_emitidos || [],
      faturamento: data.kanbanData.faturamento || [],
      checkout: data.kanbanData.checkout || [],
      embalagem: data.kanbanData.embalagem || [],
      etiqueta: data.kanbanData.etiqueta || [],
      coleta_aguardando: data.kanbanData.coleta_aguardando || [],
      coleta_realizada: data.kanbanData.coleta_realizada || [],
    }
    
    // Update progress
    loadingProgress.value = data.metrics?.loaded || 0
    totalOrders.value = data.metrics?.total_all || 0
    
    console.log('KanbanCompact data loaded:', {
      loaded: data.metrics?.loaded,
      total: data.metrics?.total_all,
      columns: Object.keys(kanbanData.value).map(key => ({
        name: key,
        count: kanbanData.value[key as keyof KanbanData]?.length || 0
      }))
    })
    
    console.log('KanbanCompact: Faturamento column has', kanbanData.value.faturamento?.length || 0, 'orders')
  } catch (error) {
    console.error('Error fetching kanban compact orders:', error)
  } finally {
    loading.value = false
  }
}

// Watch for changes in props and update local data
watch(() => props.kanbanData, (newData) => {
  if (newData) {
    // Show loading briefly when data is being updated
    loading.value = true
    
    // Small delay to show loading animation
    setTimeout(() => {
      kanbanData.value = {
        pedidos_emitidos: newData.pedidos_emitidos || [],
        faturamento: newData.faturamento || [],
        checkout: newData.checkout || [],
        embalagem: newData.embalagem || [],
        etiqueta: newData.etiqueta || [],
        coleta_aguardando: newData.coleta_aguardando || [],
        coleta_realizada: newData.coleta_realizada || [],
      }
      
      // Hide loading after data is updated
      loading.value = false
      
      console.log('KanbanCompact data updated from props:', {
        pedidos_emitidos: kanbanData.value.pedidos_emitidos?.length || 0,
        faturamento: kanbanData.value.faturamento?.length || 0,
        checkout: kanbanData.value.checkout?.length || 0,
        embalagem: kanbanData.value.embalagem?.length || 0,
        etiqueta: kanbanData.value.etiqueta?.length || 0,
        coleta_aguardando: kanbanData.value.coleta_aguardando?.length || 0,
        coleta_realizada: kanbanData.value.coleta_realizada?.length || 0,
      })
    }, 300)
  }
}, { immediate: true, deep: true })

// Initialize with props data (no need to fetch on mount)
onMounted(() => {
  console.log('KanbanCompact mounted with data:', {
    pedidos_emitidos: kanbanData.value.pedidos_emitidos?.length || 0,
    faturamento: kanbanData.value.faturamento?.length || 0,
    checkout: kanbanData.value.checkout?.length || 0,
    embalagem: kanbanData.value.embalagem?.length || 0,
    etiqueta: kanbanData.value.etiqueta?.length || 0,
    coleta_aguardando: kanbanData.value.coleta_aguardando?.length || 0,
    coleta_realizada: kanbanData.value.coleta_realizada?.length || 0,
  })
})

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
  position: relative;
}

/* Kanban column responsive adjustments */
.kanban-compact .flex > div {
  transition: all 0.3s ease;
}

/* Ensure all columns fit on screen */
@media (max-width: 1920px) {
  .kanban-compact .flex > div[style*="max-width: 25%"] {
    max-width: 22% !important;
  }
  .kanban-compact .flex > div[style*="max-width: 15%"] {
    max-width: 13% !important;
  }
}

@media (max-width: 1600px) {
  .kanban-compact .flex > div[style*="max-width: 25%"] {
    max-width: 20% !important;
  }
  .kanban-compact .flex > div[style*="max-width: 15%"] {
    max-width: 12% !important;
  }
}

@media (max-width: 1200px) {
  .kanban-compact .flex > div[style*="max-width: 25%"] {
    max-width: 18% !important;
  }
  .kanban-compact .flex > div[style*="max-width: 15%"] {
    max-width: 10% !important;
  }
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

/* Custom scrollbar for kanban columns */
.kanban-column-scroll::-webkit-scrollbar {
  width: 6px;
}

.kanban-column-scroll::-webkit-scrollbar-track {
  background: rgba(255, 255, 255, 0.1);
  border-radius: 3px;
}

.kanban-column-scroll::-webkit-scrollbar-thumb {
  background: rgba(255, 255, 255, 0.3);
  border-radius: 3px;
}

.kanban-column-scroll::-webkit-scrollbar-thumb:hover {
  background: rgba(255, 255, 255, 0.5);
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

/* Loading overlay for compact kanban */
.loading-overlay-compact {
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(0, 0, 0, 0.85);
  display: flex;
  justify-content: center;
  align-items: center;
  z-index: 1000;
  border-radius: 0.75rem;
}

.loading-content-compact {
  text-align: center;
  padding: 1.5rem;
}

/* Spinner animation for compact */
.spinner-compact {
  width: 60px;
  height: 60px;
  margin: 0 auto 1rem;
  border: 6px solid rgba(255, 255, 255, 0.2);
  border-top: 6px solid #ffffff;
  border-radius: 50%;
  animation: spin 1s linear infinite;
}

@keyframes spin {
  0% { transform: rotate(0deg); }
  100% { transform: rotate(360deg); }
}
</style>
