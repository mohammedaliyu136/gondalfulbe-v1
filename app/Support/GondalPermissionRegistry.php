<?php

namespace App\Support;

use App\Models\User;

class GondalPermissionRegistry
{
    public static function modules(): array
    {
        return [
            'farmers' => [
                'label' => 'Farmers',
                'sections' => [
                    'directory' => self::section('directory', 'Directory', 'farmers directory', ['manage', 'create', 'edit', 'delete', 'export']),
                ],
                'page_tabs' => [
                    self::tab('directory', 'Directory', 'directory'),
                ],
            ],
            'cooperatives' => [
                'label' => 'Cooperatives',
                'sections' => [
                    'registry' => self::section('registry', 'Registry', 'cooperatives registry', ['manage', 'create', 'edit', 'delete', 'export']),
                ],
                'page_tabs' => [
                    self::tab('registry', 'Registry', 'registry'),
                ],
            ],
            'milk-collection' => [
                'label' => 'Milk Collection',
                'sections' => [
                    'records' => self::section('records', 'Records', 'milk collection records', ['manage', 'create', 'edit', 'export']),
                ],
                'page_tabs' => [
                    self::tab('records', 'Records', 'records'),
                ],
            ],
            'logistics' => [
                'label' => 'Logistics',
                'sections' => [
                    'trips' => self::section('trips', 'Trips', 'logistics trips', ['manage', 'create', 'import', 'export']),
                    'riders' => self::section('riders', 'Riders', 'logistics riders', ['manage', 'create', 'import', 'export']),
                ],
                'page_tabs' => [
                    self::tab('trips', 'Trips', 'trips'),
                    self::tab('riders', 'Riders', 'riders'),
                ],
            ],
            'operations' => [
                'label' => 'Operations',
                'sections' => [
                    'costs' => self::section('costs', 'Costs', 'operations costs', ['manage', 'create', 'import', 'export']),
                    'summary' => self::section('summary', 'Weekly Summary', 'operations summary', ['manage', 'create', 'import', 'export']),
                    'ranking' => self::section('ranking', 'Center Ranking', 'operations ranking', ['manage', 'create', 'import', 'export']),
                ],
                'page_tabs' => [
                    self::tab('costs', 'Costs', 'costs'),
                    self::tab('summary', 'Weekly Summary', 'summary'),
                    self::tab('ranking', 'Center Ranking', 'ranking'),
                ],
            ],
            'requisitions' => [
                'label' => 'Requisitions',
                'sections' => [
                    'requests' => self::section('requests', 'Requests', 'requisitions requests', ['manage', 'create', 'import', 'export']),
                    'approvals' => self::section('approvals', 'Approvals', 'requisitions approvals', ['manage', 'edit']),
                    'details' => self::section('details', 'Details', 'requisitions details', ['manage', 'show']),
                ],
                'page_tabs' => [
                    self::tab('all', 'All', null),
                    self::tab('pending', 'Pending', null),
                    self::tab('approved', 'Approved', null),
                    self::tab('rejected', 'Rejected', null),
                ],
            ],
            'payments' => [
                'label' => 'Payments',
                'sections' => [
                    'overview' => self::section('overview', 'Overview', 'payments overview', ['manage', 'create', 'import', 'export']),
                    'batches' => self::section('batches', 'Batches', 'payments batches', ['manage', 'create', 'import', 'export']),
                    'reconciliation' => self::section('reconciliation', 'Reconciliation', 'payments reconciliation', ['manage', 'create', 'import', 'export']),
                ],
                'page_tabs' => [
                    self::tab('overview', 'Overview', 'overview'),
                    self::tab('batches', 'Batches', 'batches'),
                    self::tab('reconciliation', 'Reconciliation', 'reconciliation'),
                ],
            ],
            'inventory' => [
                'label' => 'Inventory',
                'sections' => [
                    'sales' => self::section('sales', 'Sales', 'inventory sales', ['manage', 'create', 'import', 'export']),
                    'credit' => self::section('credit', 'Credits', 'inventory credits', ['manage', 'create', 'import', 'export']),
                    'stock' => self::section('stock', 'Stock', 'inventory stock', ['manage', 'create', 'import', 'export']),
                    'agents' => self::section('agents', 'Agents', 'inventory agents', ['manage', 'create', 'edit', 'export']),
                    'issues' => self::section('issues', 'Stock Issues', 'inventory stock issues', ['manage', 'create', 'export']),
                    'remittances' => self::section('remittances', 'Remittances', 'inventory remittances', ['manage', 'create', 'export']),
                    'reconciliation' => self::section('reconciliation', 'Reconciliation', 'inventory reconciliation', ['manage', 'create', 'edit', 'export']),
                ],
                'page_tabs' => [
                    self::tab('sales', 'Sales', 'sales'),
                    self::tab('credit', 'Credits', 'credit'),
                    self::tab('stock', 'Stock', 'stock'),
                    self::tab('agents', 'Agents', 'agents'),
                    self::tab('issues', 'Stock Issues', 'issues'),
                    self::tab('remittances', 'Remittances', 'remittances'),
                    self::tab('reconciliation', 'Reconciliation', 'reconciliation'),
                ],
            ],
            'warehouse-ops' => [
                'label' => 'Warehouse',
                'sections' => [
                    'registry' => self::section('registry', 'Warehouses', 'gondal warehouse registry', ['manage', 'create', 'edit', 'export']),
                    'stock' => self::section('stock', 'Warehouse Stock', 'gondal warehouse stock', ['manage', 'create', 'edit', 'export']),
                    'dispatches' => self::section('dispatches', 'Dispatches', 'gondal warehouse dispatches', ['manage', 'create', 'export']),
                ],
                'page_tabs' => [
                    self::tab('registry', 'Warehouses', 'registry'),
                    self::tab('stock', 'Warehouse Stock', 'stock'),
                    self::tab('outside', 'Stock Outside Warehouse', 'dispatches'),
                    self::tab('dispatches', 'Dispatches', 'dispatches'),
                ],
            ],
            'extension' => [
                'label' => 'Extension',
                'sections' => [
                    'agents' => self::section('agents', 'Agents', 'extension agents', ['manage', 'create', 'import', 'export']),
                    'visits' => self::section('visits', 'Visits', 'extension visits', ['manage', 'create', 'import', 'export']),
                    'training' => self::section('training', 'Training', 'extension training', ['manage', 'create', 'import', 'export']),
                    'performance' => self::section('performance', 'Performance', 'extension performance', ['manage', 'create', 'import', 'export']),
                ],
                'page_tabs' => [
                    self::tab('agents', 'Agents', 'agents'),
                    self::tab('visits', 'Visits', 'visits'),
                    self::tab('training', 'Training', 'training'),
                    self::tab('performance', 'Performance', 'performance'),
                ],
            ],
            'reports' => [
                'label' => 'Reports',
                'sections' => [
                    'overview' => self::section('overview', 'Overview', 'reports overview', ['manage', 'import', 'export']),
                ],
                'page_tabs' => [],
            ],
        ];
    }

