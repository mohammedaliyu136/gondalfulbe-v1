<?php

use App\Support\GondalPermissionRegistry;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissions = GondalPermissionRegistry::granularPermissions();

        foreach ($permissions as $permissionName) {
            Permission::query()->firstOrCreate([
                'name' => $permissionName,
                'guard_name' => 'web',
            ]);
        }

        $roles = Role::query()->whereIn('name', ['super admin', 'company'])->get();
        $permissionModels = Permission::query()->whereIn('name', $permissions)->get();

        foreach ($roles as $role) {
            $role->givePermissionTo($permissionModels);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissionNames = [
            'manage inventory agents',
            'create inventory agents',
            'edit inventory agents',
            'export inventory agents',
            'manage inventory stock issues',
            'create inventory stock issues',
            'export inventory stock issues',
            'manage inventory remittances',
            'create inventory remittances',
            'export inventory remittances',
            'manage inventory reconciliation',
            'create inventory reconciliation',
            'edit inventory reconciliation',
            'export inventory reconciliation',
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
