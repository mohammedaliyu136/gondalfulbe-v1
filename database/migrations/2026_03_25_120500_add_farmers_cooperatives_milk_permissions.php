<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * @var array<int, string>
     */
    protected array $permissions = [
        'manage farmers directory',
        'create farmers directory',
        'edit farmers directory',
        'delete farmers directory',
        'export farmers directory',
        'manage cooperatives registry',
        'create cooperatives registry',
        'edit cooperatives registry',
        'delete cooperatives registry',
        'export cooperatives registry',
        'manage milk collection records',
        'create milk collection records',
        'edit milk collection records',
        'export milk collection records',
    ];

    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach ($this->permissions as $permissionName) {
            Permission::query()->firstOrCreate([
                'name' => $permissionName,
                'guard_name' => 'web',
            ]);
        }

        $defaultRoles = Role::query()->whereIn('name', ['super admin', 'company'])->get();
        $permissions = Permission::query()->whereIn('name', $this->permissions)->get();

        foreach ($defaultRoles as $role) {
            $role->givePermissionTo($permissions);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $roles = Role::query()->whereIn('name', ['super admin', 'company'])->get();
        $permissions = Permission::query()->whereIn('name', $this->permissions)->get();

        foreach ($roles as $role) {
            foreach ($permissions as $permission) {
                if ($role->hasPermissionTo($permission)) {
                    $role->revokePermissionTo($permission);
                }
            }
        }

        Permission::query()->whereIn('name', $this->permissions)->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
