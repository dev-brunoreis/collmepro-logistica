<script setup lang="ts">
import NavFooter from '@/components/NavFooter.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import users from '@/routes/users';
import { type NavItem } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';
import { 
    BookOpen, 
    Folder, 
    LayoutGrid, 
    Users, 
    Package, 
    Kanban, 
    Tag, 
    FileText,
    BarChart3,
    SquareKanban
} from 'lucide-vue-next';
import AppLogo from './AppLogo.vue';

const page = usePage();
const user = page.props.auth.user;

const mainNavItems: NavItem[] = [
    {
        title: 'Painel',
        href: dashboard.url(),
        icon: LayoutGrid,
    },
    {
        title: 'Pedidos',
        href: '/dashboard/orders',
        icon: Package,
        visible: user?.permissions?.some(p => p.name === 'orders.view') || false,
    },
    {
        title: 'Usuários',
        href: users.index(),
        icon: Users,
        visible: user?.permissions?.some(p => p.name === 'users.view') || false,
    },
    {
        title: 'Permissões',
        href: '/dashboard/permissions',
        icon: FileText,
        visible: user?.permissions?.some(p => p.name === 'permissions.view') || false,
    },
    {
        title: 'Relatórios de Vendas',
        href: '/dashboard/reports/sales',
        icon: BarChart3,
        visible: user?.permissions?.some(p => p.name === 'reports.sales.view') || false,
    },
];

const footerNavItems: NavItem[] = [
    // {
    //     title: 'Github Repo',
    //     href: 'https://github.com/laravel/vue-starter-kit',
    //     icon: Folder,
    // },
    // {
    //     title: 'Documentation',
    //     href: 'https://laravel.com/docs/starter-kits#vue',
    //     icon: BookOpen,
    // },
];
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="dashboard.url()">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="mainNavItems" />
        </SidebarContent>

        <SidebarFooter>
            <NavFooter :items="footerNavItems" />
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
