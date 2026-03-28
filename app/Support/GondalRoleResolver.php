<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Str;

class GondalRoleResolver
{
    protected const ROLE_DASHBOARDS = [
        'accountant' => 'accountant',
        'operations_lead' => 'operations_lead',
        'logistics_coordinator' => 'logistics_coordinator',
        'payments_officer' => 'payments_officer',
        'field_extension_supervisor' => 'field_extension_supervisor',
        'inventory_officer' => 'inventory_officer',
        'procurement_analyst' => 'procurement_analyst',
    ];

    public function resolve(?User $user): string
    {
        if (! $user) {
            return 'guest';
        }

        $roles = method_exists($user, 'getRoleNames')
            ? collect($user->getRoleNames())->map(fn ($role) => Str::lower((string) $role))->all()
            : [];

        if (in_array('system_admin', $roles, true) || in_array((string) $user->type, ['company', 'super admin'], true)) {
            return 'system_admin';
        }

        if (in_array('executive_director', $roles, true)) {
            return 'executive_director';
        }

        if (in_array('finance_officer', $roles, true) || in_array('accountant', $roles, true)) {
            return 'finance_officer';
        }

        if (in_array('center_manager', $roles, true) || in_array('manager', $roles, true)) {
            return 'center_manager';
        }

        return 'field_officer';
    }

    public function label(?User $user): string
    {
        return match ($this->resolve($user)) {
            'system_admin' => 'System Admin',
            'executive_director' => 'Executive Director',
            'finance_officer' => 'Finance Officer',
            'center_manager' => 'Center Manager',
            'field_officer' => 'Field Officer',
            default => 'Guest',
        };
    }

    public function isAdmin(?User $user): bool
    {
        return $this->resolve($user) === 'system_admin';
    }

    public function dashboardKey(?User $user): string
    {
        if (! $user) {
            return 'standard';
        }

        $roles = method_exists($user, 'getRoleNames')
            ? collect($user->getRoleNames())
                ->map(fn ($role) => Str::of((string) $role)->lower()->replace([' ', '-'], '_')->value())
                ->all()
            : [];

        foreach (self::ROLE_DASHBOARDS as $role => $dashboard) {
            if (in_array($role, $roles, true)) {
                return $dashboard;
            }
        }

        return 'standard';
    }

    public function dashboardLabel(string $dashboardKey): string
    {
        return match ($dashboardKey) {
            'accountant' => 'Accountant Dashboard',
            'operations_lead' => 'Operations Lead Dashboard',
            'logistics_coordinator' => 'Logistics Coordinator Dashboard',
            'payments_officer' => 'Payments Officer Dashboard',
            'field_extension_supervisor' => 'Field Extension Supervisor Dashboard',
            'inventory_officer' => 'Inventory Officer Dashboard',
            'procurement_analyst' => 'Procurement Analyst Dashboard',
            default => 'Standard Dashboard',
        };
    }

    public function roleDashboardKeys(): array
    {
        return array_values(array_unique(array_values(self::ROLE_DASHBOARDS)));
    }
}
