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
        Schema::table('gondal_inventory_sales', function (Blueprint $table) {
            $table->unsignedBigInteger('journal_entry_id')->nullable()->after('customer_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('gondal_inventory_sales', function (Blueprint $table) {
            $table->dropColumn('journal_entry_id');
        });
    }
};
