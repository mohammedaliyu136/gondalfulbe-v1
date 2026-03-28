<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissionNames = [
            'manage warehouse-ops',
            'manage gondal warehouse registry',
            'create gondal warehouse registry',
            'edit gondal warehouse registry',
            'export gondal warehouse registry',
            'manage gondal warehouse stock',
            'create gondal warehouse stock',
            'edit gondal warehouse stock',
            'export gondal warehouse stock',
            'manage gondal warehouse dispatches',
            'create gondal warehouse dispatches',
            'export gondal warehouse dispatches',
        ];

        foreach ($permissionNames as $permissionName) {
            Permission::query()->firstOrCreate([
                'name' => $permissionName,
                'guard_name' => 'web',
            ]);
        }

        $roles = Role::query()->whereIn('name', ['super admin', 'company'])->get();
        $permissions = Permission::query()->whereIn('name', $permissionNames)->get();

        foreach ($roles as $role) {
            $role->givePermissionTo($permissions);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissionNames = [
            'manage warehouse-ops',
            'manage gondal warehouse registry',
            'create gondal warehouse registry',
            'edit gondal warehouse registry',
            'export gondal warehouse registry',
            'manage gondal warehouse stock',
            'create gondal warehouse stock',
            'edit gondal warehouse stock',
            'export gondal warehouse stock',
            'manage gondal warehouse dispatches',
            'create gondal warehouse dispatches',
            'export gondal warehouse dispatches',
        ];

        $roles = Role::query()->whereIn('name', ['super admin', 'company'])->get();
        $permissions = Permission::query()->whereIn('name', $permissionNames)->get();

        foreach ($roles as $role) {
            foreach ($permissions as $permission) {
                if ($role->hasPermissionTo($permission)) {
                    $role->revokePermissionTo($permission);
                }
            }
        }

        Permission::query()->whereIn('name', $permissionNames)->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
