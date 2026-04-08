<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('gondal_agent_profiles')) {
            Schema::table('gondal_agent_profiles', function (Blueprint $table) {
                if (!Schema::hasColumn('gondal_agent_profiles', 'permitted_categories')) {
                    $table->json('permitted_categories')->nullable()->after('status');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('gondal_agent_profiles')) {
            Schema::table('gondal_agent_profiles', function (Blueprint $table) {
                if (Schema::hasColumn('gondal_agent_profiles', 'permitted_categories')) {
                    $table->dropColumn('permitted_categories');
                }
            });
        }
    }
};
