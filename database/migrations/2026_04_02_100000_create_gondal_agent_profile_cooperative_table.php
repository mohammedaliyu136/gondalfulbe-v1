<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('gondal_agent_profile_cooperative')) {
            Schema::create('gondal_agent_profile_cooperative', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('agent_profile_id')->constrained('gondal_agent_profiles')->cascadeOnDelete();
                $table->foreignId('cooperative_id')->constrained('cooperatives')->cascadeOnDelete();
                $table->timestamps();
                $table->unique(['agent_profile_id', 'cooperative_id'], 'gondal_agent_profile_coop_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('gondal_agent_profile_cooperative');
    }
};
