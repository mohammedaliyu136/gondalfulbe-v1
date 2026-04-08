<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('gondal_orders')) {
            Schema::table('gondal_orders', function (Blueprint $table) {
                if (!Schema::hasColumn('gondal_orders', 'cash_amount')) {
                    $table->decimal('cash_amount', 14, 2)->default(0)->nullable()->after('total_amount');
                }
            });
        }

        if (Schema::hasTable('gondal_inventory_sales')) {
            Schema::table('gondal_inventory_sales', function (Blueprint $table) {
                if (!Schema::hasColumn('gondal_inventory_sales', 'cash_amount')) {
                    $table->decimal('cash_amount', 14, 2)->default(0)->nullable()->after('total_amount');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('gondal_orders')) {
            Schema::table('gondal_orders', function (Blueprint $table) {
                if (Schema::hasColumn('gondal_orders', 'cash_amount')) {
                    $table->dropColumn('cash_amount');
                }
            });
        }

        if (Schema::hasTable('gondal_inventory_sales')) {
            Schema::table('gondal_inventory_sales', function (Blueprint $table) {
                if (Schema::hasColumn('gondal_inventory_sales', 'cash_amount')) {
                    $table->dropColumn('cash_amount');
                }
            });
        }
    }
};
