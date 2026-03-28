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
        Schema::table('milk_collections', function (Blueprint $table) {
            $table->decimal('snf_percentage', 5, 2)->nullable()->after('fat_percentage');
            $table->enum('adulteration_test', ['passed', 'failed'])->default('passed')->after('rejection_reason');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('milk_collections', function (Blueprint $table) {
            $table->dropColumn(['snf_percentage', 'adulteration_test']);
        });
    }
};
