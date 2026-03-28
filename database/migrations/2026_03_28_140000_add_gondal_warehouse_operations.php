<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('gondal_warehouse_stocks')) {
            Schema::create('gondal_warehouse_stocks', function (Blueprint $table) {
                $table->id();
                $table->foreignId('warehouse_id')->constrained('warehouses')->cascadeOnDelete();
                $table->foreignId('inventory_item_id')->constrained('gondal_inventory_items')->cascadeOnDelete();
                $table->decimal('quantity', 12, 2)->default(0);
                $table->decimal('reorder_level', 12, 2)->default(0);
                $table->unsignedBigInteger('created_by')->default(0);
                $table->timestamps();
                $table->unique(['warehouse_id', 'inventory_item_id']);
            });
        }

        if (Schema::hasTable('gondal_stock_issues') && ! Schema::hasColumn('gondal_stock_issues', 'warehouse_id')) {
            Schema::table('gondal_stock_issues', function (Blueprint $table) {
                $table->foreignId('warehouse_id')->nullable()->after('agent_profile_id')->constrained('warehouses')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('gondal_stock_issues') && Schema::hasColumn('gondal_stock_issues', 'warehouse_id')) {
            Schema::table('gondal_stock_issues', function (Blueprint $table) {
                $table->dropConstrainedForeignId('warehouse_id');
            });
        }

        Schema::dropIfExists('gondal_warehouse_stocks');
    }
};
