<?php

namespace App\Http\Controllers;

use App\Models\Permission;
use App\Models\RolePermission;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PermissionController extends Controller
{
    /**
     * Display a listing of roles and their permissions.
     */
    public function index(): Response
    {
        // Get all roles with their permissions
        $roles = [
            'admin' => [
                'display_name' => 'Administrador',
                'description' => 'Acesso completo ao sistema',
                'permissions' => Permission::whereHas('rolePermissions', function ($query) {
                    $query->where('role', 'admin');
                })->get(),
            ],
            'manager' => [
                'display_name' => 'Gerente',
                'description' => 'Acesso a pedidos e relatórios',
                'permissions' => Permission::whereHas('rolePermissions', function ($query) {
                    $query->where('role', 'manager');
                })->get(),
            ],
            'sales' => [
                'display_name' => 'Vendedor',
                'description' => 'Acesso básico ao sistema',
                'permissions' => Permission::whereHas('rolePermissions', function ($query) {
                    $query->where('role', 'sales');
                })->get(),
            ],
        ];

        // Get all available permissions
        $allPermissions = Permission::orderBy('category')->orderBy('display_name')->get();

        return Inertia::render('Permissions/Index', [
            'roles' => $roles,
            'allPermissions' => $allPermissions,
        ]);
    }

    /**
     * Show the form for editing a role's permissions.
     */
    public function edit(string $role): Response
    {
        if (!in_array($role, ['admin', 'manager', 'sales'])) {
            abort(404);
        }

        $roleData = [
            'admin' => [
                'display_name' => 'Administrador',
                'description' => 'Acesso completo ao sistema',
            ],
            'manager' => [
                'display_name' => 'Gerente',
                'description' => 'Acesso a pedidos e relatórios',
            ],
            'sales' => [
                'display_name' => 'Vendedor',
                'description' => 'Acesso básico ao sistema',
            ],
        ];

        $rolePermissions = Permission::whereHas('rolePermissions', function ($query) use ($role) {
            $query->where('role', $role);
        })->pluck('id')->toArray();

        $allPermissions = Permission::orderBy('category')->orderBy('display_name')->get();

        return Inertia::render('Permissions/Edit', [
            'role' => $role,
            'roleData' => $roleData[$role],
            'rolePermissions' => $rolePermissions,
            'allPermissions' => $allPermissions,
        ]);
    }

    /**
     * Update the specified role's permissions.
     */
    public function update(Request $request, string $role)
    {
        if (!in_array($role, ['admin', 'manager', 'sales'])) {
            abort(404);
        }

        $request->validate([
            'permissions' => 'array',
            'permissions.*' => 'exists:permissions,id',
        ]);

        // Check if admin is trying to remove their own admin permissions
        if ($role === 'admin' && auth()->user()->role === 'admin') {
            $adminUsers = User::where('role', 'admin')->count();
            if ($adminUsers === 1) {
                // This is the only admin, don't allow removing admin permissions
                $adminPermissions = Permission::whereHas('rolePermissions', function ($query) {
                    $query->where('role', 'admin');
                })->pluck('id')->toArray();

                $requestedPermissions = $request->input('permissions', []);
                
                // Check if any admin permissions are being removed
                $removedPermissions = array_diff($adminPermissions, $requestedPermissions);
                if (!empty($removedPermissions)) {
                    return back()->with('error', 'Você não pode remover suas próprias permissões de administrador sendo o único admin do sistema.');
                }
            }
        }

        // Remove all current permissions for this role
        RolePermission::where('role', $role)->delete();

        // Add new permissions
        if ($request->has('permissions')) {
            foreach ($request->input('permissions') as $permissionId) {
                RolePermission::create([
                    'role' => $role,
                    'permission_id' => $permissionId,
                ]);
            }
        }

        return redirect()->route('permissions.index')
            ->with('success', "Permissões do role {$role} atualizadas com sucesso.");
    }

    /**
     * Display the specified role's permissions.
     */
    public function show(string $role): Response
    {
        if (!in_array($role, ['admin', 'manager', 'sales'])) {
            abort(404);
        }

        $roleData = [
            'admin' => [
                'display_name' => 'Administrador',
                'description' => 'Acesso completo ao sistema',
            ],
            'manager' => [
                'display_name' => 'Gerente',
                'description' => 'Acesso a pedidos e relatórios',
            ],
            'sales' => [
                'display_name' => 'Vendedor',
                'description' => 'Acesso básico ao sistema',
            ],
        ];

        $permissions = Permission::whereHas('rolePermissions', function ($query) use ($role) {
            $query->where('role', $role);
        })->orderBy('category')->orderBy('display_name')->get();

        return Inertia::render('Permissions/Show', [
            'role' => $role,
            'roleData' => $roleData[$role],
            'permissions' => $permissions,
        ]);
    }
}