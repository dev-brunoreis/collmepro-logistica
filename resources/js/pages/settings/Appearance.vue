<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { useAppearance } from '@/composables/useAppearance';
import { Moon, Sun } from 'lucide-vue-next';
import { computed } from 'vue';

import HeadingSmall from '@/components/HeadingSmall.vue';
import { type BreadcrumbItem } from '@/types';

import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { edit } from '@/routes/appearance';

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Aparência',
        href: edit().url,
    },
];

const { appearance, updateAppearance } = useAppearance();

const toggleTheme = () => {
    const newTheme = appearance.value === 'light' ? 'dark' : 'light';
    updateAppearance(newTheme);
};

const currentIcon = computed(() => {
    return appearance.value === 'light' ? Moon : Sun;
});

const currentLabel = computed(() => {
    return appearance.value === 'light' ? 'Claro' : 'Escuro';
});
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head title="Aparência" />

        <SettingsLayout>
            <div class="space-y-6">
                <HeadingSmall
                    title="Aparência"
                    description="Atualize as configurações de aparência da sua conta"
                />
                <div class="rounded-lg border border-border bg-card p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="text-lg font-medium">Tema</h3>
                            <p class="text-sm text-muted-foreground">
                                Escolha entre tema claro ou escuro
                            </p>
                        </div>
                        <button
                            @click="toggleTheme"
                            class="flex items-center gap-2 rounded-lg border border-border bg-card px-4 py-2 text-sm hover:bg-muted transition-colors"
                        >
                            <component :is="currentIcon" class="h-4 w-4" />
                            {{ currentLabel }}
                        </button>
                    </div>
                </div>
            </div>
        </SettingsLayout>
    </AppLayout>
</template>
