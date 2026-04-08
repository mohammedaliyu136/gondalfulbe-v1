<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('gondal_orders')) {
            Schema::create('gondal_orders', function (Blueprint $table) {
                $table->id();
                $table->string('reference')->unique();
                $table->foreignId('farmer_id')->constrained('venders')->cascadeOnDelete();
                $table->foreignId('agent_profile_id')->nullable()->constrained('gondal_agent_profiles')->nullOnDelete();
                $table->foreignId('project_id')->nullable()->constrained('projects')->nullOnDelete();
                $table->string('status')->default('draft');
                $table->string('payment_mode');
                $table->decimal('subtotal_amount', 14, 2)->default(0);
                $table->decimal('total_amount', 14, 2)->default(0);
                $table->decimal('settled_amount', 14, 2)->default(0);
                $table->decimal('outstanding_amount', 14, 2)->default(0);
                $table->date('ordered_on');
                $table->timestamp('submitted_at')->nullable();
                $table->timestamp('fulfilled_at')->nullable();
                $table->timestamp('settled_at')->nullable();
                $table->timestamp('cancelled_at')->nullable();
                $table->string('sponsor_name')->nullable();
                $table->string('sponsor_reference')->nullable();
                $table->foreignId('fulfilled_entry_id')->nullable()->constrained('gondal_journal_entries')->nullOnDelete();
                $table->foreignId('cancelled_entry_id')->nullable()->constrained('gondal_journal_entries')->nullOnDelete();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
                $table->text('notes')->nullable();
                $table->json('meta')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('gondal_order_items')) {
            Schema::create('gondal_order_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('order_id')->constrained('gondal_orders')->cascadeOnDelete();
                $table->foreignId('inventory_item_id')->constrained('gondal_inventory_items')->cascadeOnDelete();
                $table->foreignId('inventory_sale_id')->nullable()->constrained('gondal_inventory_sales')->nullOnDelete();
                $table->foreignId('inventory_credit_id')->nullable()->constrained('gondal_inventory_credits')->nullOnDelete();
                $table->foreignId('obligation_id')->nullable()->constrained('gondal_obligations')->nullOnDelete();
                $table->decimal('quantity', 14, 2);
                $table->decimal('unit_price', 14, 2);
                $table->decimal('line_total', 14, 2);
                $table->string('status')->default('draft');
                $table->json('meta')->nullable();
                $table->timestamps();
            });
        }

        if (Schema::hasTable('gondal_inventory_sales')) {
            Schema::table('gondal_inventory_sales', function (Blueprint $table) {
                if (! Schema::hasColumn('gondal_inventory_sales', 'order_id')) {
                    $table->foreignId('order_id')->nullable()->after('agent_profile_id')->constrained('gondal_orders')->nullOnDelete();
                }
                if (! Schema::hasColumn('gondal_inventory_sales', 'cancelled_at')) {
                    $table->timestamp('cancelled_at')->nullable()->after('sold_on');
                }
                if (! Schema::hasColumn('gondal_inventory_sales', 'cancelled_reason')) {
                    $table->string('cancelled_reason')->nullable()->after('cancelled_at');
                }
            });
        }

        if (Schema::hasTable('gondal_inventory_credits')) {
            Schema::table('gondal_inventory_credits', function (Blueprint $table) {
                if (! Schema::hasColumn('gondal_inventory_credits', 'order_id')) {
                    $table->foreignId('order_id')->nullable()->after('inventory_sale_id')->constrained('gondal_orders')->nullOnDelete();
                }
                if (! Schema::hasColumn('gondal_inventory_credits', 'cancelled_at')) {
                    $table->timestamp('cancelled_at')->nullable()->after('due_date');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('gondal_inventory_credits')) {
            Schema::table('gondal_inventory_credits', function (Blueprint $table) {
                if (Schema::hasColumn('gondal_inventory_credits', 'cancelled_at')) {
                    $table->dropColumn('cancelled_at');
                }
                if (Schema::hasColumn('gondal_inventory_credits', 'order_id')) {
                    $table->dropConstrainedForeignId('order_id');
                }
            });
        }

        if (Schema::hasTable('gondal_inventory_sales')) {
            Schema::table('gondal_inventory_sales', function (Blueprint $table) {
                if (Schema::hasColumn('gondal_inventory_sales', 'cancelled_reason')) {
                    $table->dropColumn('cancelled_reason');
                }
                if (Schema::hasColumn('gondal_inventory_sales', 'cancelled_at')) {
                    $table->dropColumn('cancelled_at');
                }
                if (Schema::hasColumn('gondal_inventory_sales', 'order_id')) {
                    $table->dropConstrainedForeignId('order_id');
                }
            });
        }

        Schema::dropIfExists('gondal_order_items');
        Schema::dropIfExists('gondal_orders');
    }
};
