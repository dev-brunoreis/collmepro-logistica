<template>
  <div
    v-if="isVisible"
    class="pointer-events-auto w-full max-w-sm overflow-hidden rounded-lg bg-gray-900 border border-gray-700 shadow-xl backdrop-blur-sm transform transition-all duration-300 ease-in-out"
    :class="{
      'border-l-4 border-green-500': type === 'success',
      'border-l-4 border-red-500': type === 'error',
      'border-l-4 border-yellow-500': type === 'warning',
      'border-l-4 border-blue-500': type === 'info',
      'translate-x-0 opacity-100': isVisible,
      '-translate-x-full opacity-0': !isVisible,
    }"
  >
    <div class="p-3">
      <div class="flex items-center">
        <div class="flex-shrink-0">
          <CheckCircleIcon v-if="type === 'success'" class="h-5 w-5 text-green-400" />
          <XCircleIcon v-else-if="type === 'error'" class="h-5 w-5 text-red-400" />
          <ExclamationTriangleIcon v-else-if="type === 'warning'" class="h-5 w-5 text-yellow-400" />
          <InformationCircleIcon v-else class="h-5 w-5 text-blue-400" />
        </div>
        <div class="ml-3 flex-1">
          <p class="text-sm font-semibold text-white">{{ title }}</p>
          <p v-if="message" class="text-xs text-gray-300">{{ message }}</p>
        </div>
        <div class="ml-3 flex-shrink-0">
          <button
            @click="close"
            class="inline-flex rounded-md text-gray-400 hover:text-gray-200 hover:bg-gray-800 p-1 transition-colors duration-200"
          >
            <span class="sr-only">Fechar</span>
            <XMarkIcon class="h-4 w-4" />
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { CheckCircleIcon, XCircleIcon, ExclamationTriangleIcon, InformationCircleIcon, XMarkIcon } from '@heroicons/vue/24/outline';
import { ref, onMounted } from 'vue';

interface Props {
  type: 'success' | 'error' | 'warning' | 'info';
  title: string;
  message?: string;
  duration?: number;
}

const props = withDefaults(defineProps<Props>(), {
  duration: 5000,
});

const emit = defineEmits<{
  close: [];
}>();

const isVisible = ref(false);

const close = () => {
  isVisible.value = false;
  setTimeout(() => emit('close'), 300);
};

onMounted(() => {
  isVisible.value = true;
  if (props.duration > 0) {
    setTimeout(close, props.duration);
  }
});
</script>
