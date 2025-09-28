<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import permissions from '@/routes/permissions';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import { ArrowLeft, Edit, Shield, Users, UserCheck } from 'lucide-vue-next';

interface Permission {
    id: number;
    name: string;
    display_name: string;
    description: string;
    category: string;
}

interface Props {
    role: string;
    roleData: {
        display_name: string;
        description: string;
    };
    permissions: Permission[];
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Permissões',
        href: permissions.index.url(),
    },
    {
        title: props.roleData.display_name,
        href: permissions.show.url({ role: props.role }),
    },
];

const getRoleIcon = (role: string) => {
    switch (role) {
        case 'admin':
            return Shield;
        case 'manager':
            return UserCheck;
        case 'sales':
            return Users;
        default:
            return Users;
    }
};

const getRoleColor = (role: string) => {
    switch (role) {
        case 'admin':
            return 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300';
        case 'manager':
            return 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300';
        case 'sales':
            return 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300';
        default:
            return 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-300';
    }
};

// Group permissions by category
const groupedPermissions = computed(() => {
    const groups: { [key: string]: Permission[] } = {};
    props.permissions.forEach(permission => {
        if (!groups[permission.category]) {
            groups[permission.category] = [];
        }
        groups[permission.category].push(permission);
    });
    return groups;
});

const categoryLabels: { [key: string]: string } = {
    'orders': 'Pedidos',
    'users': 'Usuários',
    'permissions': 'Permissões',
    'dashboard': 'Dashboard',
    'kanban': 'Kanban',
    'labels': 'Etiquetas',
};
</script>

<template>
    <Head :title="`Permissões - ${props.roleData.display_name}`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <Link
                        :href="permissions.index.url()"
                        class="inline-flex items-center gap-2 rounded-lg border border-sidebar-border/70 bg-background px-3 py-2 text-sm font-medium hover:bg-sidebar-border/70 dark:border-sidebar-border"
                    >
                        <ArrowLeft class="h-4 w-4" />
                        Voltar
                    </Link>
                    <div class="flex items-center gap-3">
                        <component
                            :is="getRoleIcon(props.role)"
                            class="h-6 w-6"
                            :class="{
                                'text-red-600': props.role === 'admin',
                                'text-blue-600': props.role === 'manager',
                                'text-green-600': props.role === 'sales',
                            }"
                        />
                        <div>
                            <h1 class="text-2xl font-bold">{{ props.roleData.display_name }}</h1>
                            <p class="text-sm text-muted-foreground">{{ props.roleData.description }}</p>
                        </div>
                    </div>
                </div>
                <Link
                    :href="permissions.edit.url({ role: props.role })"
                    class="inline-flex items-center gap-2 rounded-lg bg-primary px-4 py-2 text-sm font-medium text-primary-foreground hover:bg-primary/90"
                >
                    <Edit class="h-4 w-4" />
                    Editar Permissões
                </Link>
            </div>

            <!-- Role Info -->
            <div class="rounded-lg border border-sidebar-border/70 bg-card p-6 dark:border-sidebar-border">
                <div class="flex items-center gap-3 mb-4">
                    <span
                        class="inline-flex items-center rounded-full px-3 py-1 text-sm font-medium"
                        :class="getRoleColor(props.role)"
                    >
                        {{ props.roleData.display_name }}
                    </span>
                    <span class="text-sm text-muted-foreground">
                        {{ props.permissions.length }} permissões
                    </span>
                </div>
                <p class="text-sm text-muted-foreground">{{ props.roleData.description }}</p>
            </div>

            <!-- Permissions by Category -->
            <div class="space-y-6">
                <div
                    v-for="(permissions, category) in groupedPermissions"
                    :key="category"
                    class="rounded-lg border border-sidebar-border/70 bg-card p-6 dark:border-sidebar-border"
                >
                    <h3 class="text-lg font-semibold mb-4">
                        {{ categoryLabels[category] || category }}
                        <span class="text-sm font-normal text-muted-foreground">
                            ({{ permissions.length }} permissões)
                        </span>
                    </h3>
                    <div class="space-y-3">
                        <div
                            v-for="permission in permissions"
                            :key="permission.id"
                            class="p-4 rounded-lg border border-sidebar-border/50 bg-sidebar-border/10"
                        >
                            <div class="font-medium">{{ permission.display_name }}</div>
                            <div class="text-sm text-muted-foreground mt-1">{{ permission.description }}</div>
                            <div class="text-xs text-muted-foreground mt-2 font-mono">{{ permission.name }}</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Summary -->
            <div class="rounded-lg border border-sidebar-border/70 bg-card p-6 dark:border-sidebar-border">
                <h3 class="text-lg font-semibold mb-4">Resumo das Permissões</h3>
                <div class="grid gap-4 md:grid-cols-3">
                    <div class="text-center">
                        <div class="text-2xl font-bold text-primary">{{ props.permissions.length }}</div>
                        <div class="text-sm text-muted-foreground">Total de Permissões</div>
                    </div>
                    <div class="text-center">
                        <div class="text-2xl font-bold text-primary">{{ Object.keys(groupedPermissions).length }}</div>
                        <div class="text-sm text-muted-foreground">Categorias</div>
                    </div>
                    <div class="text-center">
                        <div class="text-2xl font-bold text-primary">{{ props.role }}</div>
                        <div class="text-sm text-muted-foreground">Role</div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>