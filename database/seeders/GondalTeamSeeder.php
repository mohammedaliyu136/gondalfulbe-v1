<?php

namespace Database\Seeders;

use App\Models\LoginDetail;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class GondalTeamSeeder extends Seeder
{
    public function run(): void
    {
        $company = User::query()
            ->where('type', 'company')
            ->orderBy('id')
            ->first();

        if (! $company) {
            return;
        }

        $profiles = [
            [
                'name' => 'Amina Yusuf',
                'email' => 'amina.yusuf@gondal.local',
                'type' => 'operations',
                'role' => 'Operations Lead',
                'activity' => ['browser_name' => 'Chrome', 'os_name' => 'Windows', 'city' => 'Kaduna', 'country' => 'Nigeria'],
                'minutes_ago' => 12,
            ],
            [
                'name' => 'Chinedu Okafor',
                'email' => 'chinedu.okafor@gondal.local',
                'type' => 'logistics',
                'role' => 'Logistics Coordinator',
                'activity' => ['browser_name' => 'Edge', 'os_name' => 'Windows', 'city' => 'Zaria', 'country' => 'Nigeria'],
                'minutes_ago' => 27,
            ],
            [
                'name' => 'Fatima Bello',
                'email' => 'fatima.bello@gondal.local',
                'type' => 'payments',
                'role' => 'Payments Officer',
                'activity' => ['browser_name' => 'Chrome', 'os_name' => 'macOS', 'city' => 'Abuja', 'country' => 'Nigeria'],
                'minutes_ago' => 41,
            ],
            [
                'name' => 'Tunde Adebayo',
                'email' => 'tunde.adebayo@gondal.local',
                'type' => 'extension',
                'role' => 'Field Extension Supervisor',
                'activity' => ['browser_name' => 'Firefox', 'os_name' => 'Windows', 'city' => 'Kano', 'country' => 'Nigeria'],
                'minutes_ago' => 68,
            ],
            [
                'name' => 'Hadiza Suleiman',
                'email' => 'hadiza.suleiman@gondal.local',
                'type' => 'inventory',
                'role' => 'Inventory Officer',
                'activity' => ['browser_name' => 'Chrome', 'os_name' => 'Android', 'city' => 'Rigasa', 'country' => 'Nigeria'],
                'minutes_ago' => 82,
            ],
            [
                'name' => 'Emeka Nwosu',
                'email' => 'emeka.nwosu@gondal.local',
                'type' => 'procurement',
                'role' => 'Procurement Analyst',
                'activity' => ['browser_name' => 'Safari', 'os_name' => 'iPadOS', 'city' => 'Kaduna', 'country' => 'Nigeria'],
                'minutes_ago' => 126,
            ],
        ];

        foreach ($profiles as $profile) {
            $role = Role::query()->firstOrCreate(
                ['name' => $profile['role']],
                ['created_by' => $company->id]
            );

            $user = User::query()->updateOrCreate(
                ['email' => $profile['email']],
                [
                    'name' => $profile['name'],
                    'password' => Hash::make('1234'),
                    'type' => $profile['type'],
                    'lang' => 'en',
                    'avatar' => '',
                    'created_by' => $company->id,
                    'email_verified_at' => now(),
                    'last_login_at' => now()->subMinutes($profile['minutes_ago']),
                ]
            );

            $user->syncRoles([$role->name]);

            $hasRecentActivity = LoginDetail::query()
                ->where('user_id', (string) $user->id)
                ->where('date', '>=', now()->subDay()->toDateTimeString())
                ->exists();

            if (! $hasRecentActivity) {
                LoginDetail::query()->create([
                    'user_id' => (string) $user->id,
                    'ip' => '102.89.34.'.random_int(10, 200),
                    'date' => now()->subMinutes($profile['minutes_ago'])->toDateTimeString(),
                    'Details' => json_encode($profile['activity']),
                    'created_by' => $company->id,
                ]);
            }
        }
    }
}
