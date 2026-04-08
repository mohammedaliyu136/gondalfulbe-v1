<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('milk_collections')) {
            Schema::table('milk_collections', function (Blueprint $table) {
                if (!Schema::hasColumn('milk_collections', 'unit_price')) {
                    $table->decimal('unit_price', 14, 2)->nullable();
                }
                if (!Schema::hasColumn('milk_collections', 'total_price')) {
                    $table->decimal('total_price', 14, 2)->nullable();
                }
            });

            // Fallback backfill based on an averaged approximation or just 0 for extremely old data
            // Any subsequent active logic will leverage the new workflow service.
            DB::table('milk_collections')->whereNull('unit_price')->update([
                'unit_price' => 350,
                'total_price' => DB::raw('quantity * 350')
            ]);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('milk_collections')) {
            Schema::table('milk_collections', function (Blueprint $table) {
                if (Schema::hasColumn('milk_collections', 'unit_price')) {
                    $table->dropColumn('unit_price');
                }
                if (Schema::hasColumn('milk_collections', 'total_price')) {
                    $table->dropColumn('total_price');
                }
            });
        }
    }
};
