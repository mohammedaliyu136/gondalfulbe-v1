<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('gondal_program_farmer_funding_limits')) {
            return;
        }

        Schema::create('gondal_program_farmer_funding_limits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->foreignId('farmer_id')->constrained('venders')->cascadeOnDelete();
            $table->decimal('limit_amount', 14, 2);
            $table->string('status')->default('active');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['project_id', 'farmer_id'], 'gondal_program_farmer_funding_limit_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gondal_program_farmer_funding_limits');
    }
};
