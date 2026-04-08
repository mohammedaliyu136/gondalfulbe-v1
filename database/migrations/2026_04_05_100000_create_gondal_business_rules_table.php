<?php

use App\Services\Gondal\BusinessRuleService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('gondal_business_rules')) {
            Schema::create('gondal_business_rules', function (Blueprint $table) {
                $table->id();
                $table->string('scope_type')->default('global');
                $table->unsignedBigInteger('scope_id')->default(0);
                $table->string('rule_key');
                $table->json('rule_value');
                $table->text('description')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->unique(['scope_type', 'scope_id', 'rule_key'], 'gondal_business_rules_scope_unique');
            });
        }

        $now = now();
        $defaults = [
            [
                'scope_type' => 'global',
                'scope_id' => 0,
                'rule_key' => BusinessRuleService::KEY_MILK_GRADE_PRICES,
                'rule_value' => json_encode([
                    'A' => 120.0,
                    'B' => 100.0,
                    'C' => 0.0,
                ]),
                'description' => 'Default milk grade pricing used by ledger postings.',
            ],
            [
                'scope_type' => 'global',
                'scope_id' => 0,
                'rule_key' => BusinessRuleService::KEY_SETTLEMENT_DEFAULTS,
                'rule_value' => json_encode([
                    'max_deduction_percent' => 50.0,
                    'payout_floor_amount' => 0.0,
                ]),
                'description' => 'Fallback settlement deduction cap and payout floor.',
            ],
            [
                'scope_type' => 'global',
                'scope_id' => 0,
                'rule_key' => BusinessRuleService::KEY_INVENTORY_CREDIT_PAYMENT_METHODS,
                'rule_value' => json_encode([
                    'Credit',
                    'Milk Collection Balance',
                ]),
                'description' => 'Inventory payment methods that create farmer credit obligations.',
            ],
            [
                'scope_type' => 'global',
                'scope_id' => 0,
                'rule_key' => BusinessRuleService::KEY_INVENTORY_CREDIT_OBLIGATION_DEFAULTS,
                'rule_value' => json_encode([
                    'priority' => 10,
                    'max_deduction_percent' => 35.0,
                    'payout_floor_amount' => 0.0,
                    'due_days' => 14,
                ]),
                'description' => 'Default obligation policy for inventory credit sales.',
            ],
        ];

        foreach ($defaults as $rule) {
            $exists = DB::table('gondal_business_rules')
                ->where('scope_type', $rule['scope_type'])
                ->where('scope_id', $rule['scope_id'])
                ->where('rule_key', $rule['rule_key'])
                ->exists();

            if ($exists) {
                continue;
            }

            DB::table('gondal_business_rules')->insert([
                ...$rule,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('gondal_business_rules');
    }
};
