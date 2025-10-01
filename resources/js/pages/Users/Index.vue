<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import users from '@/routes/users';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import { Trash2, Edit, Plus, Search, Filter } from 'lucide-vue-next';

interface User {
    id: number;
    name: string;
    email: string;
    username: string;
    role: string;
    role_display_name: string;
    created_at: string;
}

interface Props {
    users: {
        data: User[];
        links: any[];
        from?: number;
        to?: number;
        total?: number;
        current_page?: number;
        last_page?: number;
        per_page?: number;
    };
    filters: {
        search?: string;
        role?: string;
    };
    roles: {
        [key: string]: string;
    };
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Usuários',
        href: users.index.url(),
    },
];

const deleteUser = (user: User) => {
    if (confirm(`Tem certeza que deseja excluir ${user.name}?`)) {
        // Store current scroll position
        const scrollPosition = window.pageYOffset || document.documentElement.scrollTop;
        
        router.delete(users.destroy.url({ user: user.id }), {
            preserveState: true,
            preserveScroll: true,
            onSuccess: (page: any) => {
                // Restore scroll position after a short delay to ensure DOM is updated
                setTimeout(() => {
                    window.scrollTo(0, scrollPosition);
                }, 50);
                
                if (typeof window !== 'undefined' && (window as any).toast) {
                    // Check if there's an error message in the session
                    if (page.props.flash?.error) {
                        (window as any).toast.error('Erro', page.props.flash.error);
                    } else {
                        (window as any).toast.success('Usuário excluído', 'O usuário foi removido com sucesso.');
                    }
                }
            },
            onError: (errors: any) => {
                if (typeof window !== 'undefined' && (window as any).toast) {
                    const message = errors.message || 'Erro ao excluir usuário';
                    (window as any).toast.error('Erro', message);
                }
            },
        });
    }
};

const search = (value: string) => {
    const scrollPosition = window.pageYOffset || document.documentElement.scrollTop;
    
    router.get('/users', { search: value }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        onSuccess: () => {
            setTimeout(() => {
                window.scrollTo(0, scrollPosition);
            }, 50);
        },
    });
};

const filterByRole = (role: string) => {
    const scrollPosition = window.pageYOffset || document.documentElement.scrollTop;
    
    router.get('/users', { role: role || null }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        onSuccess: () => {
            setTimeout(() => {
                window.scrollTo(0, scrollPosition);
            }, 50);
        },
    });
};

const clearFilters = () => {
    const scrollPosition = window.pageYOffset || document.documentElement.scrollTop;
    
    router.get('/users', {}, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        onSuccess: () => {
            setTimeout(() => {
                window.scrollTo(0, scrollPosition);
            }, 50);
        },
    });
};
</script>

