<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
// import users from '@/routes/users'; // Removed as file doesn't exist
import { type BreadcrumbItem } from '@/types';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft } from 'lucide-vue-next';

interface User {
    id: number;
    name: string;
    email: string;
    username: string;
    role: string;
}

interface Role {
    [key: string]: string;
}

interface Props {
    user: User;
    roles: Role;
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Usuários',
        href: '/dashboard/users',
    },
    {
        title: 'Editar Usuário',
        href: `/dashboard/users/${props.user.id}/edit`,
    },
];

const form = useForm({
    name: props.user.name,
    email: props.user.email,
    username: props.user.username,
    password: '',
    password_confirmation: '',
    role: props.user.role,
});

const submit = () => {
    form.patch(`/dashboard/users/${props.user.id}`, {
        onSuccess: () => {
            if (typeof window !== 'undefined' && (window as any).toast) {
                (window as any).toast.success('Usuário atualizado', 'O usuário foi atualizado com sucesso.');
            }
        },
        onError: (errors: any) => {
            if (typeof window !== 'undefined' && (window as any).toast) {
                const message = Object.values(errors).flat().join(', ');
                (window as any).toast.error('Erro ao atualizar usuário', message);
            }
        },
    });
};
</script>

<template>
    <Head title="Editar Usuário" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
            <div class="flex items-center gap-4">
                <Link
                    href="/dashboard/users"
                    class="inline-flex items-center gap-2 text-sm text-muted-foreground hover:text-foreground"
                >
                    <ArrowLeft class="h-4 w-4" />
                    Voltar para Usuários
                </Link>
            </div>

            <div class="max-w-2xl">
                <h1 class="text-2xl font-bold">Editar Usuário</h1>
                <p class="text-muted-foreground">Atualizar informações do usuário.</p>
            </div>

            <form @submit.prevent="submit" class="max-w-2xl space-y-6">
                <div class="rounded-lg border border-sidebar-border/70 dark:border-sidebar-border p-6">
                    <div class="space-y-4">
                        <div>
                            <label for="name" class="block text-sm font-medium text-foreground">Nome</label>
                            <input
                                id="name"
                                v-model="form.name"
                                type="text"
                                required
                                class="mt-1 block w-full rounded-md border border-sidebar-border/70 bg-background px-3 py-2 text-sm placeholder:text-muted-foreground focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary dark:border-sidebar-border"
                                :class="{ 'border-red-500': form.errors.name }"
                            />
                            <p v-if="form.errors.name" class="mt-1 text-sm text-red-600">{{ form.errors.name }}</p>
                        </div>

                        <div>
                            <label for="email" class="block text-sm font-medium text-foreground">E-mail</label>
                            <input
                                id="email"
                                v-model="form.email"
                                type="email"
                                required
                                class="mt-1 block w-full rounded-md border border-sidebar-border/70 bg-background px-3 py-2 text-sm placeholder:text-muted-foreground focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary dark:border-sidebar-border"
                                :class="{ 'border-red-500': form.errors.email }"
                            />
                            <p v-if="form.errors.email" class="mt-1 text-sm text-red-600">{{ form.errors.email }}</p>
                        </div>

                        <div>
                            <label for="username" class="block text-sm font-medium text-foreground">Nome de Usuário</label>
                            <input
                                id="username"
                                v-model="form.username"
                                type="text"
                                required
                                class="mt-1 block w-full rounded-md border border-sidebar-border/70 bg-background px-3 py-2 text-sm placeholder:text-muted-foreground focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary dark:border-sidebar-border"
                                :class="{ 'border-red-500': form.errors.username }"
                            />
                            <p v-if="form.errors.username" class="mt-1 text-sm text-red-600">{{ form.errors.username }}</p>
                        </div>

                        <div>
                            <label for="role" class="block text-sm font-medium text-foreground">Função</label>
                            <select
                                id="role"
                                v-model="form.role"
                                required
                                :disabled="props.user.id === $page.props.auth.user.id && $page.props.auth.user.role === 'admin'"
                                class="mt-1 block w-full rounded-md border border-sidebar-border/70 bg-background px-3 py-2 text-sm focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary dark:border-sidebar-border disabled:opacity-50 disabled:cursor-not-allowed"
                                :class="{ 'border-red-500': form.errors.role }"
                            >
                                <option v-for="(label, value) in props.roles" :key="value" :value="value" :disabled="value === 'developer'">
                                    {{ label }}
                                </option>
                            </select>
                            <p v-if="form.errors.role" class="mt-1 text-sm text-red-600">{{ form.errors.role }}</p>
                            <p v-if="props.user.id === $page.props.auth.user.id && $page.props.auth.user.role === 'admin'" class="mt-1 text-sm text-muted-foreground">
                                Você não pode alterar sua própria função.
                            </p>
                        </div>

                        <div>
                            <label for="password" class="block text-sm font-medium text-foreground">Nova Senha</label>
                            <input
                                id="password"
                                v-model="form.password"
                                type="password"
                                class="mt-1 block w-full rounded-md border border-sidebar-border/70 bg-background px-3 py-2 text-sm placeholder:text-muted-foreground focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary dark:border-sidebar-border"
                                :class="{ 'border-red-500': form.errors.password }"
                                placeholder="Deixe em branco para manter a senha atual"
                            />
                            <p v-if="form.errors.password" class="mt-1 text-sm text-red-600">{{ form.errors.password }}</p>
                        </div>

                        <div v-if="form.password">
                            <label for="password_confirmation" class="block text-sm font-medium text-foreground">Confirmar Nova Senha</label>
                            <input
                                id="password_confirmation"
                                v-model="form.password_confirmation"
                                type="password"
                                class="mt-1 block w-full rounded-md border border-sidebar-border/70 bg-background px-3 py-2 text-sm placeholder:text-muted-foreground focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary dark:border-sidebar-border"
                                :class="{ 'border-red-500': form.errors.password_confirmation }"
                            />
                            <p v-if="form.errors.password_confirmation" class="mt-1 text-sm text-red-600">{{ form.errors.password_confirmation }}</p>
                        </div>
                    </div>
                </div>

                <div class="flex items-center gap-4">
                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="inline-flex items-center gap-2 rounded-lg bg-primary px-4 py-2 text-sm font-medium text-primary-foreground hover:bg-primary/90 disabled:opacity-50"
                    >
                        <span v-if="form.processing">Atualizando...</span>
                        <span v-else>Atualizar Usuário</span>
                    </button>
                    <Link
                        href="/dashboard/users"
                        class="inline-flex items-center gap-2 rounded-lg border border-sidebar-border/70 px-4 py-2 text-sm font-medium text-foreground hover:bg-sidebar-border/70 dark:border-sidebar-border"
                    >
                        Cancelar
                    </Link>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
