<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class MccPermissionSeeder extends Seeder
{
    public function run()
    {
        $permissions = [
            'manage mcc',
            'create mcc',
            'edit mcc',
            'delete mcc',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(
                ['name' => $permission, 'guard_name' => 'web']
            );
        }

        $companyRole = Role::where('name', 'company')->first();
        if ($companyRole) {
            $companyRole->givePermissionTo($permissions);
        }
    }
}
