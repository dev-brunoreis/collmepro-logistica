<script setup lang="ts">
import UserInfo from '@/components/UserInfo.vue';
import {
    DropdownMenuGroup,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
} from '@/components/ui/dropdown-menu';
import { logout } from '@/routes';
import { edit } from '@/routes/profile';
import type { User } from '@/types';
import { Link, router } from '@inertiajs/vue3';
import { LogOut, Settings, User as UserIcon, Moon, Sun } from 'lucide-vue-next';
import { useAppearance } from '@/composables/useAppearance';
import { computed } from 'vue';

interface Props {
    user: User;
}

const { appearance, updateAppearance } = useAppearance();

const handleLogout = () => {
    router.flushAll();
};

const toggleTheme = () => {
    const newTheme = appearance.value === 'light' ? 'dark' : 'light';
    updateAppearance(newTheme);
};

const currentIcon = computed(() => {
    return appearance.value === 'light' ? Moon : Sun;
});

const currentLabel = computed(() => {
    return appearance.value === 'light' ? 'Tema Escuro' : 'Tema Claro';
});

defineProps<Props>();
</script>

<template>
    <DropdownMenuLabel class="p-0 font-normal">
        <div class="flex items-center gap-2 px-1 py-1.5 text-left text-sm">
            <UserInfo :user="user" :show-email="true" />
        </div>
    </DropdownMenuLabel>
    <DropdownMenuSeparator />
    <DropdownMenuGroup>
        <DropdownMenuItem :as-child="true">
            <Link class="block w-full" :href="edit.url()" prefetch as="button">
                <UserIcon class="mr-2 h-4 w-4" />
                Meu Perfil
            </Link>
        </DropdownMenuItem>
        <!-- <DropdownMenuItem :as-child="true">
            <Link class="block w-full" :href="edit.url()" prefetch as="button">
                <Settings class="mr-2 h-4 w-4" />
                Configurações
            </Link>
        </DropdownMenuItem> -->
    </DropdownMenuGroup>
    <DropdownMenuSeparator />
    <DropdownMenuItem @click="toggleTheme" class="cursor-pointer">
        <component :is="currentIcon" class="mr-2 h-4 w-4" />
        {{ currentLabel }}
    </DropdownMenuItem>
    <DropdownMenuSeparator />
    <DropdownMenuItem :as-child="true">
        <Link
            class="block w-full"
            :href="logout()"
            @click="handleLogout"
            as="button"
            data-test="logout-button"
        >
            <LogOut class="mr-2 h-4 w-4" />
            Sair
        </Link>
    </DropdownMenuItem>
</template>
