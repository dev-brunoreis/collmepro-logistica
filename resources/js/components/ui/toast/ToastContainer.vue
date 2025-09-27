<template>
  <div class="pointer-events-none fixed bottom-4 right-4 z-50 flex flex-col space-y-4">
    <div class="flex flex-col space-y-4">
      <Toast
        v-for="toast in toasts"
        :key="toast.id"
        :type="toast.type"
        :title="toast.title"
        :message="toast.message"
        :duration="toast.duration"
        @close="removeToast(toast.id)"
      />
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive } from 'vue';
import Toast from './Toast.vue';

interface ToastItem {
  id: string;
  type: 'success' | 'error' | 'warning' | 'info';
  title: string;
  message?: string;
  duration?: number;
}

const toasts = ref<ToastItem[]>([]);

const addToast = (toast: Omit<ToastItem, 'id'>) => {
  const id = Math.random().toString(36).substr(2, 9);
  toasts.value.push({ ...toast, id });
};

const removeToast = (id: string) => {
  const index = toasts.value.findIndex(toast => toast.id === id);
  if (index > -1) {
    toasts.value.splice(index, 1);
  }
};

// Expose methods globally
if (typeof window !== 'undefined') {
  (window as any).toast = {
    success: (title: string, message?: string, duration?: number) => 
      addToast({ type: 'success', title, message, duration }),
    error: (title: string, message?: string, duration?: number) => 
      addToast({ type: 'error', title, message, duration }),
    warning: (title: string, message?: string, duration?: number) => 
      addToast({ type: 'warning', title, message, duration }),
    info: (title: string, message?: string, duration?: number) => 
      addToast({ type: 'info', title, message, duration }),
  };
}
</script>
