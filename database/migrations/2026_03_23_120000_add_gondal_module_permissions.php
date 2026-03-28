<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class AddGondalModulePermissions extends Migration
{
    /**
     * @var array<int, string>
     */
    protected array $permissions = [
        'manage logistics',
        'create logistics',
        'import logistics',
        'export logistics',
        'manage operations',
        'create operations',
        'import operations',
        'export operations',
        'manage requisitions',
        'create requisitions',
        'edit requisitions',
        'show requisitions',
        'import requisitions',
        'export requisitions',
        'manage payments',
        'create payments',
        'import payments',
        'export payments',
        'manage inventory',
        'create inventory',
        'import inventory',
        'export inventory',
        'manage extension',
        'create extension',
        'import extension',
        'export extension',
        'manage reports',
        'import reports',
        'export reports',
    ];

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach ($this->permissions as $permissionName) {
            Permission::firstOrCreate([
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

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
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
}
