<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\RolePermission;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Clear existing data
        RolePermission::truncate();
        Permission::truncate();

        // Define permissions - simplified for 3 roles only
        $permissions = [
            // Orders permissions
            [
                'name' => 'orders.view',
                'display_name' => 'Visualizar Pedidos',
                'description' => 'Permite visualizar a lista de pedidos',
                'category' => 'orders',
                'roles' => ['admin', 'manager', 'sales'],
            ],
            [
                'name' => 'orders.status.update',
                'display_name' => 'Atualizar Status dos Pedidos',
                'description' => 'Permite alterar o status dos pedidos (picking, packing, picked_up)',
                'category' => 'orders',
                'roles' => ['admin', 'manager'],
            ],
            [
                'name' => 'orders.import',
                'display_name' => 'Importar Pedidos',
                'description' => 'Permite importar pedidos manualmente dos sistemas externos',
                'category' => 'orders',
                'roles' => ['admin', 'manager'],
            ],

            // Users permissions
            [
                'name' => 'users.view',
                'display_name' => 'Visualizar Usuários',
                'description' => 'Permite visualizar a lista de usuários',
                'category' => 'users',
                'roles' => ['admin'],
            ],
            [
                'name' => 'users.create',
                'display_name' => 'Criar Usuários',
                'description' => 'Permite criar novos usuários',
                'category' => 'users',
                'roles' => ['admin'],
            ],
            [
                'name' => 'users.edit',
                'display_name' => 'Editar Usuários',
                'description' => 'Permite editar informações dos usuários',
                'category' => 'users',
                'roles' => ['admin'],
            ],
            [
                'name' => 'users.delete',
                'display_name' => 'Excluir Usuários',
                'description' => 'Permite excluir usuários',
                'category' => 'users',
                'roles' => ['admin'],
            ],

            // Permissions management (only admin)
            [
                'name' => 'permissions.view',
                'display_name' => 'Visualizar Permissões',
                'description' => 'Permite visualizar e gerenciar permissões dos roles',
                'category' => 'permissions',
                'roles' => ['admin'],
            ],

            // Dashboard permissions
            [
                'name' => 'dashboard.view',
                'display_name' => 'Visualizar Dashboard',
                'description' => 'Permite acessar o dashboard principal',
                'category' => 'dashboard',
                'roles' => ['admin', 'manager', 'sales'],
            ],

            // Kanban permissions
            [
                'name' => 'kanban.view',
                'display_name' => 'Visualizar Kanban',
                'description' => 'Permite visualizar o kanban de pedidos',
                'category' => 'kanban',
                'roles' => ['admin', 'manager', 'sales'],
            ],

            // Labels permissions
            [
                'name' => 'labels.view',
                'display_name' => 'Visualizar Etiquetas',
                'description' => 'Permite visualizar etiquetas de envio',
                'category' => 'labels',
                'roles' => ['admin', 'manager', 'sales'],
            ],
        ];

        foreach ($permissions as $permissionData) {
            $roles = $permissionData['roles'];
            unset($permissionData['roles']);

            $permission = Permission::create($permissionData);

            // Assign roles to permission
            foreach ($roles as $role) {
                RolePermission::create([
                    'role' => $role,
                    'permission_id' => $permission->id,
                ]);
            }
        }
    }
}