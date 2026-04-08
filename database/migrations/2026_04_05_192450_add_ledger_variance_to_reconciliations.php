<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('gondal_milk_center_reconciliations')) {
            Schema::table('gondal_milk_center_reconciliations', function (Blueprint $table) {
                if (!Schema::hasColumn('gondal_milk_center_reconciliations', 'ledger_value')) {
                    $table->decimal('ledger_value', 15, 2)->default(0)->after('accepted_value');
                }
                if (!Schema::hasColumn('gondal_milk_center_reconciliations', 'variance_amount')) {
                    $table->decimal('variance_amount', 15, 2)->default(0)->after('ledger_value');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('gondal_milk_center_reconciliations')) {
            Schema::table('gondal_milk_center_reconciliations', function (Blueprint $table) {
                if (Schema::hasColumn('gondal_milk_center_reconciliations', 'ledger_value')) {
                    $table->dropColumn(['ledger_value', 'variance_amount']);
                }
            });
        }
    }
};
