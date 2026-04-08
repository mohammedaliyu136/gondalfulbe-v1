<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('gondal_program_agent_assignments')) {
            Schema::create('gondal_program_agent_assignments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
                $table->foreignId('agent_profile_id')->constrained('gondal_agent_profiles')->cascadeOnDelete();
                $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
                $table->date('starts_on')->nullable();
                $table->date('ends_on')->nullable();
                $table->string('status')->default('active');
                $table->timestamps();
                $table->unique(['project_id', 'agent_profile_id'], 'gondal_program_agent_unique');
            });
        }

        if (! Schema::hasTable('gondal_program_farmer_enrollments')) {
            Schema::create('gondal_program_farmer_enrollments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
                $table->foreignId('farmer_id')->constrained('venders')->cascadeOnDelete();
                $table->foreignId('enrolled_by')->nullable()->constrained('users')->nullOnDelete();
                $table->date('starts_on')->nullable();
                $table->date('ends_on')->nullable();
                $table->string('status')->default('active');
                $table->timestamps();
                $table->unique(['project_id', 'farmer_id'], 'gondal_program_farmer_unique');
            });
        }

        if (! Schema::hasTable('gondal_finance_accounts')) {
            Schema::create('gondal_finance_accounts', function (Blueprint $table) {
                $table->id();
                $table->string('code');
                $table->string('name');
                $table->string('type');
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->boolean('is_system')->default(false);
                $table->timestamps();
                $table->unique(['code', 'created_by'], 'gondal_finance_accounts_code_created_by_unique');
            });
        }

        if (! Schema::hasTable('gondal_journal_entries')) {
            Schema::create('gondal_journal_entries', function (Blueprint $table) {
                $table->id();
                $table->string('entry_number')->unique();
                $table->date('entry_date');
                $table->string('reference_type')->nullable();
                $table->unsignedBigInteger('reference_id')->nullable();
                $table->text('description')->nullable();
                $table->string('status')->default('posted');
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();
                $table->json('meta')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('gondal_journal_lines')) {
            Schema::create('gondal_journal_lines', function (Blueprint $table) {
                $table->id();
                $table->foreignId('journal_entry_id')->constrained('gondal_journal_entries')->cascadeOnDelete();
                $table->foreignId('finance_account_id')->constrained('gondal_finance_accounts')->cascadeOnDelete();
                $table->foreignId('farmer_id')->nullable()->constrained('venders')->nullOnDelete();
                $table->foreignId('project_id')->nullable()->constrained('projects')->nullOnDelete();
                $table->string('direction');
                $table->decimal('amount', 14, 2);
                $table->text('memo')->nullable();
                $table->json('meta')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('gondal_obligations')) {
            Schema::create('gondal_obligations', function (Blueprint $table) {
                $table->id();
                $table->string('reference')->unique();
                $table->foreignId('farmer_id')->constrained('venders')->cascadeOnDelete();
                $table->foreignId('agent_profile_id')->nullable()->constrained('gondal_agent_profiles')->nullOnDelete();
                $table->foreignId('inventory_credit_id')->nullable()->constrained('gondal_inventory_credits')->nullOnDelete();
                $table->foreignId('project_id')->nullable()->constrained('projects')->nullOnDelete();
                $table->string('source_type')->nullable();
                $table->unsignedBigInteger('source_id')->nullable();
                $table->decimal('principal_amount', 14, 2);
                $table->decimal('outstanding_amount', 14, 2);
                $table->decimal('recovered_amount', 14, 2)->default(0);
                $table->unsignedInteger('priority')->default(50);
                $table->decimal('max_deduction_percent', 5, 2)->nullable();
                $table->decimal('payout_floor_amount', 14, 2)->nullable();
                $table->date('due_date')->nullable();
                $table->string('status')->default('open');
                $table->json('meta')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('gondal_obligation_installments')) {
            Schema::create('gondal_obligation_installments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('obligation_id')->constrained('gondal_obligations')->cascadeOnDelete();
                $table->date('due_date');
                $table->decimal('amount_due', 14, 2);
                $table->decimal('amount_paid', 14, 2)->default(0);
                $table->string('status')->default('pending');
                $table->json('meta')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('gondal_settlement_runs')) {
            Schema::create('gondal_settlement_runs', function (Blueprint $table) {
                $table->id();
                $table->string('reference')->unique();
                $table->foreignId('farmer_id')->constrained('venders')->cascadeOnDelete();
                $table->foreignId('project_id')->nullable()->constrained('projects')->nullOnDelete();
                $table->date('period_start');
                $table->date('period_end');
                $table->decimal('gross_milk_value', 14, 2)->default(0);
                $table->decimal('total_deductions', 14, 2)->default(0);
                $table->decimal('net_payout', 14, 2)->default(0);
                $table->string('status')->default('draft');
                $table->foreignId('payment_batch_id')->nullable()->constrained('gondal_payment_batches')->nullOnDelete();
                $table->json('meta')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('gondal_deduction_runs')) {
            Schema::create('gondal_deduction_runs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('settlement_run_id')->constrained('gondal_settlement_runs')->cascadeOnDelete();
                $table->foreignId('farmer_id')->constrained('venders')->cascadeOnDelete();
                $table->date('period_start');
                $table->date('period_end');
                $table->decimal('gross_amount', 14, 2)->default(0);
                $table->decimal('deduction_cap_amount', 14, 2)->default(0);
                $table->decimal('payout_floor_amount', 14, 2)->default(0);
                $table->decimal('total_deducted_amount', 14, 2)->default(0);
                $table->decimal('net_payout_amount', 14, 2)->default(0);
                $table->string('status')->default('completed');
                $table->json('meta')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('gondal_deduction_allocations')) {
            Schema::create('gondal_deduction_allocations', function (Blueprint $table) {
                $table->id();
                $table->foreignId('deduction_run_id')->constrained('gondal_deduction_runs')->cascadeOnDelete();
                $table->foreignId('obligation_id')->constrained('gondal_obligations')->cascadeOnDelete();
                $table->decimal('amount', 14, 2);
                $table->unsignedInteger('priority')->default(50);
                $table->json('meta')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('gondal_payouts')) {
            Schema::create('gondal_payouts', function (Blueprint $table) {
                $table->id();
                $table->foreignId('settlement_run_id')->constrained('gondal_settlement_runs')->cascadeOnDelete();
                $table->foreignId('farmer_id')->constrained('venders')->cascadeOnDelete();
                $table->foreignId('payment_id')->nullable()->constrained('gondal_payments')->nullOnDelete();
                $table->decimal('amount', 14, 2);
                $table->string('status')->default('scheduled');
                $table->timestamp('scheduled_at')->nullable();
                $table->timestamp('paid_at')->nullable();
                $table->json('meta')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('gondal_payouts');
        Schema::dropIfExists('gondal_deduction_allocations');
        Schema::dropIfExists('gondal_deduction_runs');
        Schema::dropIfExists('gondal_settlement_runs');
        Schema::dropIfExists('gondal_obligation_installments');
        Schema::dropIfExists('gondal_obligations');
        Schema::dropIfExists('gondal_journal_lines');
        Schema::dropIfExists('gondal_journal_entries');
        Schema::dropIfExists('gondal_finance_accounts');
        Schema::dropIfExists('gondal_program_farmer_enrollments');
        Schema::dropIfExists('gondal_program_agent_assignments');
    }
};
