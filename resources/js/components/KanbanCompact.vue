<template>
  <div class="kanban-compact" :class="{ 'kanban-fullscreen': isFullscreen }">
    <!-- Loading Indicator removed - now handled by sync status badge -->

    <!-- Header with fullscreen button and sync status -->
    <div class="flex items-center justify-between mb-4">
      <button @click="toggleFullscreen"
        class="flex items-center gap-2 px-3 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition-colors duration-200"
        :title="isFullscreen ? 'Exit fullscreen' : 'Open fullscreen'">
        <Maximize class="w-4 h-4" />
        <span class="cursor-pointer hidden sm:inline">{{ isFullscreen ? 'Diminuir' : 'Expandir' }}</span>
      </button>

      <SyncStatusBadge :syncStatus="syncStatus" />
    </div>
    <!-- Pedidos Emitidos Layout -->
    <div class="bg-card rounded-lg shadow-lg border border-border">
      <!-- Flex Layout - Responsive -->
      <div class="flex gap-0 w-full kanban-layout" style="height: 600px; align-items: stretch;">
        <!-- Pedidos Emitidos Column (spans 3 columns) -->
        <div class="border-r border-border flex-1 flex flex-col" style="max-width: 35%; min-width: 200px;">
          <div class="bg-muted px-4 py-3 text-center border-b border-border">
            <h3 class="text-foreground font-semibold">Pedidos Emitidos</h3>
          </div>
          <div class="grid grid-cols-3 flex-1" style="height: 100%; max-height: 518px;">
            <!-- Collme Pro Sub-column -->
            <div class="border-r border-border flex flex-col">
              <div class="bg-muted px-2 py-2 text-center border-b border-border">
                <h4 class="text-foreground text-xs font-medium">Collme Pro</h4>
              </div>
              <div class="p-2 bg-card overflow-y-auto flex-1" style="max-height: 518px;">
                <div class="space-y-3">
                  <div v-for="order in getOrdersBySource('Collme Pro')" :key="order.id"
                    :class="['rounded p-1.5 transition-all duration-200 hover:transform hover:shadow-lg border', getCardColorClasses(order)]"
                    >
                    <div class="flex flex-col gap-1">
                      <!-- Linha 1: ID do Pedido (sozinho, destaque) -->
                      <div class="flex justify-start items-center">
                        <a :href="`/dashboard/orders/${order?.id}`" target="_blank" class="text-primary gap-1 font-medium rounded-md text-xs flex items-center bg-gray-200 text-green-900 px-1 py-0.5" :title="`Visualizar pedido no sistema de Logística `">
                            <span class="text-sm">{{ order.numero }}</span>
                            <ExternalLink class="h-3 w-3" />
                        </a>
                      </div>
                      
                      <!-- Linha 2: Nome completo (SEM truncate - permite quebra de linha) -->
                      <div class="font-semibold text-xs break-words">
                        {{ order.contato_nome }}
                      </div>
                      
                      <!-- Linha 3: Data e Tag do Sistema juntas -->
                      <div class="flex items-center justify-between gap-1 text-xs opacity-90">
                        <span>{{ formatDateOnly(order.data) }}</span>
                        <span :class="['text-xs font-semibold px-1 py-0.5 rounded border', getSystemBadgeColor(order.source)]">{{ getOrderSource(order) }}</span>
                      </div>
                      
                      <!-- Linha 4: Nota Fiscal (se existir) -->
                      <div v-if="order.invoice_number" class="text-xs opacity-90">
                        NF: {{ order.invoice_number }}
                      </div>
                      
                      <!-- Linha 5: Código de Rastreio (se existir) -->
                      <a v-if="order.tracking_code" :href="order.tracking_url" target="_blank" class="inline-flex items-center gap-1 font-medium rounded-md text-xs" title="Abrir rastreamento">
                        <span class="text-xs">{{ order.tracking_code }}</span>
                        <ExternalLink class="h-3 w-3" />
                      </a>
                      
                      <!-- Linha 6: Informações adicionais (picking/packing - se existir) -->
                      <div v-if="order.order_status?.picking_marked_by" class="text-xs opacity-90">
                        Separado por: {{ order.order_status.picking_marked_by }}
                      </div>
                      <div v-if="order.order_status?.packing_marked_by" class="text-xs opacity-90">
                        Embalado por: {{ order.order_status.packing_marked_by }}
                      </div>
                      
                      <!-- Linha Final: Quantidade de Items (com separador visual) -->
                      <div :class="['font-semibold border-t border-opacity-20 pt-1 mt-0.5 text-xs', getBorderColorClass(order)]">
                        {{ order.items_quantity }} item(s)
                      </div>
                    </div>
                  </div>
                  <div v-if="getOrdersBySource('Collme Pro').length === 0"
                    class="text-center text-muted-foreground py-2 text-xs italic">
                    Nenhum pedido
                  </div>
                </div>
              </div>
            </div>

            <!-- Collme Beauty Sub-column -->
            <div class="border-r border-border flex flex-col">
              <div class="bg-muted px-2 py-2 text-center border-b border-border">
                <h4 class="text-foreground text-xs font-medium">Collme Beauty</h4>
              </div>
              <div class="p-2 bg-card overflow-y-auto flex-1" style="max-height: 518px;">
                <div class="space-y-3">
                  <div v-for="order in getOrdersBySource('Collme Beauty')" :key="order.id"
                    :class="['rounded p-1.5 transition-all duration-200 hover:transform hover:shadow-lg border', getCardColorClasses(order)]"
                    >
                    <div class="flex flex-col gap-1">
                      <!-- Linha 1: ID do Pedido (sozinho, destaque) -->
                      <div class="flex justify-start items-center">
                        <a :href="`/dashboard/orders/${order?.id}`" target="_blank" class="text-primary gap-1 font-medium rounded-md text-xs flex items-center bg-gray-200 text-green-900 px-1 py-0.5" :title="`Visualizar pedido no sistema de Logística `">
                            <span class="text-sm">{{ order.numero }}</span>
                            <ExternalLink class="h-3 w-3" />
                        </a>
                      </div>
                      
                      <!-- Linha 2: Nome completo (SEM truncate - permite quebra de linha) -->
                      <div class="font-semibold text-xs break-words">
                        {{ order.contato_nome }}
                      </div>
                      
                      <!-- Linha 3: Data e Tag do Sistema juntas -->
                      <div class="flex items-center justify-between gap-1 text-xs opacity-90">
                        <span>{{ formatDateOnly(order.data) }}</span>
                        <span :class="['text-xs font-semibold px-1 py-0.5 rounded border', getSystemBadgeColor(order.source)]">{{ getOrderSource(order) }}</span>
                      </div>
                      
                      <!-- Linha 4: Nota Fiscal (se existir) -->
                      <div v-if="order.invoice_number" class="text-xs opacity-90">
                        NF: {{ order.invoice_number }}
                      </div>
                      
                      <!-- Linha 5: Código de Rastreio (se existir) -->
                      <a v-if="order.tracking_code" :href="order.tracking_url" target="_blank" class="inline-flex items-center gap-1 font-medium rounded-md text-xs" title="Abrir rastreamento">
                        <span class="text-xs">{{ order.tracking_code }}</span>
                        <ExternalLink class="h-3 w-3" />
                      </a>
                      
                      <!-- Linha 6: Informações adicionais (picking/packing - se existir) -->
                      <div v-if="order.order_status?.picking_marked_by" class="text-xs opacity-90">
                        Separado por: {{ order.order_status.picking_marked_by }}
                      </div>
                      <div v-if="order.order_status?.packing_marked_by" class="text-xs opacity-90">
                        Embalado por: {{ order.order_status.packing_marked_by }}
                      </div>
                      
                      <!-- Linha Final: Quantidade de Items (com separador visual) -->
                      <div :class="['font-semibold border-t border-opacity-20 pt-1 mt-0.5 text-xs', getBorderColorClass(order)]">
                        {{ order.items_quantity }} item(s)
                      </div>
                    </div>
                  </div>
                  <div v-if="getOrdersBySource('Collme Beauty').length === 0"
                    class="text-center text-muted-foreground py-2 text-xs italic">
                    Nenhum pedido
                  </div>
                </div>
              </div>
            </div>

            <!-- Atacadista Sub-column -->
            <div class="flex flex-col">
              <div class="bg-muted px-2 py-2 text-center border-b border-border">
                <h4 class="text-foreground text-xs font-medium">Atacadista</h4>
              </div>
              <div class="p-2 bg-card overflow-y-auto flex-1" style="max-height: 518px;">
                <div class="space-y-3">
                  <div v-for="order in getOrdersBySource('Atacadista')" :key="order.id"
                    :class="['rounded p-1.5 transition-all duration-200 hover:transform hover:shadow-lg border', getCardColorClasses(order)]"
                    >
                    <div class="flex flex-col gap-1">
                      <!-- Linha 1: ID do Pedido (sozinho, destaque) -->
                      <div class="flex justify-start items-center">
                        <a :href="`/dashboard/orders/${order?.id}`" target="_blank" class="text-primary gap-1 font-medium rounded-md text-xs flex items-center bg-gray-200 text-green-900 px-1 py-0.5" :title="`Visualizar pedido no sistema de Logística `">
                            <span class="text-sm">{{ order.numero }}</span>
                            <ExternalLink class="h-3 w-3" />
                        </a>
                      </div>
                      
                      <!-- Linha 2: Nome completo (SEM truncate - permite quebra de linha) -->
                      <div class="font-semibold text-xs break-words">
                        {{ order.contato_nome }}
                      </div>
                      
                      <!-- Linha 3: Data e Tag do Sistema juntas -->
                      <div class="flex items-center justify-between gap-1 text-xs opacity-90">
                        <span>{{ formatDateOnly(order.data) }}</span>
                        <span :class="['text-xs font-semibold px-1 py-0.5 rounded border', getSystemBadgeColor(order.source)]">{{ getOrderSource(order) }}</span>
                      </div>
                      
                      <!-- Linha 4: Nota Fiscal (se existir) -->
                      <div v-if="order.invoice_number" class="text-xs opacity-90">
                        NF: {{ order.invoice_number }}
                      </div>
                      
                      <!-- Linha 5: Código de Rastreio (se existir) -->
                      <a v-if="order.tracking_code" :href="order.tracking_url" target="_blank" class="inline-flex items-center gap-1 font-medium rounded-md text-xs" title="Abrir rastreamento">
                        <span class="text-xs">{{ order.tracking_code }}</span>
                        <ExternalLink class="h-3 w-3" />
                      </a>
                      
                      <!-- Linha 6: Informações adicionais (picking/packing - se existir) -->
                      <div v-if="order.order_status?.picking_marked_by" class="text-xs opacity-90">
                        Separado por: {{ order.order_status.picking_marked_by }}
                      </div>
                      <div v-if="order.order_status?.packing_marked_by" class="text-xs opacity-90">
                        Embalado por: {{ order.order_status.packing_marked_by }}
                      </div>
                      
                      <!-- Linha Final: Quantidade de Items (com separador visual) -->
                      <div :class="['font-semibold border-t border-opacity-20 pt-1 mt-0.5 text-xs', getBorderColorClass(order)]">
                        {{ order.items_quantity }} item(s)
                      </div>
                    </div>
                  </div>
                  <div v-if="getOrdersBySource('Atacadista').length === 0"
                    class="text-center text-muted-foreground py-2 text-xs italic">
                    Nenhum pedido
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Faturamento/Emissão de NF Column -->
        <div class="border-r border-border flex-1 flex flex-col" style="max-width: 10%; min-width: 120px;">
          <div class="bg-muted px-2 py-2 text-center border-b border-border flex items-center justify-center" style="height: 82px;">
            <h3 class="text-foreground text-xs font-semibold leading-tight">Faturamento/Emissão de NF</h3>
          </div>
          <div class="p-2 bg-card overflow-y-auto flex-1">
            <div class="space-y-3">
              <div v-for="order in kanbanData.faturamento || []" :key="order.id"
                :class="['rounded p-1.5 transition-all duration-200 hover:transform hover:shadow-lg border', getCardColorClasses(order)]">
                <div class="flex flex-col gap-1">
                  <!-- Linha 1: ID do Pedido (sozinho, destaque) -->
                  <div class="flex justify-start items-center">
                    <a :href="`/dashboard/orders/${order?.id}`" target="_blank" class="text-primary gap-1 font-medium rounded-md text-xs flex items-center bg-gray-200 text-green-900 px-1 py-0.5" :title="`Visualizar pedido no sistema de Logística `">
                            <span class="text-sm">{{ order.numero }}</span>
                            <ExternalLink class="h-3 w-3" />
                        </a>
                  </div>
                  
                  <!-- Linha 2: Nome completo (SEM truncate - permite quebra de linha) -->
                  <div class="font-semibold text-xs break-words">
                    {{ order.contato_nome }}
                  </div>
                  
                  <!-- Linha 3: Data e Tag do Sistema juntas -->
                  <div class="flex items-center justify-between gap-1 text-xs opacity-90">
                    <span>{{ formatDateOnly(order.data) }}</span>
                    <span :class="['text-xs font-semibold px-1 py-0.5 rounded border', getSystemBadgeColor(order.source)]">{{ getOrderSource(order) }}</span>
                  </div>
                  
                  <!-- Linha 4: Nota Fiscal (se existir) -->
                  <div v-if="order.invoice_number" class="text-xs opacity-90">
                    NF: {{ order.invoice_number }}
                  </div>
                  
                  <!-- Linha 5: Código de Rastreio (se existir) -->
                  <a v-if="order.tracking_code" :href="order.tracking_url" target="_blank" class="inline-flex items-center gap-1 font-medium rounded-md text-xs" title="Abrir rastreamento">
                    <span class="text-xs">{{ order.tracking_code }}</span>
                    <ExternalLink class="h-3 w-3" />
                  </a>
                  
                  <!-- Linha 6: Informações adicionais (picking/packing - se existir) -->
                  <div v-if="order.order_status?.picking_marked_by" class="text-xs opacity-90">
                    Separado por: {{ order.order_status.picking_marked_by }}
                  </div>
                  <div v-if="order.order_status?.packing_marked_by" class="text-xs opacity-90">
                    Embalado por: {{ order.order_status.packing_marked_by }}
                  </div>
                  
                  <!-- Linha Final: Quantidade de Items (com separador visual) -->
                  <div :class="['font-semibold border-t border-opacity-20 pt-1 mt-0.5 text-xs', getBorderColorClass(order)]">
                    {{ order.items_quantity }} item(s)
                  </div>
                </div>
              </div>
              <div v-if="(kanbanData.faturamento || []).length === 0"
                class="text-center text-muted-foreground py-2 text-xs italic">
                Nenhum pedido
              </div>
            </div>
          </div>
        </div>

        <!-- Checkout (Picking) Column -->
        <div class="border-r border-border flex-1 flex flex-col" style="max-width: 10%; min-width: 120px;">
          <div class="bg-muted px-2 py-2 text-center border-b border-border flex items-center justify-center" style="height: 82px;">
            <h3 class="text-foreground text-xs font-semibold">Checkout (Picking)</h3>
          </div>
          <div class="p-2 bg-card overflow-y-auto flex-1">
            <div class="space-y-3">
              <div v-for="order in kanbanData.checkout || []" :key="order.id"
                :class="['rounded p-1.5 transition-all duration-200 hover:transform hover:shadow-lg border', getCardColorClasses(order)]">
                <div class="flex flex-col gap-1">
                  <!-- Linha 1: ID do Pedido (sozinho, destaque) -->
                  <div class="flex justify-start items-center">
                    <a :href="`/dashboard/orders/${order?.id}`" target="_blank" class="text-primary gap-1 font-medium rounded-md text-xs flex items-center bg-gray-200 text-green-900 px-1 py-0.5" :title="`Visualizar pedido no sistema de Logística `">
                            <span class="text-sm">{{ order.numero }}</span>
                            <ExternalLink class="h-3 w-3" />
                        </a>
                  </div>
                  
                  <!-- Linha 2: Nome completo (SEM truncate - permite quebra de linha) -->
                  <div class="font-semibold text-xs break-words">
                    {{ order.contato_nome }}
                  </div>
                  
                  <!-- Linha 3: Data e Tag do Sistema juntas -->
                  <div class="flex items-center justify-between gap-1 text-xs opacity-90">
                    <span>{{ formatDateOnly(order.data) }}</span>
                    <span :class="['text-xs font-semibold px-1 py-0.5 rounded border', getSystemBadgeColor(order.source)]">{{ getOrderSource(order) }}</span>
                  </div>
                  
                  <!-- Linha 4: Nota Fiscal (se existir) -->
                  <div v-if="order.invoice_number" class="text-xs opacity-90">
                    NF: {{ order.invoice_number }}
                  </div>
                  
                  <!-- Linha 5: Código de Rastreio (se existir) -->
                  <a v-if="order.tracking_code" :href="order.tracking_url" target="_blank" class="inline-flex items-center gap-1 font-medium rounded-md text-xs" title="Abrir rastreamento">
                    <span class="text-xs">{{ order.tracking_code }}</span>
                    <ExternalLink class="h-3 w-3" />
                  </a>
                  
                  <!-- Linha 6: Informações adicionais (picking/packing - se existir) -->
                  <div v-if="order.order_status?.picking_marked_by" class="text-xs opacity-90">
                    Separado por: {{ order.order_status.picking_marked_by }}
                  </div>
                  <div v-if="order.order_status?.packing_marked_by" class="text-xs opacity-90">
                    Embalado por: {{ order.order_status.packing_marked_by }}
                  </div>
                  
                  <!-- Linha Final: Quantidade de Items (com separador visual) -->
                  <div :class="['font-semibold border-t border-opacity-20 pt-1 mt-0.5 text-xs', getBorderColorClass(order)]">
                    {{ order.items_quantity }} item(s)
                  </div>
                </div>
              </div>
              <div v-if="(kanbanData.checkout || []).length === 0"
                class="text-center text-muted-foreground py-2 text-xs italic">
                Nenhum pedido
              </div>
            </div>
          </div>
        </div>

        <!-- Embalagem (Packing) Column -->
        <div class="border-r border-border flex-1 flex flex-col" style="max-width: 10%; min-width: 120px;">
          <div class="bg-muted px-2 py-2 text-center border-b border-border flex items-center justify-center" style="height: 82px;">
            <h3 class="text-foreground text-xs font-semibold">Embalagem (Packing)</h3>
          </div>
          <div class="p-2 bg-card overflow-y-auto flex-1">
            <div class="space-y-3">
              <div v-for="order in kanbanData.embalagem || []" :key="order.id"
                :class="['rounded p-1.5 transition-all duration-200 hover:transform hover:shadow-lg border', getCardColorClasses(order)]">
                <div class="flex flex-col gap-1">
                  <!-- Linha 1: ID do Pedido (sozinho, destaque) -->
                  <div class="flex justify-start items-center">
                    <a :href="`/dashboard/orders/${order?.id}`" target="_blank" class="text-primary gap-1 font-medium rounded-md text-xs flex items-center bg-gray-200 text-green-900 px-1 py-0.5" :title="`Visualizar pedido no sistema de Logística `">
                            <span class="text-sm">{{ order.numero }}</span>
                            <ExternalLink class="h-3 w-3" />
                        </a>
                  </div>
                  
                  <!-- Linha 2: Nome completo (SEM truncate - permite quebra de linha) -->
                  <div class="font-semibold text-xs break-words">
                    {{ order.contato_nome }}
                  </div>
                  
                  <!-- Linha 3: Data e Tag do Sistema juntas -->
                  <div class="flex items-center justify-between gap-1 text-xs opacity-90">
                    <span>{{ formatDateOnly(order.data) }}</span>
                    <span :class="['text-xs font-semibold px-1 py-0.5 rounded border', getSystemBadgeColor(order.source)]">{{ getOrderSource(order) }}</span>
                  </div>
                  
                  <!-- Linha 4: Nota Fiscal (se existir) -->
                  <div v-if="order.invoice_number" class="text-xs opacity-90">
                    NF: {{ order.invoice_number }}
                  </div>
                  
                  <!-- Linha 5: Código de Rastreio (se existir) -->
                  <a v-if="order.tracking_code" :href="order.tracking_url" target="_blank" class="inline-flex items-center gap-1 font-medium rounded-md text-xs" title="Abrir rastreamento">
                    <span class="text-xs">{{ order.tracking_code }}</span>
                    <ExternalLink class="h-3 w-3" />
                  </a>
                  
                  <!-- Linha 6: Informações adicionais (picking/packing - se existir) -->
                  <div v-if="order.order_status?.picking_marked_by" class="text-xs opacity-90">
                    Separado por: {{ order.order_status.picking_marked_by }}
                  </div>
                  <div v-if="order.order_status?.packing_marked_by" class="text-xs opacity-90">
                    Embalado por: {{ order.order_status.packing_marked_by }}
                  </div>
                  
                  <!-- Linha Final: Quantidade de Items (com separador visual) -->
                  <div :class="['font-semibold border-t border-opacity-20 pt-1 mt-0.5 text-xs', getBorderColorClass(order)]">
                    {{ order.items_quantity }} item(s)
                  </div>
                </div>
              </div>
              <div v-if="(kanbanData.embalagem || []).length === 0"
                class="text-center text-muted-foreground py-2 text-xs italic">
                Nenhum pedido
              </div>
            </div>
          </div>
        </div>

        <!-- Etiqueta (Cód. Rastreio) Column -->
        <div class="border-r border-border flex-1 flex flex-col" style="max-width: 10%; min-width: 120px;">
          <div class="bg-muted px-2 py-2 text-center border-b border-border flex items-center justify-center" style="height: 82px;">
            <h3 class="text-foreground text-xs font-semibold">Etiqueta (Cód. Rastreio)</h3>
          </div>
          <div class="p-2 bg-card overflow-y-auto flex-1">
            <div class="space-y-3">
              <div v-for="order in kanbanData.etiqueta || []" :key="order.id"
                :class="['rounded p-1.5 transition-all duration-200 hover:transform hover:shadow-lg border', getCardColorClasses(order)]">
                <div class="flex flex-col gap-1">
                  <!-- Linha 1: ID do Pedido (sozinho, destaque) -->
                  <div class="flex justify-start items-center">
                    <a :href="`/dashboard/orders/${order?.id}`" target="_blank" class="text-primary gap-1 font-medium rounded-md text-xs flex items-center bg-gray-200 text-green-900 px-1 py-0.5" :title="`Visualizar pedido no sistema de Logística `">
                            <span class="text-sm">{{ order.numero }}</span>
                            <ExternalLink class="h-3 w-3" />
                        </a>
                  </div>
                  
                  <!-- Linha 2: Nome completo (SEM truncate - permite quebra de linha) -->
                  <div class="font-semibold text-xs break-words">
                    {{ order.contato_nome }}
                  </div>
                  
                  <!-- Linha 3: Data e Tag do Sistema juntas -->
                  <div class="flex items-center justify-between gap-1 text-xs opacity-90">
                    <span>{{ formatDateOnly(order.data) }}</span>
                    <span :class="['text-xs font-semibold px-1 py-0.5 rounded border', getSystemBadgeColor(order.source)]">{{ getOrderSource(order) }}</span>
                  </div>
                  
                  <!-- Linha 4: Nota Fiscal (se existir) -->
                  <div v-if="order.invoice_number" class="text-xs opacity-90">
                    NF: {{ order.invoice_number }}
                  </div>
                  
                  <!-- Linha 5: Código de Rastreio (se existir) -->
                  <a v-if="order.tracking_code" :href="order.tracking_url" target="_blank" class="inline-flex items-center gap-1 font-medium rounded-md text-xs" title="Abrir rastreamento">
                    <span class="text-xs">{{ order.tracking_code }}</span>
                    <ExternalLink class="h-3 w-3" />
                  </a>
                  
                  <!-- Linha 6: Informações adicionais (picking/packing - se existir) -->
                  <div v-if="order.order_status?.picking_marked_by" class="text-xs opacity-90">
                    Separado por: {{ order.order_status.picking_marked_by }}
                  </div>
                  <div v-if="order.order_status?.packing_marked_by" class="text-xs opacity-90">
                    Embalado por: {{ order.order_status.packing_marked_by }}
                  </div>
                  
                  <!-- Linha Final: Quantidade de Items (com separador visual) -->
                  <div :class="['font-semibold border-t border-opacity-20 pt-1 mt-0.5 text-xs', getBorderColorClass(order)]">
                    {{ order.items_quantity }} item(s)
                  </div>
                </div>
              </div>
              <div v-if="(kanbanData.etiqueta || []).length === 0"
                class="text-center text-muted-foreground py-2 text-xs italic">
                Nenhum pedido
              </div>
            </div>
          </div>
        </div>

        <!-- Coleta Column -->
        <div class="flex-1 flex flex-col" style="max-width: 30%; min-width: 200px;">
          <div class="bg-muted px-4 py-3 text-center border-b border-border">
            <h3 class="text-foreground font-semibold">Coleta</h3>
          </div>
          <div class="grid grid-cols-2 flex-1" style="height: 100%; max-height: 518px;">
            <!-- Aguardando Sub-column -->
            <div class="border-r border-border flex flex-col">
              <div class="bg-muted px-2 py-2 text-center border-b border-border">
                <h4 class="text-foreground text-xs font-medium">Aguardando</h4>
              </div>
              <div class="p-2 bg-card overflow-y-auto flex-1" style="max-height: 518px;">
                <div class="space-y-3">
                  <div v-for="order in kanbanData.coleta_aguardando || []" :key="order.id"
                    :class="['rounded p-1.5 transition-all duration-200 hover:transform hover:shadow-lg border', getCardColorClasses(order)]"
                    >
                    <div class="flex flex-col gap-1">
                      <!-- Linha 1: ID do Pedido (sozinho, destaque) -->
                      <div class="flex justify-start items-center">
                        <a :href="`/dashboard/orders/${order?.id}`" target="_blank" class="text-primary gap-1 font-medium rounded-md text-xs flex items-center bg-gray-200 text-green-900 px-1 py-0.5" :title="`Visualizar pedido no sistema de Logística `">
                            <span class="text-sm">{{ order.numero }}</span>
                            <ExternalLink class="h-3 w-3" />
                        </a>
                      </div>
                      
                      <!-- Linha 2: Nome completo (SEM truncate - permite quebra de linha) -->
                      <div class="font-semibold text-xs break-words">
                        {{ order.contato_nome }}
                      </div>
                      
                      <!-- Linha 3: Data e Tag do Sistema juntas -->
                      <div class="flex items-center justify-between gap-1 text-xs opacity-90">
                        <span>{{ formatDateOnly(order.data) }}</span>
                        <span :class="['text-xs font-semibold px-1 py-0.5 rounded border', getSystemBadgeColor(order.source)]">{{ getOrderSource(order) }}</span>
                      </div>
                      
                      <!-- Linha 4: Nota Fiscal (se existir) -->
                      <div v-if="order.invoice_number" class="text-xs opacity-90">
                        NF: {{ order.invoice_number }}
                      </div>
                      
                      <!-- Linha 5: Código de Rastreio (se existir) -->
                      <a v-if="order.tracking_code" :href="order.tracking_url" target="_blank" class="inline-flex items-center gap-1 font-medium rounded-md text-xs" title="Abrir rastreamento">
                        <span class="text-xs">{{ order.tracking_code }}</span>
                        <ExternalLink class="h-3 w-3" />
                      </a>
                      
                      <!-- Linha 6: Informações adicionais (picking/packing - se existir) -->
                      <div v-if="order.order_status?.picking_marked_by" class="text-xs opacity-90">
                        Separado por: {{ order.order_status.picking_marked_by }}
                      </div>
                      <div v-if="order.order_status?.packing_marked_by" class="text-xs opacity-90">
                        Embalado por: {{ order.order_status.packing_marked_by }}
                      </div>
                      
                      <!-- Linha Final: Quantidade de Items (com separador visual) -->
                      <div :class="['font-semibold border-t border-opacity-20 pt-1 mt-0.5 text-xs', getBorderColorClass(order)]">
                        {{ order.items_quantity }} item(s)
                      </div>
                    </div>
                  </div>
                  <div v-if="(kanbanData.coleta_aguardando || []).length === 0"
                    class="text-center text-muted-foreground py-2 text-xs italic">
                    Nenhum pedido
                  </div>
                </div>
              </div>
            </div>

            <!-- Realizada Sub-column -->
            <div class="flex flex-col">
              <div class="bg-muted px-2 py-2 text-center border-b border-border">
                <h4 class="text-foreground text-xs font-medium">Realizada</h4>
              </div>
              <div class="p-2 bg-card overflow-y-auto flex-1" style="max-height: 518px;">
                <div class="space-y-3">
                  <div v-for="order in kanbanData.coleta_realizada || []" :key="order.id"
                    :class="['rounded p-1.5 transition-all duration-200 hover:transform hover:shadow-lg border', getCardColorClasses(order)]"
                    >
                    <div class="flex flex-col gap-1">
                      <!-- Linha 1: ID do Pedido (sozinho, destaque) -->
                      <div class="flex justify-start items-center">
                        <a :href="`/dashboard/orders/${order?.id}`" target="_blank" class="text-primary gap-1 font-medium rounded-md text-xs flex items-center bg-gray-200 text-black-900 px-1 py-0.5" :title="`Visualizar pedido no sistema de Logística `">
                            <span class="text-sm">{{ order.numero }}</span>
                            <ExternalLink class="h-3 w-3" />
                        </a>
                      </div>
                      
                      <!-- Linha 2: Nome completo (SEM truncate - permite quebra de linha) -->
                      <div class="font-semibold text-xs break-words">
                        {{ order.contato_nome }}
                      </div>
                      
                      <!-- Linha 3: Data e Tag do Sistema juntas -->
                      <div class="flex items-center justify-between gap-1 text-xs opacity-90">
                        <span>{{ formatDateOnly(order.data) }}</span>
                        <span :class="['text-xs font-semibold px-1 py-0.5 rounded border', getSystemBadgeColor(order.source)]">{{ getOrderSource(order) }}</span>
                      </div>
                      
                      <!-- Linha 4: Nota Fiscal (se existir) -->
                      <div v-if="order.invoice_number" class="text-xs opacity-90">
                        NF: {{ order.invoice_number }}
                      </div>
                      
                      <!-- Linha 5: Código de Rastreio (se existir) -->
                      <a v-if="order.tracking_code" :href="order.tracking_url" target="_blank" class="inline-flex items-center gap-1 font-medium rounded-md text-xs" title="Abrir rastreamento">
                        <span class="text-xs">{{ order.tracking_code }}</span>
                        <ExternalLink class="h-3 w-3" />
                      </a>
                      <span v-else-if="order.tracking_code && !order.tracking?.url" class="inline-flex items-center gap-1 font-medium rounded-md px-1 py-0.5 text-xs text-gray-400" title="URL de rastreamento não disponível">
                        <span class="text-xs">{{ order.tracking_code }}</span>
                      </span>
                      
                      <!-- Linha 6: Informações adicionais (picking/packing - se existir) -->
                      <div v-if="order.order_status?.picking_marked_by" class="text-xs opacity-90">
                        Separado por: {{ order.order_status.picking_marked_by }}
                      </div>
                      <div v-if="order.order_status?.packing_marked_by" class="text-xs opacity-90">
                        Embalado por: {{ order.order_status.packing_marked_by }}
                      </div>
                      
                      <!-- Linha Final: Quantidade de Items (com separador visual) -->
                      <div :class="['font-semibold border-t border-opacity-20 pt-1 mt-0.5 text-xs', getBorderColorClass(order)]">
                        {{ order.items_quantity }} item(s)
                      </div>
                    </div>
                  </div>
                  <div v-if="(kanbanData.coleta_realizada || []).length === 0"
                    class="text-center text-muted-foreground py-2 text-xs italic">
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
import SyncStatusBadge from './SyncStatusBadge.vue'
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

