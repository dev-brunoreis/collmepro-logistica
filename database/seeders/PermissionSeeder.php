<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\RolePermission;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Clear existing data
        $driver = DB::getDriverName();
        
        // Disable foreign key checks for MySQL/MariaDB
        if (in_array($driver, ['mysql', 'mariadb'])) {
            DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        }
        
        RolePermission::truncate();
        Permission::truncate();
        
        // Re-enable foreign key checks for MySQL/MariaDB
        if (in_array($driver, ['mysql', 'mariadb'])) {
            DB::statement('SET FOREIGN_KEY_CHECKS=1;');
        }

        // Define permissions - developer has same permissions as admin
        $permissions = [
            // Orders permissions
            [
                'name' => 'orders.view',
                'display_name' => 'Visualizar Pedidos',
                'description' => 'Permite visualizar a lista de pedidos',
                'category' => 'orders',
                'roles' => ['admin', 'developer', 'manager', 'sales'],
            ],
            [
                'name' => 'orders.status.update',
                'display_name' => 'Atualizar Status dos Pedidos',
                'description' => 'Permite alterar o status dos pedidos (separado, embalado, coletado)',
                'category' => 'orders',
                'roles' => ['admin', 'developer', 'manager'],
            ],
            [
                'name' => 'orders.import',
                'display_name' => 'Importar Pedidos',
                'description' => 'Permite importar pedidos manualmente dos sistemas externos',
                'category' => 'orders',
                'roles' => ['admin', 'developer', 'manager'],
            ],

            // Users permissions
            [
                'name' => 'users.view',
                'display_name' => 'Visualizar Usuários',
                'description' => 'Permite visualizar a lista de usuários',
                'category' => 'users',
                'roles' => ['admin', 'developer'],
            ],
            [
                'name' => 'users.create',
                'display_name' => 'Criar Usuários',
                'description' => 'Permite criar novos usuários',
                'category' => 'users',
                'roles' => ['admin', 'developer'],
            ],
            [
                'name' => 'users.edit',
                'display_name' => 'Editar Usuários',
                'description' => 'Permite editar informações dos usuários',
                'category' => 'users',
                'roles' => ['admin', 'developer'],
            ],
            [
                'name' => 'users.delete',
                'display_name' => 'Excluir Usuários',
                'description' => 'Permite excluir usuários',
                'category' => 'users',
                'roles' => ['admin', 'developer'],
            ],

            // Permissions management (only admin)
            [
                'name' => 'permissions.view',
                'display_name' => 'Visualizar Permissões',
                'description' => 'Permite visualizar e gerenciar permissões dos roles',
                'category' => 'permissions',
                'roles' => ['admin', 'developer'],
            ],

            // Dashboard permissions
            [
                'name' => 'dashboard.view',
                'display_name' => 'Visualizar Dashboard',
                'description' => 'Permite acessar o dashboard principal',
                'category' => 'dashboard',
                'roles' => ['admin', 'developer', 'manager', 'sales'],
            ],

            // Kanban permissions
            [
                'name' => 'kanban.view',
                'display_name' => 'Visualizar Kanban',
                'description' => 'Permite visualizar o kanban de pedidos',
                'category' => 'kanban',
                'roles' => ['admin', 'developer', 'manager', 'sales'],
            ],

            // Stock permissions
            [
                'name' => 'stock.view',
                'display_name' => 'Visualizar Estoque',
                'description' => 'Permite visualizar o controle de estoque por produto e período',
                'category' => 'stock',
                'roles' => ['admin', 'developer'],
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