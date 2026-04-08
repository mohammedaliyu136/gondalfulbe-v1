<?php

use App\Services\Gondal\BusinessRuleService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $exists = DB::table('gondal_business_rules')
            ->where('scope_type', 'global')
            ->where('scope_id', 0)
            ->where('rule_key', BusinessRuleService::KEY_SETTLEMENT_DEDUCTION_PRIORITY)
            ->exists();

        if ($exists) {
            return;
        }

        DB::table('gondal_business_rules')->insert([
            'scope_type' => 'global',
            'scope_id' => 0,
            'rule_key' => BusinessRuleService::KEY_SETTLEMENT_DEDUCTION_PRIORITY,
            'rule_value' => json_encode([
                'order' => [
                    'loan',
                    'feed_input_credit',
                    'service_charge',
                    'marketplace_order',
                    'manual_adjustment',
                    'other',
                ],
                'same_type_order' => 'oldest_due_date_first',
            ]),
            'description' => 'Cross-category deduction order and same-type ordering for settlement runs.',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('gondal_business_rules')
            ->where('scope_type', 'global')
            ->where('scope_id', 0)
            ->where('rule_key', BusinessRuleService::KEY_SETTLEMENT_DEDUCTION_PRIORITY)
            ->delete();
    }
};
