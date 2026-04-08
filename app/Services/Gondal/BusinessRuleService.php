<?php

namespace App\Services\Gondal;

use App\Models\Gondal\BusinessRule;

class BusinessRuleService
{
    public const KEY_MILK_GRADE_PRICES = 'milk.grade_prices';
    public const KEY_SETTLEMENT_DEFAULTS = 'settlement.defaults';
    public const KEY_SETTLEMENT_DEDUCTION_PRIORITY = 'settlement.deduction_priority';
    public const KEY_INVENTORY_CREDIT_PAYMENT_METHODS = 'inventory.credit_payment_methods';
    public const KEY_INVENTORY_CREDIT_OBLIGATION_DEFAULTS = 'inventory.credit_obligation_defaults';

    public function getRuleValue(string $key, array $default = [], ?int $projectId = null): array
    {
        if ($projectId !== null) {
            $scopedRule = BusinessRule::query()
                ->where('scope_type', 'project')
                ->where('scope_id', $projectId)
                ->where('rule_key', $key)
                ->value('rule_value');

            if (is_array($scopedRule)) {
                return $scopedRule;
            }
        }

        $globalRule = BusinessRule::query()
            ->where('scope_type', 'global')
            ->where('scope_id', 0)
            ->where('rule_key', $key)
            ->value('rule_value');

        return is_array($globalRule) ? $globalRule : $default;
    }

    public function milkGradePrices(?int $projectId = null): array
    {
        return $this->getRuleValue(self::KEY_MILK_GRADE_PRICES, [
            'A' => 120.0,
            'B' => 100.0,
            'C' => 0.0,
        ], $projectId);
    }

    public function resolveMilkPrice(?string $grade, ?int $projectId = null): float
    {
        $prices = $this->milkGradePrices($projectId);
        $normalizedGrade = strtoupper((string) ($grade ?: 'B'));

        return (float) ($prices[$normalizedGrade] ?? $prices['B'] ?? 0.0);
    }

    public function settlementDefaults(?int $projectId = null): array
    {
        $defaults = $this->getRuleValue(self::KEY_SETTLEMENT_DEFAULTS, [
            'max_deduction_percent' => 100.0,
            'payout_floor_amount' => 0.0,
        ], $projectId);

        return [
            'max_deduction_percent' => (float) ($defaults['max_deduction_percent'] ?? 100.0),
            'payout_floor_amount' => (float) ($defaults['payout_floor_amount'] ?? 0.0),
        ];
    }

    public function inventoryCreditPaymentMethods(?int $projectId = null): array
    {
        $methods = $this->getRuleValue(self::KEY_INVENTORY_CREDIT_PAYMENT_METHODS, [
            'Credit',
            'Milk Collection Balance',
        ], $projectId);

        return array_values(array_filter($methods, fn ($method) => is_string($method) && $method !== ''));
    }

    public function inventoryCreditObligationDefaults(?int $projectId = null): array
    {
        $defaults = $this->getRuleValue(self::KEY_INVENTORY_CREDIT_OBLIGATION_DEFAULTS, [
            'priority' => 10,
            'max_deduction_percent' => 100.0,
            'payout_floor_amount' => 0.0,
            'due_days' => 14,
        ], $projectId);

        return [
            'priority' => (int) ($defaults['priority'] ?? 10),
            'max_deduction_percent' => isset($defaults['max_deduction_percent']) ? (float) $defaults['max_deduction_percent'] : null,
            'payout_floor_amount' => isset($defaults['payout_floor_amount']) ? (float) $defaults['payout_floor_amount'] : null,
            'due_days' => (int) ($defaults['due_days'] ?? 14),
        ];
    }

    public function settlementDeductionPriority(?int $projectId = null): array
    {
        $defaults = $this->getRuleValue(self::KEY_SETTLEMENT_DEDUCTION_PRIORITY, [
            'order' => [
                'loan',
                'feed_input_credit',
                'service_charge',
                'marketplace_order',
                'manual_adjustment',
                'other',
            ],
            'same_type_order' => 'oldest_due_date_first',
        ], $projectId);

        $order = collect($defaults['order'] ?? [])
            ->filter(fn ($value) => is_string($value) && $value !== '')
            ->map(fn ($value) => trim((string) $value))
            ->values()
            ->all();

        if ($order === []) {
            $order = [
                'loan',
                'feed_input_credit',
                'service_charge',
                'marketplace_order',
                'manual_adjustment',
                'other',
            ];
        }

        return [
            'order' => $order,
            'same_type_order' => (string) ($defaults['same_type_order'] ?? 'oldest_due_date_first'),
        ];
    }
}
