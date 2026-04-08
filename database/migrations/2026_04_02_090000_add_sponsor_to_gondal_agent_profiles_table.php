<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gondal_agent_profiles', function (Blueprint $table): void {
            if (! Schema::hasColumn('gondal_agent_profiles', 'sponsor_user_id')) {
                $table->foreignId('sponsor_user_id')
                    ->nullable()
                    ->after('supervisor_user_id')
                    ->constrained('users')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('gondal_agent_profiles', function (Blueprint $table): void {
            if (Schema::hasColumn('gondal_agent_profiles', 'sponsor_user_id')) {
                $table->dropConstrainedForeignId('sponsor_user_id');
            }
        });
    }
};
