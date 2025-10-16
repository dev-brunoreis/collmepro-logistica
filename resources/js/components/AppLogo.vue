<script setup lang="ts">
import AppLogoIcon from '@/components/AppLogoIcon.vue';

const appTitle = import.meta.env.VITE_APP_NAME || 'Collmepro Logistics';
</script>

<template>
    <div class="ml-1 grid flex-1 text-left text-sm">
        <span class="mb-0.5 truncate leading-tight font-semibold"
            >{{ appTitle }}</span
        >
    </div>
</template>
