<template>
  <Dialog :open="show" @update:open="$emit('update:show', $event)">
    <DialogContent class="sm:max-w-3xl max-h-[90vh] flex flex-col">
      <DialogHeader>
        <DialogTitle>Gerenciar Produtos Visíveis</DialogTitle>
        <DialogDescription>
          Selecione os produtos que deseja exibir no relatório de estoque.
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

      <!-- Toggle All Checkbox -->
      <div v-if="!loading && !error" class="mb-3 flex items-center gap-2">
        <label class="flex items-center gap-2 cursor-pointer">
          <input
            type="checkbox"
            :checked="allFilteredSelected"
            @change="handleToggleAll"
            :disabled="filteredAndSortedProducts.length === 0"
            class="w-4 h-4 rounded border-input text-primary focus:ring-primary cursor-pointer"
          />
          <span class="text-sm text-foreground">
            <span v-if="allFilteredSelected">Desmarcar Todos</span>
            <span v-else>Marcar Todos</span>
          </span>
        </label>
      </div>

      <!-- Products List -->
      <div v-if="!loading && !error" class="flex-1 overflow-y-auto border border-sidebar-border/30 rounded-lg">
        <div class="divide-y divide-sidebar-border/30">
          <div
            v-for="(product, index) in filteredAndSortedProducts"
            :key="`${product.product_id}_${product.source}_${index}`"
            class="p-3 hover:bg-muted/50 transition-colors cursor-pointer"
            @click="handleItemClick(product, $event)"
          >
            <label class="flex items-center gap-3 cursor-pointer" @click.stop>
              <input
                type="checkbox"
                :checked="isProductVisible(product)"
                @change="toggleProduct(product)"
                @click.stop
                class="w-4 h-4 rounded border-input text-primary focus:ring-primary cursor-pointer"
              />
              <div class="flex-1">
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
          {{ visibleCount }} de {{ uniqueProductsCount }} produtos visíveis
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

// Check if a product is visible (inverted logic: visible = not hidden)
const isProductVisible = (product) => {
  return !hiddenProductKeys.value.has(`${product.product_id}_${product.source}`)
}

// Toggle product visibility
const toggleProduct = (product) => {
  emit('toggle-product', product)
}

// Handle click on the entire item (div container)
const handleItemClick = (product, event) => {
  // Only toggle if the click is not on the checkbox or label (they handle themselves)
  if (event.target.type !== 'checkbox' && event.target.tagName !== 'LABEL') {
    toggleProduct(product)
  }
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

  // Sort alphabetically by name (no special sorting for selected products)
  return filtered.sort((a, b) => {
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

// Count visible products (products that are NOT hidden)
const visibleCount = computed(() => {
  const productsMap = new Map()
  props.allProducts.forEach(product => {
    const key = `${product.product_id}_${product.source}`
    if (!productsMap.has(key)) {
      productsMap.set(key, product)
    }
  })
  const totalProducts = productsMap.size
  return totalProducts - props.hiddenProducts.length
})

// Check if all filtered products are selected (visible)
const allFilteredSelected = computed(() => {
  if (filteredAndSortedProducts.value.length === 0) return false
  return filteredAndSortedProducts.value.every(product => isProductVisible(product))
})

// Handle save
const handleSave = () => {
  emit('save', props.hiddenProducts)
}

// Handle toggle all filtered products
const handleToggleAll = (event) => {
  const filtered = filteredAndSortedProducts.value
  if (filtered.length === 0) return
  
  // Get the checked state from the checkbox
  const shouldSelectAll = event.target.checked
  
  // Use current hidden products state
  const currentHiddenKeys = new Set(
    props.hiddenProducts.map(p => `${p.product_id}_${p.source}`)
  )
  
  if (shouldSelectAll) {
    // Select all: remove all filtered products from hidden products
    filtered.forEach(product => {
      const key = `${product.product_id}_${product.source}`
      if (currentHiddenKeys.has(key)) {
        emit('toggle-product', product)
      }
    })
  } else {
    // Deselect all: add all filtered products to hidden products
    filtered.forEach(product => {
      const key = `${product.product_id}_${product.source}`
      if (!currentHiddenKeys.has(key)) {
        emit('toggle-product', product)
      }
    })
  }
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