// Loading state removed - now handled by sync status badge

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
const { isConnected, lastUpdate, lastNewOrder, lastOrderUpdate, lastSyncStatus } = useOrderBroadcasting()

// Sync status state
const syncStatus = ref<any>(null)

// Polling state
const pollingInterval = ref<ReturnType<typeof setInterval> | null>(null)
const isPolling = ref(false)

// Watch for sync status updates from broadcasting
watch(lastSyncStatus, (newSyncStatus) => {
  if (newSyncStatus) {
    syncStatus.value = newSyncStatus
    console.log('Sync status updated via broadcasting:', newSyncStatus)
  }
})

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
        // 5. Pedidos Emitidos: !invoice
        // 6. Faturamento: invoice + !picking
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
        } else if (!hasInvoice) {
          // 5. Pedidos Emitidos
          newColumn = 'pedidos_emitidos'
        } else if (hasInvoice && !order.order_status.picking) {
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
    // 5. Pedidos Emitidos: !invoice
    // 6. Faturamento: invoice + !picking
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
    } else if (!hasInvoice) {
      // 5. Pedidos Emitidos
      targetColumn = 'pedidos_emitidos'
    } else if (hasInvoice && !orderStatus.picking) {
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

// Watch for order updates from broadcasting
watch(lastOrderUpdate, (updateEvent) => {
  if (updateEvent && updateEvent.order) {
    console.log('Kanban Compact received order update:', updateEvent)

    const updatedOrder = updateEvent.order
    const changeTypes = updateEvent.change_types || []

    // Find the order in all columns
    let foundOrder: any = null
    let foundColumnKey: string | null = null

    Object.keys(kanbanData.value).forEach(columnKey => {
      const column = kanbanData.value[columnKey as keyof KanbanData]
      if (!column) return
      
      const orderIndex = column.findIndex(order => order.id === updatedOrder.id)
      if (orderIndex !== -1) {
        foundOrder = column[orderIndex]
        foundColumnKey = columnKey
      }
    })

    if (!foundOrder) {
      console.log('Order not found in Kanban, ignoring update:', updatedOrder.id)
      return
    }

    // 1. Handle cancellation - remove from Kanban
    if (changeTypes.includes('cancelled') && updatedOrder.situacao_id === 12) {
      console.log(`Order ${updatedOrder.numero} was cancelled, removing from Kanban`)
      if (foundColumnKey) {
        const column = kanbanData.value[foundColumnKey as keyof KanbanData]
        if (column) {
        const orderIndex = column.findIndex(order => order.id === updatedOrder.id)
        if (orderIndex !== -1) {
          column.splice(orderIndex, 1)
            console.log(`Order ${updatedOrder.id} removed from column: ${foundColumnKey}`)
          }
        }
      }
      // TODO: Show toast notification when toast system is implemented
      return
    }

    // 2. Update order data in place
    if (foundOrder) {
      // Update basic fields
      if (updateEvent.changed_fields.includes('total')) {
        foundOrder.total = updatedOrder.total
      }
      if (updateEvent.changed_fields.includes('contato_nome')) {
        foundOrder.contato_nome = updatedOrder.contato_nome
      }
      
      // Update invoice if added (primary check)
      if (changeTypes.includes('invoice_added') && updatedOrder.invoice_number) {
        foundOrder.invoice_number = updatedOrder.invoice_number
        console.log(`Invoice added to order ${updatedOrder.numero}: ${updatedOrder.invoice_number}`)
      }
      // Fallback: Update invoice if present in update data (even without invoice_added change type)
      else if (updatedOrder.invoice_number && !foundOrder.invoice_number) {
        foundOrder.invoice_number = updatedOrder.invoice_number
        console.log(`Invoice updated via fallback for order ${updatedOrder.numero}: ${updatedOrder.invoice_number}`)
      }
      
      // Update tracking if added (primary check)
      if (changeTypes.includes('tracking_added') && updatedOrder.tracking_code) {
        foundOrder.tracking_code = updatedOrder.tracking_code
        foundOrder.tracking_url = updatedOrder.tracking_url
        console.log(`Tracking added to order ${updatedOrder.numero}: ${updatedOrder.tracking_code}`)
      }
      // Fallback: Update tracking if present in update data (even without tracking_added change type)
      else if (updatedOrder.tracking_code && !foundOrder.tracking_code) {
        foundOrder.tracking_code = updatedOrder.tracking_code
        foundOrder.tracking_url = updatedOrder.tracking_url
        console.log(`Tracking updated via fallback for order ${updatedOrder.numero}: ${updatedOrder.tracking_code}`)
      }
      
      // Update order status
      if (updatedOrder.order_status) {
        foundOrder.order_status = updatedOrder.order_status
      }
    }

    // 3. Recalculate column if invoice or tracking was added (or updated via fallback)
    const invoiceWasUpdated = changeTypes.includes('invoice_added') || 
      (updatedOrder.invoice_number && !foundOrder?.invoice_number)
    const trackingWasUpdated = changeTypes.includes('tracking_added') || 
      (updatedOrder.tracking_code && !foundOrder?.tracking_code)
    
    if (invoiceWasUpdated || trackingWasUpdated) {
      // Determine new column
      let targetColumn = 'pedidos_emitidos'
      const hasInvoice = !!updatedOrder.invoice_number
      const hasTracking = !!updatedOrder.tracking_code
      const orderStatus = updatedOrder.order_status || { picking: false, packing: false, picked_up: false }
      
      if (orderStatus.picked_up) {
        targetColumn = 'coleta_realizada'
      } else if (hasInvoice && orderStatus.picking && orderStatus.packing && hasTracking) {
        targetColumn = 'coleta_aguardando'
      } else if (hasInvoice && orderStatus.picking && orderStatus.packing && !hasTracking) {
        targetColumn = 'etiqueta'
      } else if (hasInvoice && orderStatus.picking && !orderStatus.packing) {
        targetColumn = 'embalagem'
      } else if (hasInvoice && !orderStatus.picking) {
        targetColumn = 'faturamento'
      } else if (!hasInvoice) {
        targetColumn = 'pedidos_emitidos'
      }

      // Move if needed
      if (foundColumnKey && foundColumnKey !== targetColumn) {
        const oldColumn = kanbanData.value[foundColumnKey as keyof KanbanData]
        const newColumn = kanbanData.value[targetColumn as keyof KanbanData]
        
        if (oldColumn && newColumn) {
          const orderIndex = oldColumn.findIndex(order => order.id === updatedOrder.id)
          if (orderIndex !== -1) {
            const [orderToMove] = oldColumn.splice(orderIndex, 1)
            // Update the order with latest data before moving
            Object.assign(orderToMove, foundOrder)
            newColumn.push(orderToMove)
            console.log(`Order ${updatedOrder.numero} moved from ${foundColumnKey} to ${targetColumn}`)
          }
        }
      }
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

// Get dynamic card color classes based on order source
const getCardColorClasses = (order: Order): string => {
  if (order.source === 'Pro') {
    return 'bg-gradient-to-br from-blue-600 to-blue-700 hover:from-blue-700 hover:to-blue-800 border-blue-500 text-white'
  } else if (order.source === 'Beauty') {
    return 'bg-gradient-to-br from-yellow-400 to-yellow-500 hover:from-yellow-500 hover:to-yellow-600 border-yellow-400 text-gray-900'
  } else if (order.source === 'Atacadista') {
    return 'bg-gradient-to-br from-green-600 to-green-700 hover:from-green-700 hover:to-green-800 border-green-500 text-white'
  }
  return 'bg-gradient-to-br from-gray-600 to-gray-700 hover:from-gray-700 hover:to-gray-800 border-gray-500 text-white'
}

// Get border color based on order source (for internal card borders)
const getBorderColorClass = (order: Order): string => {
  // Light background (Beauty) uses dark borders, dark backgrounds use light borders
  return order.source === 'Beauty' ? 'border-black' : 'border-white'
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

// Fetch orders asynchronously (simplified - no loading UI)
const fetchOrdersAsync = async (daysBack: number = 7, loadAll: boolean = false) => {
  try {
    console.log('KanbanCompact: Starting to fetch orders...')
    
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
  }
}

// Watch for changes in props and update local data (no loading animation)
watch(() => props.kanbanData, (newData) => {
  if (newData) {
    kanbanData.value = {
      pedidos_emitidos: newData.pedidos_emitidos || [],
      faturamento: newData.faturamento || [],
      checkout: newData.checkout || [],
      embalagem: newData.embalagem || [],
      etiqueta: newData.etiqueta || [],
      coleta_aguardando: newData.coleta_aguardando || [],
      coleta_realizada: newData.coleta_realizada || [],
    }
    
    console.log('KanbanCompact data updated from props:', {
      pedidos_emitidos: kanbanData.value.pedidos_emitidos?.length || 0,
      faturamento: kanbanData.value.faturamento?.length || 0,
      checkout: kanbanData.value.checkout?.length || 0,
      embalagem: kanbanData.value.embalagem?.length || 0,
      etiqueta: kanbanData.value.etiqueta?.length || 0,
      coleta_aguardando: kanbanData.value.coleta_aguardando?.length || 0,
      coleta_realizada: kanbanData.value.coleta_realizada?.length || 0,
    })
  }
}, { immediate: true, deep: true })

// Fetch sync status via API (for polling)
const fetchSyncStatus = async () => {
  try {
    const response = await axios.get('/dashboard/api/sync-status')
    const newStatus = response.data
    
    // Only update if status actually changed to avoid unnecessary re-renders
    if (!syncStatus.value || 
        syncStatus.value.is_running !== newStatus.is_running ||
        syncStatus.value.last_sync_at !== newStatus.last_sync_at) {
      syncStatus.value = newStatus
      console.log('Sync status fetched via polling:', newStatus)
    }
    
    return newStatus
  } catch (error) {
    console.error('Error fetching sync status:', error)
    return null
  }
}

// Start adaptive polling
const startPolling = () => {
  if (isPolling.value) {
    console.log('Polling already active, skipping start')
    return
  }
  
  isPolling.value = true
  console.log('Starting adaptive sync status polling')
  
  const poll = async () => {
    if (!isPolling.value) return
    
    const status = await fetchSyncStatus()
    if (status) {
      // Adjust polling interval based on sync status
      const interval = status.is_running ? 5000 : 30000 // 5s when running, 30s when idle
      
      // Clear existing interval and set new one
      if (pollingInterval.value) {
        clearInterval(pollingInterval.value)
      }
      
      pollingInterval.value = setTimeout(poll, interval)
    } else {
      // On error, retry in 30 seconds
      pollingInterval.value = setTimeout(poll, 30000)
    }
  }
  
  // Start first poll immediately
  poll()
}

// Stop polling
const stopPolling = () => {
  if (pollingInterval.value) {
    clearTimeout(pollingInterval.value)
    pollingInterval.value = null
  }
  isPolling.value = false
  console.log('Stopped sync status polling')
}

// Initialize with props data and fetch sync status
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
  
  // Fetch initial sync status and start polling
  fetchSyncStatus().then(() => {
    startPolling()
  })
})

// Cleanup on unmount
onUnmounted(() => {
  document.removeEventListener('keydown', handleEscapeKey)
  stopPolling()
})
</script>

<style scoped>
.kanban-compact {
  height: 100%;
  display: flex;
  flex-direction: column;
  transition: all 0.3s ease;
  position: relative;
  -webkit-overflow-scrolling: touch; /* Smooth scrolling on iOS */
}

/* Kanban column responsive adjustments */
.kanban-compact .flex > div {
  transition: all 0.3s ease;
}

/* Ensure all columns have equal height and proper alignment */
.kanban-layout {
  display: flex;
  align-items: stretch;
}

.kanban-layout > div {
  display: flex;
  flex-direction: column;
  height: 100%;
}

/* Ensure sub-columns also have equal height */
.kanban-layout .grid {
  display: grid;
  height: 100%;
}

.kanban-layout .grid > div {
  display: flex;
  flex-direction: column;
  height: 100%;
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

/* Tablet Responsive - Improved Layout */
@media (max-width: 1024px) and (min-width: 769px) {
  .kanban-compact {
    overflow-y: auto;
    max-height: calc(100vh - 80px);
  }

  .kanban-layout {
    overflow-x: auto;
    overflow-y: visible;
  }

  .kanban-layout > div {
    min-width: 150px !important;
  }
}

/* Mobile Responsive - Vertical Scrollable Layout */
@media (max-width: 768px) {
  .kanban-compact {
    overflow-y: auto;
    max-height: calc(100vh - 100px);
  }

  .kanban-layout {
    flex-direction: column !important;
    max-height: none !important;
    overflow-y: visible;
  }

  .kanban-layout > div {
    max-width: 100% !important;
    min-width: 100% !important;
    border-right: none !important;
    border-bottom: 1px solid;
  }

  .kanban-layout > div:last-child {
    border-bottom: none;
  }

  .kanban-layout .grid {
    grid-template-columns: repeat(3, 1fr);
    width: 100%;
  }

  .kanban-layout .grid.grid-cols-2 {
    grid-template-columns: repeat(2, 1fr);
  }

  .kanban-column-scroll {
    max-height: 300px !important;
  }

  /* Hide fullscreen button text on mobile */
  .kanban-compact button span {
    display: none;
  }

  /* Better card sizing on mobile */
  .kanban-layout .bg-gradient-to-br {
    font-size: 0.85rem;
  }
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

/* Spinner animation for compact */

@keyframes spin {
  0% { transform: rotate(0deg); }
  100% { transform: rotate(360deg); }
}
</style>