<template>
    <Head title="Usuários" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
            <div class="flex items-center justify-between">
                <h1 class="text-2xl font-bold">Usuários</h1>
                <Link
                    :href="users.create.url()"
                    class="inline-flex items-center gap-2 rounded-lg bg-primary px-4 py-2 text-sm font-medium text-primary-foreground hover:bg-primary/90"
                >
                    <Plus class="h-4 w-4" />
                    Adicionar Usuário
                </Link>
            </div>

            <!-- Filters -->
            <div class="flex flex-col gap-4 rounded-lg border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                <div class="flex items-center gap-4">
                    <div class="flex-1">
                        <div class="relative">
                            <Search class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                            <input
                                type="text"
                                :value="props.filters?.search || ''"
                                @input="search(($event.target as HTMLInputElement).value)"
                                placeholder="Buscar por nome, email ou usuário..."
                                class="w-full rounded-lg border border-sidebar-border/70 bg-background pl-10 pr-4 py-2 text-sm placeholder:text-muted-foreground focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary dark:border-sidebar-border"
                            />
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <Filter class="h-4 w-4 text-muted-foreground" />
                        <select
                            :value="props.filters?.role || ''"
                            @change="filterByRole(($event.target as HTMLSelectElement).value)"
                            class="rounded-lg border border-sidebar-border/70 bg-background px-3 py-2 text-sm focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary dark:border-sidebar-border"
                        >
                            <option value="">Todas as funções</option>
                            <option v-for="(label, value) in props.roles || {}" :key="value" :value="value">
                                {{ label }}
                            </option>
                        </select>
                    </div>
                    <button
                        v-if="props.filters?.search || props.filters?.role"
                        @click="clearFilters"
                        class="inline-flex items-center gap-2 rounded-lg border border-sidebar-border/70 px-3 py-2 text-sm font-medium text-foreground hover:bg-sidebar-border/70 dark:border-sidebar-border"
                    >
                        Limpar Filtros
                    </button>
                </div>
                <div v-if="props.filters?.search || props.filters?.role" class="text-sm text-muted-foreground">
                    <span v-if="props.filters?.search">Busca: "{{ props.filters.search }}"</span>
                    <span v-if="props.filters?.search && props.filters?.role"> • </span>
                    <span v-if="props.filters?.role">Função: {{ props.roles?.[props.filters.role] }}</span>
                </div>
            </div>

            <div class="rounded-lg border border-sidebar-border/70 dark:border-sidebar-border">
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="border-b border-sidebar-border/70 dark:border-sidebar-border">
                            <tr>
                            <th class="px-4 py-3 text-left text-sm font-medium text-muted-foreground">Nome</th>
                            <th class="px-4 py-3 text-left text-sm font-medium text-muted-foreground">E-mail</th>
                            <th class="px-4 py-3 text-left text-sm font-medium text-muted-foreground">Nome de Usuário</th>
                            <th class="px-4 py-3 text-left text-sm font-medium text-muted-foreground">Função</th>
                            <th class="px-4 py-3 text-left text-sm font-medium text-muted-foreground">Criado em</th>
                            <th class="px-4 py-3 text-left text-sm font-medium text-muted-foreground">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="user in props.users.data || []"
                                :key="user.id"
                                class="border-b border-sidebar-border/70 dark:border-sidebar-border last:border-b-0 hover:bg-sidebar-border/20"
                            >
                                <td class="px-4 py-3">
                                    <div class="font-medium">{{ user.name }}</div>
                                </td>
                                <td class="px-4 py-3 text-sm text-muted-foreground">{{ user.email }}</td>
                                <td class="px-4 py-3 text-sm text-muted-foreground">{{ user.username }}</td>
                                <td class="px-4 py-3">
                                    <span
                                        class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium"
                                        :class="{
                                            'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300': user.role === 'admin',
                                            'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300': user.role === 'manager',
                                            'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300': user.role === 'sales',
                                            'bg-purple-100 text-white dark:bg-purple-900 dark:text-purple-300 animate-pulse duration-300': user.role === 'developer',
                                        }"
                                    >
                                        {{ user.role_display_name }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-sm text-muted-foreground">
                                    {{ new Date(user.created_at).toLocaleDateString('pt-BR') }}
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-2">
                                        <Link
                                            :href="users.edit.url({ user: user.id })"
                                            class="inline-flex items-center gap-1 rounded-md px-2 py-1 text-sm text-muted-foreground hover:bg-sidebar-border/70"
                                        >
                                            <Edit class="h-3 w-3" />
                                            Editar
                                        </Link>
                                        <button
                                            @click="deleteUser(user)"
                                            class="inline-flex items-center gap-1 rounded-md px-2 py-1 text-sm text-red-600 hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-900/20"
                                        >
                                            <Trash2 class="h-3 w-3" />
                                            Excluir
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Pagination -->
            <div v-if="props.users.links?.length > 3" class="flex items-center justify-between">
                <div class="text-sm text-muted-foreground">
                    Mostrando {{ props.users.from || 0 }} a {{ props.users.to || 0 }} de {{ props.users.total || 0 }} resultados
                </div>
                <div class="flex items-center gap-2">
                    <Link
                        v-for="link in props.users.links || []"
                        :key="link.label"
                        :href="link.url || '#'"
                        v-html="link.label"
                        class="inline-flex items-center justify-center rounded-md px-3 py-2 text-sm font-medium"
                        :class="{
                            'bg-primary text-primary-foreground': link.active,
                            'text-muted-foreground hover:bg-sidebar-border/70': !link.active && link.url,
                            'text-muted-foreground/50 cursor-not-allowed': !link.url,
                        }"
                    />
                </div>
            </div>
        </div>
    </AppLayout>
</template>
