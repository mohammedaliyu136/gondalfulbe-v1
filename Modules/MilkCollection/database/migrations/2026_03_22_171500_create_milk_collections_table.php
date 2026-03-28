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
        Schema::create('milk_collections', function (Blueprint $table) {
            $table->id();
            $table->string('batch_id')->nullable();
            $table->string('mcc_id'); // Mayo, Yola, Jabbi Lamba, etc.
            $table->unsignedBigInteger('farmer_id'); 
            $table->decimal('quantity', 8, 2);
            $table->decimal('fat_percentage', 5, 2)->nullable();
            $table->decimal('temperature', 5, 2)->nullable();
            $table->enum('quality_grade', ['A', 'B', 'C'])->nullable();
            $table->string('rejection_reason')->nullable();
            $table->unsignedBigInteger('recorded_by');
            $table->string('photo_path')->nullable();
            $table->dateTime('collection_date');
            $table->timestamps();

            // Optionally, add foreign keys here if needed
            // $table->foreign('farmer_id')->references('id')->on('venders')->onDelete('cascade');
            // $table->foreign('recorded_by')->references('id')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('milk_collections');
    }
};
