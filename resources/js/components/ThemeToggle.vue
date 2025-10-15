<script setup lang="ts">
import { useAppearance } from '@/composables/useAppearance';
import { Moon, Sun } from 'lucide-vue-next';
import { computed } from 'vue';

const { appearance, updateAppearance } = useAppearance();

const toggleTheme = () => {
    const newTheme = appearance.value === 'light' ? 'dark' : 'light';
    updateAppearance(newTheme);
};

const currentIcon = computed(() => {
    return appearance.value === 'light' ? Moon : Sun;
});

const currentLabel = computed(() => {
    return appearance.value === 'light' ? 'Escuro' : 'Claro';
});
</script>

<template>
    <button
        @click="toggleTheme"
        class="flex w-full items-center gap-3 rounded-lg px-3 py-2 text-sm text-sidebar-foreground hover:bg-sidebar-accent hover:text-sidebar-accent-foreground transition-colors"
        :title="`Alternar para tema ${currentLabel.toLowerCase()}`"
    >
        <component :is="currentIcon" class="h-4 w-4" />
        <span>{{ currentLabel }}</span>
    </button>
</template>
