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
            $table->string('status')->default('pending')->after('total_price');
            $table->unsignedBigInteger('validated_by')->nullable()->after('status');
            $table->timestamp('validated_at')->nullable()->after('validated_by');
        });

        // Set existing records to 'validated'
        DB::table('milk_collections')->update(['status' => 'validated', 'validated_at' => now()]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('milk_collections', function (Blueprint $table) {
            $table->dropColumn(['status', 'validated_by', 'validated_at']);
        });
    }
};
