<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('gondal_inventory_items', function (Blueprint $table) {
            $table->string('category')->nullable()->after('name');
            $table->string('unit')->nullable()->after('category');
        });

        Schema::table('gondal_inventory_sales', function (Blueprint $table) {
            $table->unsignedBigInteger('vender_id')->nullable()->after('inventory_item_id');
            $table->string('payment_method')->default('Cash')->after('unit_price');
        });

        Schema::table('gondal_inventory_credits', function (Blueprint $table) {
            $table->unsignedBigInteger('vender_id')->nullable()->after('inventory_item_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('gondal_inventory_items', function (Blueprint $table) {
            $table->dropColumn(['category', 'unit']);
        });

        Schema::table('gondal_inventory_sales', function (Blueprint $table) {
            $table->dropColumn(['vender_id', 'payment_method']);
        });

        Schema::table('gondal_inventory_credits', function (Blueprint $table) {
            $table->dropColumn(['vender_id']);
        });
    }
};
