<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('gondal_agent_profiles')) {
            return;
        }

        Schema::table('gondal_agent_profiles', function (Blueprint $table) {
            if (! Schema::hasColumn('gondal_agent_profiles', 'first_name')) {
                $table->string('first_name')->nullable()->after('agent_type');
            }
            if (! Schema::hasColumn('gondal_agent_profiles', 'middle_name')) {
                $table->string('middle_name')->nullable()->after('first_name');
            }
            if (! Schema::hasColumn('gondal_agent_profiles', 'last_name')) {
                $table->string('last_name')->nullable()->after('middle_name');
            }
            if (! Schema::hasColumn('gondal_agent_profiles', 'gender')) {
                $table->string('gender')->nullable()->after('last_name');
            }
            if (! Schema::hasColumn('gondal_agent_profiles', 'phone_number')) {
                $table->string('phone_number')->nullable()->after('gender');
            }
            if (! Schema::hasColumn('gondal_agent_profiles', 'email')) {
                $table->string('email')->nullable()->after('phone_number');
            }
            if (! Schema::hasColumn('gondal_agent_profiles', 'nin')) {
                $table->string('nin')->nullable()->after('email');
            }
            if (! Schema::hasColumn('gondal_agent_profiles', 'state')) {
                $table->string('state')->nullable()->after('nin');
            }
            if (! Schema::hasColumn('gondal_agent_profiles', 'lga')) {
                $table->string('lga')->nullable()->after('state');
            }
            if (! Schema::hasColumn('gondal_agent_profiles', 'community')) {
                $table->string('community')->nullable()->after('lga');
            }
            if (! Schema::hasColumn('gondal_agent_profiles', 'residential_address')) {
                $table->text('residential_address')->nullable()->after('community');
            }
            if (! Schema::hasColumn('gondal_agent_profiles', 'permanent_address')) {
                $table->text('permanent_address')->nullable()->after('residential_address');
            }
            if (! Schema::hasColumn('gondal_agent_profiles', 'account_number')) {
                $table->string('account_number')->nullable()->after('permanent_address');
            }
            if (! Schema::hasColumn('gondal_agent_profiles', 'account_name')) {
                $table->string('account_name')->nullable()->after('account_number');
            }
            if (! Schema::hasColumn('gondal_agent_profiles', 'bank_details')) {
                $table->text('bank_details')->nullable()->after('account_name');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('gondal_agent_profiles')) {
            return;
        }

        Schema::table('gondal_agent_profiles', function (Blueprint $table) {
            $columns = [
                'first_name',
                'middle_name',
                'last_name',
                'gender',
                'phone_number',
                'email',
                'nin',
                'state',
                'lga',
                'community',
                'residential_address',
                'permanent_address',
                'account_number',
                'account_name',
                'bank_details',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('gondal_agent_profiles', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
