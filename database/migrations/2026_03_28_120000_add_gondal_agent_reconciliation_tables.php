<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('gondal_agent_profiles')) {
            Schema::create('gondal_agent_profiles', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('vender_id')->nullable()->constrained('venders')->nullOnDelete();
                $table->foreignId('supervisor_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('agent_code')->unique();
                $table->string('agent_type');
                $table->string('outlet_name')->nullable();
                $table->string('assigned_warehouse')->nullable();
                $table->string('reconciliation_frequency')->default('daily');
                $table->string('settlement_mode')->default('consignment');
                $table->boolean('credit_sales_enabled')->default(false);
                $table->decimal('credit_limit', 12, 2)->default(0);
                $table->decimal('stock_variance_tolerance', 12, 2)->default(0);
                $table->decimal('cash_variance_tolerance', 12, 2)->default(0);
                $table->string('status')->default('active');
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('gondal_stock_issues')) {
            Schema::create('gondal_stock_issues', function (Blueprint $table) {
                $table->id();
                $table->foreignId('agent_profile_id')->constrained('gondal_agent_profiles')->cascadeOnDelete();
                $table->foreignId('inventory_item_id')->constrained('gondal_inventory_items')->cascadeOnDelete();
                $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
                $table->string('issue_reference')->unique();
                $table->string('batch_reference')->nullable();
                $table->decimal('quantity_issued', 10, 2);
                $table->decimal('unit_cost', 12, 2)->default(0);
                $table->date('issued_on');
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('gondal_agent_remittances')) {
            Schema::create('gondal_agent_remittances', function (Blueprint $table) {
                $table->id();
                $table->foreignId('agent_profile_id')->constrained('gondal_agent_profiles')->cascadeOnDelete();
                $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
                $table->string('reconciliation_mode')->default('daily');
                $table->string('reference')->unique();
                $table->decimal('amount', 12, 2);
                $table->string('payment_method')->default('transfer');
                $table->date('period_start')->nullable();
                $table->date('period_end')->nullable();
                $table->timestamp('remitted_at');
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('gondal_inventory_reconciliations')) {
            Schema::create('gondal_inventory_reconciliations', function (Blueprint $table) {
                $table->id();
                $table->foreignId('agent_profile_id')->constrained('gondal_agent_profiles')->cascadeOnDelete();
                $table->foreignId('inventory_item_id')->nullable()->constrained('gondal_inventory_items')->nullOnDelete();
                $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->string('reconciliation_mode')->default('daily');
                $table->string('reference')->unique();
                $table->date('period_start');
                $table->date('period_end');
                $table->decimal('opening_stock_qty', 10, 2)->default(0);
                $table->decimal('issued_stock_qty', 10, 2)->default(0);
                $table->decimal('sold_stock_qty', 10, 2)->default(0);
                $table->decimal('returned_stock_qty', 10, 2)->default(0);
                $table->decimal('damaged_stock_qty', 10, 2)->default(0);
                $table->decimal('expected_stock_qty', 10, 2)->default(0);
                $table->decimal('counted_stock_qty', 10, 2)->default(0);
                $table->decimal('stock_variance_qty', 10, 2)->default(0);
                $table->decimal('cash_sales_amount', 12, 2)->default(0);
                $table->decimal('transfer_sales_amount', 12, 2)->default(0);
                $table->decimal('credit_sales_amount', 12, 2)->default(0);
                $table->decimal('credit_collections_amount', 12, 2)->default(0);
                $table->decimal('expected_cash_amount', 12, 2)->default(0);
                $table->decimal('remitted_cash_amount', 12, 2)->default(0);
                $table->decimal('cash_variance_amount', 12, 2)->default(0);
                $table->decimal('outstanding_credit_amount', 12, 2)->default(0);
                $table->string('status')->default('draft');
                $table->text('agent_notes')->nullable();
                $table->text('review_notes')->nullable();
                $table->timestamps();
            });
        }

        if (Schema::hasTable('gondal_inventory_sales')) {
            Schema::table('gondal_inventory_sales', function (Blueprint $table) {
                if (! Schema::hasColumn('gondal_inventory_sales', 'agent_profile_id')) {
                    $table->foreignId('agent_profile_id')->nullable()->after('inventory_item_id')->constrained('gondal_agent_profiles')->nullOnDelete();
                }
                if (! Schema::hasColumn('gondal_inventory_sales', 'total_amount')) {
                    $table->decimal('total_amount', 12, 2)->default(0)->after('unit_price');
                }
                if (! Schema::hasColumn('gondal_inventory_sales', 'credit_allowed_snapshot')) {
                    $table->boolean('credit_allowed_snapshot')->default(false)->after('payment_method');
                }
            });
        }

        if (Schema::hasTable('gondal_inventory_credits')) {
            Schema::table('gondal_inventory_credits', function (Blueprint $table) {
                if (! Schema::hasColumn('gondal_inventory_credits', 'agent_profile_id')) {
                    $table->foreignId('agent_profile_id')->nullable()->after('inventory_item_id')->constrained('gondal_agent_profiles')->nullOnDelete();
                }
                if (! Schema::hasColumn('gondal_inventory_credits', 'inventory_sale_id')) {
                    $table->foreignId('inventory_sale_id')->nullable()->after('agent_profile_id')->constrained('gondal_inventory_sales')->nullOnDelete();
                }
                if (! Schema::hasColumn('gondal_inventory_credits', 'outstanding_amount')) {
                    $table->decimal('outstanding_amount', 12, 2)->default(0)->after('amount');
                }
                if (! Schema::hasColumn('gondal_inventory_credits', 'due_date')) {
                    $table->date('due_date')->nullable()->after('credit_date');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('gondal_inventory_credits')) {
            Schema::table('gondal_inventory_credits', function (Blueprint $table) {
                foreach (['inventory_sale_id', 'agent_profile_id'] as $column) {
                    if (Schema::hasColumn('gondal_inventory_credits', $column)) {
                        $table->dropConstrainedForeignId($column);
                    }
                }
                foreach (['outstanding_amount', 'due_date'] as $column) {
                    if (Schema::hasColumn('gondal_inventory_credits', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        if (Schema::hasTable('gondal_inventory_sales')) {
            Schema::table('gondal_inventory_sales', function (Blueprint $table) {
                if (Schema::hasColumn('gondal_inventory_sales', 'agent_profile_id')) {
                    $table->dropConstrainedForeignId('agent_profile_id');
                }
                foreach (['total_amount', 'credit_allowed_snapshot'] as $column) {
                    if (Schema::hasColumn('gondal_inventory_sales', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        Schema::dropIfExists('gondal_inventory_reconciliations');
        Schema::dropIfExists('gondal_agent_remittances');
        Schema::dropIfExists('gondal_stock_issues');
        Schema::dropIfExists('gondal_agent_profiles');
    }
};
