<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('venders', function (Blueprint $table) {
            $table->unsignedBigInteger('cooperative_id')->nullable()->after('id');
            $table->string('gender')->nullable()->after('email');
            $table->string('status')->default('active')->after('is_active');
            $table->date('registration_date')->nullable()->after('created_by');
            $table->text('document_paths')->nullable()->after('avatar'); // use text instead of json for broader compatibility
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('venders', function (Blueprint $table) {
            $table->dropColumn(['cooperative_id', 'gender', 'status', 'registration_date', 'document_paths']);
        });
    }
};
