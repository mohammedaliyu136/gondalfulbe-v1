<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gondal_logistics_riders', function (Blueprint $table) {
            if (! Schema::hasColumn('gondal_logistics_riders', 'photo_path')) {
                $table->string('photo_path')->nullable()->after('phone');
            }
            if (! Schema::hasColumn('gondal_logistics_riders', 'bank_name')) {
                $table->string('bank_name')->nullable()->after('photo_path');
            }
            if (! Schema::hasColumn('gondal_logistics_riders', 'account_number')) {
                $table->string('account_number')->nullable()->after('bank_name');
            }
            if (! Schema::hasColumn('gondal_logistics_riders', 'account_name')) {
                $table->string('account_name')->nullable()->after('account_number');
            }
            if (! Schema::hasColumn('gondal_logistics_riders', 'bike_make')) {
                $table->string('bike_make')->nullable()->after('account_name');
            }
            if (! Schema::hasColumn('gondal_logistics_riders', 'bike_model')) {
                $table->string('bike_model')->nullable()->after('bike_make');
            }
            if (! Schema::hasColumn('gondal_logistics_riders', 'bike_plate_number')) {
                $table->string('bike_plate_number')->nullable()->after('bike_model');
            }
            if (! Schema::hasColumn('gondal_logistics_riders', 'identification_type')) {
                $table->string('identification_type')->nullable()->after('bike_plate_number');
            }
            if (! Schema::hasColumn('gondal_logistics_riders', 'identification_number')) {
                $table->string('identification_number')->nullable()->after('identification_type');
            }
            if (! Schema::hasColumn('gondal_logistics_riders', 'identification_document_path')) {
                $table->string('identification_document_path')->nullable()->after('identification_number');
            }
        });
    }

    public function down(): void
    {
        Schema::table('gondal_logistics_riders', function (Blueprint $table) {
            foreach ([
                'identification_document_path',
                'identification_number',
                'identification_type',
                'bike_plate_number',
                'bike_model',
                'bike_make',
                'account_name',
                'account_number',
                'bank_name',
                'photo_path',
            ] as $column) {
                if (Schema::hasColumn('gondal_logistics_riders', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
