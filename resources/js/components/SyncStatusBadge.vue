<template>
  <div class="sync-status-badge" :class="{ 'is-running': syncStatus?.is_running }">
    <!-- Sync Running State -->
    <div v-if="syncStatus?.is_running" class="flex items-center gap-2 px-2 py-1.5 bg-blue-50 text-blue-700 rounded-md border border-blue-200 shadow-sm">
      <div class="relative">
        <RefreshCw class="w-3.5 h-3.5 animate-spin" />
        <div class="absolute inset-0 w-3.5 h-3.5 border border-blue-300 rounded-full animate-ping opacity-20"></div>
      </div>
      <span class="text-xs font-medium">Sincronizando</span>
    </div>

    <!-- Sync Idle State -->
    <div v-else class="flex items-center gap-2 px-2 py-1.5 bg-gray-50 text-gray-600 rounded-md border border-gray-200 shadow-sm">
      <Clock class="w-3.5 h-3.5" />
      <div class="flex flex-col">
        <span class="text-xs">{{ nextSyncText }}</span>
        <span class="text-xs opacity-60">{{ lastSyncText }}</span>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { RefreshCw, Clock } from 'lucide-vue-next'

interface SyncStatus {
  is_running: boolean
  last_sync_at: string | null
  next_sync_at: string | null
  account_type: string
  timestamp: string
}

const props = defineProps<{
  syncStatus: SyncStatus | null
}>()

const currentTime = ref(new Date())
let intervalId: ReturnType<typeof setInterval> | null = null

// Update current time every second for countdown
onMounted(() => {
  intervalId = setInterval(() => {
    currentTime.value = new Date()
  }, 1000)
})

onUnmounted(() => {
  if (intervalId) {
    clearInterval(intervalId)
  }
})

const getAccountDisplayName = (accountType: string): string => {
  const names: Record<string, string> = {
    'all': 'Todas as contas',
    'pro': 'Collme Pro',
    'atacadista': 'Collme Atacadista',
  }
  return names[accountType] || accountType
}

const formatRelativeTime = (dateString: string | null): string => {
  if (!dateString) return 'Nunca'
  
  const date = new Date(dateString)
  const now = currentTime.value
  const diffInSeconds = Math.floor((now.getTime() - date.getTime()) / 1000)
  
  if (diffInSeconds < 60) return 'há menos de 1 minuto'
  if (diffInSeconds < 3600) {
    const minutes = Math.floor(diffInSeconds / 60)
    return `há ${minutes} minuto${minutes > 1 ? 's' : ''}`
  }
  if (diffInSeconds < 86400) {
    const hours = Math.floor(diffInSeconds / 3600)
    return `há ${hours} hora${hours > 1 ? 's' : ''}`
  }
  
  const days = Math.floor(diffInSeconds / 86400)
  return `há ${days} dia${days > 1 ? 's' : ''}`
}

const formatTimeUntil = (dateString: string | null): string => {
  const now = currentTime.value
  
  // Calculate next sync time (every 30 minutes: 00:00, 00:30, 01:00, etc.)
  const currentMinutes = now.getMinutes()
  const currentSeconds = now.getSeconds()
  
  // Calculate minutes until next 30-minute interval
  let minutesUntilNext = 30 - (currentMinutes % 30)
  if (minutesUntilNext === 30) minutesUntilNext = 0
  
  // If we're exactly at a 30-minute mark, next sync is in 30 minutes
  if (minutesUntilNext === 0) {
    minutesUntilNext = 30
  }
  
  // Subtract current seconds from the total
  const totalSecondsUntilNext = (minutesUntilNext * 60) - currentSeconds
  const finalMinutes = Math.floor(totalSecondsUntilNext / 60)
  const finalSeconds = totalSecondsUntilNext % 60
  
  if (finalMinutes > 0) {
    return `Próxima sincronização em ${finalMinutes}m ${finalSeconds}s`
  }
  
  return `Próxima sincronização em ${finalSeconds}s`
}

const lastSyncText = computed(() => {
  if (!props.syncStatus?.last_sync_at) {
    return 'Nunca sincronizado'
  }
  return `Última: ${formatRelativeTime(props.syncStatus.last_sync_at)}`
})

const nextSyncText = computed(() => {
  return formatTimeUntil(null) // Always calculate based on current time
})
</script>

<style scoped>
.sync-status-badge {
  transition: all 0.3s ease;
}

.sync-status-badge.is-running {
  box-shadow: 0 0 0 1px rgba(59, 130, 246, 0.1);
}

.animate-spin {
  animation: spin 1s linear infinite;
}

.animate-ping {
  animation: ping 2s cubic-bezier(0, 0, 0.2, 1) infinite;
}

@keyframes spin {
  from {
    transform: rotate(0deg);
  }
  to {
    transform: rotate(360deg);
  }
}

@keyframes ping {
  75%, 100% {
    transform: scale(2);
    opacity: 0;
  }
}

/* Smooth transitions for text changes */
.sync-status-badge span {
  transition: opacity 0.2s ease;
}
</style>
