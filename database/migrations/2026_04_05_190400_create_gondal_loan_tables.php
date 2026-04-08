<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('gondal_loans')) {
            Schema::create('gondal_loans', function (Blueprint $table) {
                $table->id();
                $table->string('reference')->unique();
                $table->foreignId('farmer_id')->constrained('venders')->cascadeOnDelete();
                $table->foreignId('agent_profile_id')->nullable()->constrained('gondal_agent_profiles')->nullOnDelete();
                $table->foreignId('project_id')->nullable()->constrained('projects')->nullOnDelete();
                $table->string('type'); // input, equipment, emergency
                $table->decimal('principal_amount', 14, 2);
                $table->decimal('interest_rate', 5, 2)->default(0);
                $table->string('status')->default('pending'); // pending, approved, disbursed, rejected, default, closed
                $table->text('notes')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('approved_at')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('gondal_loan_repayment_schedules')) {
            Schema::create('gondal_loan_repayment_schedules', function (Blueprint $table) {
                $table->id();
                $table->foreignId('gondal_loan_id')->constrained('gondal_loans')->cascadeOnDelete();
                $table->date('due_date');
                $table->decimal('amount_due', 14, 2);
                $table->decimal('amount_paid', 14, 2)->default(0);
                $table->string('status')->default('pending'); // pending, paid, partial, overdue
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('gondal_loan_disbursements')) {
            Schema::create('gondal_loan_disbursements', function (Blueprint $table) {
                $table->id();
                $table->foreignId('gondal_loan_id')->constrained('gondal_loans')->cascadeOnDelete();
                $table->date('disbursal_date');
                $table->decimal('amount', 14, 2);
                $table->string('status')->default('completed');
                $table->foreignId('disbursed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('gondal_loan_disbursements');
        Schema::dropIfExists('gondal_loan_repayment_schedules');
        Schema::dropIfExists('gondal_loans');
    }
};
