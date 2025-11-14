<template>
  <Dialog :open="show" @update:open="$emit('update:show', $event)">
    <DialogContent class="sm:max-w-3xl max-h-[90vh] flex flex-col">
      <DialogHeader>
        <DialogTitle>Gerenciar Produtos Ocultos</DialogTitle>
        <DialogDescription>
          Selecione os produtos que deseja ocultar do relatório de estoque. Produtos marcados aparecerão primeiro na lista.
          <br />
          <span class="text-xs text-muted-foreground mt-1 block">
            Nota: Esta lista mostra todos os produtos do banco de dados, independente do período filtrado no relatório.
          </span>
        </DialogDescription>
      </DialogHeader>

      <!-- Search Bar -->
      <div class="mb-4">
        <input
          type="text"
          v-model="searchQuery"
          placeholder="Buscar produto..."
          class="w-full px-3 py-2 border border-input rounded-md bg-background text-foreground"
        />
      </div>

      <!-- System Filter -->
      <div class="mb-4">
        <label class="block text-sm font-medium text-foreground mb-2">Filtrar por Sistema</label>
        <select
          v-model="sourceFilter"
          class="w-full px-3 py-2 border border-input rounded-md bg-background text-foreground"
        >
          <option value="">Todos</option>
          <option value="Pro">Pro</option>
          <option value="Atacadista">Atacadista</option>
        </select>
      </div>

      <!-- Loading State -->
      <div v-if="loading" class="flex items-center justify-center py-12">
        <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-primary"></div>
        <span class="ml-2 text-muted-foreground">Carregando produtos...</span>
      </div>

      <!-- Error State -->
      <div v-if="error" class="rounded-lg border border-destructive/50 bg-destructive/10 p-4 mb-4">
        <p class="text-destructive">{{ error }}</p>
      </div>

      <!-- Products List -->
      <div v-if="!loading && !error" class="flex-1 overflow-y-auto border border-sidebar-border/30 rounded-lg">
        <div class="divide-y divide-sidebar-border/30">
          <div
            v-for="(product, index) in filteredAndSortedProducts"
            :key="`${product.product_id}_${product.source}_${index}`"
            class="p-3 hover:bg-muted/50 transition-colors"
          >
            <label class="flex items-center gap-3 cursor-pointer">
              <input
                type="checkbox"
                :checked="isProductHidden(product)"
                @change="handleToggleProduct(product, $event)"
                @click.stop
                class="w-4 h-4 rounded border-input text-primary focus:ring-primary cursor-pointer"
              />
              <div class="flex-1" @click="handleToggleProduct(product, $event)">
                <div class="font-medium text-foreground">{{ product.product_name }}</div>
                <div class="text-sm text-muted-foreground">
                  Código: {{ product.product_code }} • Sistema: 
                  <span :class="getSourceBadgeClass(product.source)">
                    {{ product.source }}
                  </span>
                </div>
              </div>
            </label>
          </div>
        </div>

        <!-- Empty State -->
        <div v-if="filteredAndSortedProducts.length === 0" class="text-center py-12">
          <p class="text-muted-foreground">Nenhum produto encontrado.</p>
        </div>
      </div>

      <!-- Footer Actions -->
      <div class="flex items-center justify-between pt-4 border-t border-sidebar-border/30">
        <div class="text-sm text-muted-foreground">
          {{ selectedCount }} de {{ uniqueProductsCount }} produtos selecionados
        </div>
        <div class="flex gap-2">
          <Button
            variant="outline"
            @click="$emit('update:show', false)"
            :disabled="saving"
          >
            Cancelar
          </Button>
          <Button
            @click="handleSave"
            :disabled="saving || loading"
          >
            <span v-if="saving">Salvando...</span>
            <span v-else>Salvar</span>
          </Button>
        </div>
      </div>
    </DialogContent>
  </Dialog>
</template>

<script setup>
import { ref, computed, watch } from 'vue'
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Button } from '@/components/ui/button'

const props = defineProps({
  show: {
    type: Boolean,
    default: false
  },
  allProducts: {
    type: Array,
    default: () => []
  },
  hiddenProducts: {
    type: Array,
    default: () => []
  },
  loading: {
    type: Boolean,
    default: false
  },
  saving: {
    type: Boolean,
    default: false
  },
  error: {
    type: String,
    default: null
  }
})

