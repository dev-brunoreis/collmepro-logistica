<template>
  <div class="metric-card">
    <div class="metric-icon">
      <component :is="icon" class="w-6 h-6" />
    </div>
    <div class="metric-content">
      <div class="metric-value">{{ value }}</div>
      <div class="metric-label">{{ label }}</div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { type Component } from 'vue'

interface Props {
  value: string | number
  label: string
  icon: Component
}

defineProps<Props>()
</script>

<style scoped>
.metric-card {
  display: flex;
  align-items: center;
  padding: 1.5rem;
  background-color: white;
  border-radius: 0.75rem;
  border: 1px solid #e5e7eb;
  box-shadow: 0 1px 2px 0 rgb(0 0 0 / 0.05);
  transition: box-shadow 0.2s ease-in-out;
  gap: 1rem;
}

.dark .metric-card {
  background-color: #1f2937;
  border-color: #374151;
}

.metric-card:hover {
  box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1);
}

.metric-icon {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 3rem;
  height: 3rem;
  background-color: #dbeafe;
  border-radius: 0.5rem;
}

.dark .metric-icon {
  background-color: #1e3a8a;
}

.metric-icon svg {
  color: #2563eb;
  width: 1.5rem;
  height: 1.5rem;
}

.dark .metric-icon svg {
  color: #60a5fa;
}

.metric-content {
  display: flex;
  flex-direction: column;
}

.metric-value {
  font-size: 1.5rem;
  font-weight: 700;
  color: #111827;
}

.dark .metric-value {
  color: white;
}

.metric-label {
  font-size: 0.875rem;
  color: #4b5563;
  font-weight: 500;
}

.dark .metric-label {
  color: #9ca3af;
}
</style>
