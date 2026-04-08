<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('gondal_stock_issues') && Schema::hasColumn('gondal_stock_issues', 'agent_profile_id')) {
            Schema::table('gondal_stock_issues', function (Blueprint $table) {
                $table->unsignedBigInteger('agent_profile_id')->nullable()->change();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('gondal_stock_issues') && Schema::hasColumn('gondal_stock_issues', 'agent_profile_id')) {
            Schema::table('gondal_stock_issues', function (Blueprint $table) {
                $table->unsignedBigInteger('agent_profile_id')->nullable(false)->change();
            });
        }
    }
};
