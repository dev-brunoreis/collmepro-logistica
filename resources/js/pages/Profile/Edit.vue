<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import profile from '@/routes/profile';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/vue3';

interface User {
    id: number;
    name: string;
    email: string;
    username: string;
    role: string;
}

interface Props {
    user: User;
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Minha Conta',
        href: profile.edit.url(),
    },
];

const form = useForm({
    name: props.user.name,
    email: props.user.email,
    password: '',
    password_confirmation: '',
});

const submit = () => {
    form.patch(profile.update.url(), {
        onSuccess: () => {
            if (typeof window !== 'undefined' && (window as any).toast) {
                (window as any).toast.success('Perfil atualizado', 'Seu perfil foi atualizado com sucesso.');
            }
        },
        onError: (errors: any) => {
            if (typeof window !== 'undefined' && (window as any).toast) {
                const message = Object.values(errors).flat().join(', ');
                (window as any).toast.error('Erro ao atualizar perfil', message);
            }
        },
    });
};
</script>

<template>
    <Head title="Minha Conta" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
            <div class="max-w-2xl">
                <h1 class="text-2xl font-bold">Minha Conta</h1>
                <p class="text-muted-foreground">Atualize suas informações pessoais e senha.</p>
            </div>

            <form @submit.prevent="submit" class="max-w-2xl space-y-6">
                <div class="rounded-lg border border-sidebar-border/70 dark:border-sidebar-border p-6">
                    <h2 class="text-lg font-semibold mb-4">Informações Pessoais</h2>
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
                            <label for="email" class="block text-sm font-medium text-foreground">Email</label>
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
                            <label for="username" class="block text-sm font-medium text-foreground">Usuário</label>
                            <input
                                id="username"
                                :value="props.user.username"
                                type="text"
                                disabled
                                class="mt-1 block w-full rounded-md border border-sidebar-border/70 bg-muted px-3 py-2 text-sm text-muted-foreground dark:border-sidebar-border"
                            />
                            <p class="mt-1 text-sm text-muted-foreground">O usuário não pode ser alterado.</p>
                        </div>

                        <div>
                            <label for="role" class="block text-sm font-medium text-foreground">Função</label>
                            <input
                                id="role"
                                :value="props.user.role"
                                type="text"
                                disabled
                                class="mt-1 block w-full rounded-md border border-sidebar-border/70 bg-muted px-3 py-2 text-sm text-muted-foreground dark:border-sidebar-border"
                            />
                            <p class="mt-1 text-sm text-muted-foreground">A função não pode ser alterada.</p>
                        </div>
                    </div>
                </div>

                <div class="rounded-lg border border-sidebar-border/70 dark:border-sidebar-border p-6">
                    <h2 class="text-lg font-semibold mb-4">Alterar Senha</h2>
                    <div class="space-y-4">
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
                        <span v-else>Atualizar Perfil</span>
                    </button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
