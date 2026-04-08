<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('gondal_farmer_agent_assignments')) {
            Schema::create('gondal_farmer_agent_assignments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('farmer_id')->constrained('venders');
                $table->foreignId('agent_profile_id')->constrained('gondal_agent_profiles');
                $table->foreignId('assigned_by')->nullable()->constrained('users');
                $table->date('starts_on')->nullable();
                $table->date('ends_on')->nullable();
                $table->string('status')->default('active')->index();
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('gondal_farmer_agent_assignments');
    }
};