    public static function roleTabs(): array
    {
        $tabs = [];

        foreach (self::modules() as $module => $config) {
            $tabs[] = [
                'id' => $module,
                'module' => $module,
                'label' => $config['label'],
                'rows' => array_values($config['sections']),
            ];
        }

        return $tabs;
    }

    public static function sections(string $module): array
    {
        return self::modules()[$module]['sections'] ?? [];
    }

    public static function pageTabs(string $module): array
    {
        return self::modules()[$module]['page_tabs'] ?? [];
    }

    public static function visiblePageTabs(?User $user, string $module): array
    {
        $visibleTabs = [];

        foreach (self::pageTabs($module) as $tab) {
            if ($tab['section'] === null) {
                if (self::canAccessModule($user, $module)) {
                    $visibleTabs[] = $tab;
                }

                continue;
            }

            if (self::can($user, $module, $tab['section'], 'manage')) {
                $visibleTabs[] = $tab;
            }
        }

        return $visibleTabs;
    }

    public static function resolvePageTab(?User $user, string $module, ?string $requestedTab, string $defaultTab): ?string
    {
        $visibleTabs = self::visiblePageTabs($user, $module);

        if ($visibleTabs === []) {
            return null;
        }

        $visibleKeys = array_values(array_map(static fn (array $tab) => $tab['key'], $visibleTabs));

        if ($requestedTab && in_array($requestedTab, $visibleKeys, true)) {
            return $requestedTab;
        }

        if (in_array($defaultTab, $visibleKeys, true)) {
            return $defaultTab;
        }

        return $visibleKeys[0] ?? null;
    }

    public static function canAccessAny(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        foreach (self::dashboardAccessAbilities() as $ability) {
            if ($user->can($ability)) {
                return true;
            }
        }

        return false;
    }

    public static function canAccessModule(?User $user, string $module): bool
    {
        if (! $user) {
            return false;
        }

        if ($user->can('manage '.$module)) {
            return true;
        }

        foreach (self::sections($module) as $section) {
            if ($user->can('manage '.$section['permission'])) {
                return true;
            }
        }

        return false;
    }

    public static function can(?User $user, string $module, string $sectionKey, string $ability = 'manage'): bool
    {
        if (! $user) {
            return false;
        }

        $section = self::sections($module)[$sectionKey] ?? null;
        if ($section === null) {
            return false;
        }

        $moduleManage = $user->can('manage '.$module);
        $sectionManage = $user->can('manage '.$section['permission']);

        if ($ability === 'manage') {
            return $moduleManage || $sectionManage;
        }

        $moduleAbility = $user->can($ability.' '.$module);
        $sectionAbility = $user->can($ability.' '.$section['permission']);

        return ($moduleManage || $sectionManage) && ($moduleAbility || $sectionAbility);
    }

    public static function dashboardAccessAbilities(): array
    {
        $abilities = ['manage vender'];

        foreach (array_keys(self::modules()) as $module) {
            $abilities[] = 'manage '.$module;
        }

        foreach (self::roleTabs() as $tab) {
            foreach ($tab['rows'] as $row) {
                $abilities[] = 'manage '.$row['permission'];
            }
        }

        return array_values(array_unique($abilities));
    }

    public static function granularPermissions(): array
    {
        $permissions = [];

        foreach (self::roleTabs() as $tab) {
            foreach ($tab['rows'] as $row) {
                foreach ($row['actions'] as $action) {
                    $permissions[] = $action['ability'].' '.$row['permission'];
                }
            }
        }

        return array_values(array_unique($permissions));
    }

    public static function actionLabel(string $ability): string
    {
        return match ($ability) {
            'manage' => 'Manage',
            'create' => 'Create',
            'edit' => 'Edit',
            'show' => 'Show',
            'import' => 'Import',
            'export' => 'Export',
            default => ucfirst($ability),
        };
    }

    protected static function section(string $key, string $label, string $permission, array $abilities): array
    {
        return [
            'key' => $key,
            'label' => $label,
            'permission' => $permission,
            'actions' => array_map(
                static fn (string $ability) => [
                    'ability' => $ability,
                    'label' => self::actionLabel($ability),
                ],
                $abilities
            ),
        ];
    }

    protected static function tab(string $key, string $label, ?string $section): array
    {
        return [
            'key' => $key,
            'label' => $label,
            'section' => $section,
        ];
    }
}