const emit = defineEmits(['update:show', 'save', 'toggle-product'])

const searchQuery = ref('')
const sourceFilter = ref('')

// Create a Set of hidden product keys for quick lookup
const hiddenProductKeys = computed(() => {
  return new Set(
    props.hiddenProducts.map(p => `${p.product_id}_${p.source}`)
  )
})

// Check if a product is hidden
const isProductHidden = (product) => {
  return hiddenProductKeys.value.has(`${product.product_id}_${product.source}`)
}

// Toggle product visibility
const toggleProduct = (product) => {
  emit('toggle-product', product)
}

// Handle checkbox change with event
const handleToggleProduct = (product, event) => {
  // Prevent event bubbling to avoid multiple toggles
  if (event) {
    event.stopPropagation()
  }
  toggleProduct(product)
}

// Filter and sort products
const filteredAndSortedProducts = computed(() => {
  // First, deduplicate products by product_id + source
  const productsMap = new Map()
  props.allProducts.forEach(product => {
    const key = `${product.product_id}_${product.source}`
    if (!productsMap.has(key)) {
      productsMap.set(key, product)
    }
  })
  let filtered = Array.from(productsMap.values())

  // Apply source filter
  if (sourceFilter.value) {
    filtered = filtered.filter(product => {
      return product.source === sourceFilter.value
    })
  }

  // Apply search filter
  if (searchQuery.value) {
    const query = searchQuery.value.toLowerCase()
    filtered = filtered.filter(product => {
      const name = (product.product_name || '').toLowerCase()
      const code = (product.product_code || '').toLowerCase()
      return name.includes(query) || code.includes(query)
    })
  }

  // Sort: hidden products first, then alphabetical
  return filtered.sort((a, b) => {
    const aHidden = isProductHidden(a)
    const bHidden = isProductHidden(b)

    // If one is hidden and the other isn't, hidden comes first
    if (aHidden && !bHidden) return -1
    if (!aHidden && bHidden) return 1

    // If both have same hidden status, sort alphabetically by name
    const aName = (a.product_name || '').toLowerCase()
    const bName = (b.product_name || '').toLowerCase()
    return aName.localeCompare(bName)
  })
})

// Count unique products (deduplicated)
const uniqueProductsCount = computed(() => {
  const productsMap = new Map()
  props.allProducts.forEach(product => {
    const key = `${product.product_id}_${product.source}`
    if (!productsMap.has(key)) {
      productsMap.set(key, product)
    }
  })
  return productsMap.size
})

// Count selected products
const selectedCount = computed(() => {
  return props.hiddenProducts.length
})

// Handle save
const handleSave = () => {
  emit('save', props.hiddenProducts)
}

// Get source badge class
const getSourceBadgeClass = (source) => {
  const classes = {
    'Pro': 'text-blue-600 dark:text-blue-400',
    'Atacadista': 'text-green-600 dark:text-green-400',
    'Beauty': 'text-pink-600 dark:text-pink-400',
  }
  return classes[source] || 'text-gray-600 dark:text-gray-400'
}

// Reset filters when modal closes
watch(() => props.show, (isOpen) => {
  if (!isOpen) {
    searchQuery.value = ''
    sourceFilter.value = ''
  }
})
</script>

<style scoped>
/* Custom scrollbar for the products list */
.overflow-y-auto::-webkit-scrollbar {
  width: 8px;
}

.overflow-y-auto::-webkit-scrollbar-track {
  background: rgba(0, 0, 0, 0.1);
  border-radius: 4px;
}

.overflow-y-auto::-webkit-scrollbar-thumb {
  background: rgba(0, 0, 0, 0.3);
  border-radius: 4px;
}

.overflow-y-auto::-webkit-scrollbar-thumb:hover {
  background: rgba(0, 0, 0, 0.5);
}

.dark .overflow-y-auto::-webkit-scrollbar-track {
  background: rgba(255, 255, 255, 0.1);
}

.dark .overflow-y-auto::-webkit-scrollbar-thumb {
  background: rgba(255, 255, 255, 0.3);
}

.dark .overflow-y-auto::-webkit-scrollbar-thumb:hover {
  background: rgba(255, 255, 255, 0.5);
}
</style>

