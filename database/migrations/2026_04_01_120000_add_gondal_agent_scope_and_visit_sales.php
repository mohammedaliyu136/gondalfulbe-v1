<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('gondal_agent_profiles') && ! Schema::hasColumn('gondal_agent_profiles', 'assigned_communities')) {
            Schema::table('gondal_agent_profiles', function (Blueprint $table) {
                $table->json('assigned_communities')->nullable()->after('outlet_name');
            });
        }

        if (Schema::hasTable('gondal_extension_visits')) {
            Schema::table('gondal_extension_visits', function (Blueprint $table) {
                if (! Schema::hasColumn('gondal_extension_visits', 'agent_profile_id')) {
                    $table->foreignId('agent_profile_id')->nullable()->after('farmer_id')->constrained('gondal_agent_profiles')->nullOnDelete();
                }
                if (! Schema::hasColumn('gondal_extension_visits', 'notes')) {
                    $table->text('notes')->nullable()->after('performance_score');
                }
            });
        }

        if (Schema::hasTable('gondal_inventory_sales') && ! Schema::hasColumn('gondal_inventory_sales', 'extension_visit_id')) {
            Schema::table('gondal_inventory_sales', function (Blueprint $table) {
                $table->foreignId('extension_visit_id')->nullable()->after('agent_profile_id')->constrained('gondal_extension_visits')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('gondal_inventory_sales') && Schema::hasColumn('gondal_inventory_sales', 'extension_visit_id')) {
            Schema::table('gondal_inventory_sales', function (Blueprint $table) {
                $table->dropConstrainedForeignId('extension_visit_id');
            });
        }

        if (Schema::hasTable('gondal_extension_visits')) {
            Schema::table('gondal_extension_visits', function (Blueprint $table) {
                if (Schema::hasColumn('gondal_extension_visits', 'agent_profile_id')) {
                    $table->dropConstrainedForeignId('agent_profile_id');
                }
                if (Schema::hasColumn('gondal_extension_visits', 'notes')) {
                    $table->dropColumn('notes');
                }
            });
        }

        if (Schema::hasTable('gondal_agent_profiles') && Schema::hasColumn('gondal_agent_profiles', 'assigned_communities')) {
            Schema::table('gondal_agent_profiles', function (Blueprint $table) {
                $table->dropColumn('assigned_communities');
            });
        }
    }
};
