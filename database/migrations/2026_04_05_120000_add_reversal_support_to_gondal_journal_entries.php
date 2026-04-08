<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('gondal_journal_entries')) {
            return;
        }

        Schema::table('gondal_journal_entries', function (Blueprint $table) {
            if (! Schema::hasColumn('gondal_journal_entries', 'entry_type')) {
                $table->string('entry_type')->default('legacy')->after('entry_date');
            }

            if (! Schema::hasColumn('gondal_journal_entries', 'source_key')) {
                $table->string('source_key')->nullable()->after('reference_id');
                $table->index(['entry_type', 'source_key'], 'gondal_journal_entries_type_source_idx');
            }

            if (! Schema::hasColumn('gondal_journal_entries', 'reversal_of_entry_id')) {
                $table->foreignId('reversal_of_entry_id')
                    ->nullable()
                    ->after('status')
                    ->constrained('gondal_journal_entries')
                    ->nullOnDelete();
                $table->unique('reversal_of_entry_id', 'gondal_journal_entries_reversal_of_unique');
            }

            if (! Schema::hasColumn('gondal_journal_entries', 'reversed_at')) {
                $table->timestamp('reversed_at')->nullable()->after('posted_by');
            }

            if (! Schema::hasColumn('gondal_journal_entries', 'reversed_by')) {
                $table->foreignId('reversed_by')
                    ->nullable()
                    ->after('reversed_at')
                    ->constrained('users')
                    ->nullOnDelete();
            }
        });

        DB::table('gondal_journal_entries')
            ->whereNull('entry_type')
            ->update(['entry_type' => 'legacy']);
    }

    public function down(): void
    {
        if (! Schema::hasTable('gondal_journal_entries')) {
            return;
        }

        Schema::table('gondal_journal_entries', function (Blueprint $table) {
            if (Schema::hasColumn('gondal_journal_entries', 'reversed_by')) {
                $table->dropConstrainedForeignId('reversed_by');
            }

            if (Schema::hasColumn('gondal_journal_entries', 'reversal_of_entry_id')) {
                $table->dropUnique('gondal_journal_entries_reversal_of_unique');
                $table->dropConstrainedForeignId('reversal_of_entry_id');
            }

            if (Schema::hasColumn('gondal_journal_entries', 'source_key')) {
                $table->dropIndex('gondal_journal_entries_type_source_idx');
                $table->dropColumn('source_key');
            }

            if (Schema::hasColumn('gondal_journal_entries', 'reversed_at')) {
                $table->dropColumn('reversed_at');
            }

            if (Schema::hasColumn('gondal_journal_entries', 'entry_type')) {
                $table->dropColumn('entry_type');
            }
        });
    }
};
