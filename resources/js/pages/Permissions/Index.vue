<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import permissions from '@/routes/permissions';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import { Edit, Eye, Shield, Users, UserCheck } from 'lucide-vue-next';

interface Permission {
    id: number;
    name: string;
    display_name: string;
    description: string;
    category: string;
}

interface Role {
    display_name: string;
    description: string;
    permissions: Permission[];
}

interface Props {
    roles: {
        admin: Role;
        manager: Role;
        seller: Role;
    };
    allPermissions: Permission[];
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Permissões',
        href: permissions.index.url(),
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
</script>

<template>
    <Head title="Permissões" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
            <div class="flex items-center justify-between">
                <h1 class="text-2xl font-bold">Gerenciar Permissões</h1>
                <p class="text-sm text-muted-foreground">
                    Gerencie as permissões dos roles do sistema
                </p>
            </div>

            <div class="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
                <div
                    v-for="(roleData, role) in props.roles"
                    :key="role"
                    class="rounded-lg border border-sidebar-border/70 bg-card p-6 dark:border-sidebar-border"
                >
                    <div class="flex items-center gap-3 mb-4">
                        <component
                            :is="getRoleIcon(role)"
                            class="h-6 w-6"
                            :class="{
                                'text-red-600': role === 'admin',
                                'text-blue-600': role === 'manager',
                                'text-green-600': role === 'sales',
                            }"
                        />
                        <div>
                            <h3 class="text-lg font-semibold">{{ roleData.display_name }}</h3>
                            <p class="text-sm text-muted-foreground">{{ roleData.description }}</p>
                        </div>
                    </div>

                    <div class="mb-4">
                        <div class="flex items-center gap-2 mb-2">
                            <span class="text-sm font-medium">Permissões:</span>
                            <span
                                class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium"
                                :class="getRoleColor(role)"
                            >
                                {{ roleData.permissions.length }} permissões
                            </span>
                        </div>
                        <div class="space-y-1">
                            <div
                                v-for="permission in roleData.permissions.slice(0, 3)"
                                :key="permission.id"
                                class="text-sm text-muted-foreground"
                            >
                                • {{ permission.display_name }}
                            </div>
                            <div
                                v-if="roleData.permissions.length > 3"
                                class="text-sm text-muted-foreground"
                            >
                                • +{{ roleData.permissions.length - 3 }} mais...
                            </div>
                        </div>
                    </div>

                    <div class="flex gap-2">
                        <Link
                            :href="permissions.show.url({ role })"
                            class="inline-flex items-center gap-1 rounded-md px-3 py-2 text-sm font-medium text-muted-foreground hover:bg-sidebar-border/70"
                        >
                            <Eye class="h-4 w-4" />
                            Ver
                        </Link>
                        <Link
                            :href="permissions.edit.url({ role })"
                            class="inline-flex items-center gap-1 rounded-md px-3 py-2 text-sm font-medium text-primary hover:bg-primary/10"
                        >
                            <Edit class="h-4 w-4" />
                            Editar
                        </Link>
                    </div>
                </div>
            </div>

            <!-- Summary -->
            <div class="rounded-lg border border-sidebar-border/70 bg-card p-6 dark:border-sidebar-border">
                <h3 class="text-lg font-semibold mb-4">Resumo do Sistema</h3>
                <div class="grid gap-4 md:grid-cols-3">
                    <div class="text-center">
                        <div class="text-2xl font-bold text-primary">{{ props.allPermissions.length }}</div>
                        <div class="text-sm text-muted-foreground">Permissões Disponíveis</div>
                    </div>
                    <div class="text-center">
                        <div class="text-2xl font-bold text-primary">3</div>
                        <div class="text-sm text-muted-foreground">Roles do Sistema</div>
                    </div>
                    <div class="text-center">
                        <div class="text-2xl font-bold text-primary">
                            {{ Object.values(props.roles).reduce((total, role) => total + role.permissions.length, 0) }}
                        </div>
                        <div class="text-sm text-muted-foreground">Total de Atribuições</div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>