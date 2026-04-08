<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('gondal_agent_inventory_adjustments')) {
            Schema::create('gondal_agent_inventory_adjustments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('agent_profile_id')->constrained('gondal_agent_profiles')->cascadeOnDelete();
                $table->foreignId('inventory_item_id')->constrained('gondal_inventory_items')->cascadeOnDelete();
                $table->foreignId('reconciliation_id')->nullable()->unique()->constrained('gondal_inventory_reconciliations')->nullOnDelete();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->string('reference')->unique();
                $table->decimal('quantity_delta', 10, 2);
                $table->string('reason')->default('reconciliation_variance');
                $table->date('effective_on');
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('gondal_agent_cash_liabilities')) {
            Schema::create('gondal_agent_cash_liabilities', function (Blueprint $table) {
                $table->id();
                $table->foreignId('agent_profile_id')->constrained('gondal_agent_profiles')->cascadeOnDelete();
                $table->foreignId('reconciliation_id')->nullable()->unique()->constrained('gondal_inventory_reconciliations')->nullOnDelete();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->string('reference')->unique();
                $table->decimal('amount', 12, 2);
                $table->string('liability_type')->default('cash_shortage');
                $table->string('status')->default('open');
                $table->date('due_date')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }

        if (Schema::hasTable('gondal_agent_profiles')) {
            DB::table('gondal_agent_profiles')
                ->where('credit_sales_enabled', false)
                ->update(['credit_sales_enabled' => true]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('gondal_agent_cash_liabilities');
        Schema::dropIfExists('gondal_agent_inventory_adjustments');
    }
};
