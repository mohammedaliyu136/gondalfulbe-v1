<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('gondal_notification_logs')) {
            Schema::create('gondal_notification_logs', function (Blueprint $table) {
                $table->id();
                $table->string('event_type');
                $table->string('recipient_type');
                $table->unsignedBigInteger('recipient_id');
                $table->string('channel')->default('sms');
                $table->text('message');
                $table->string('reference_hash')->unique();
                $table->string('status')->default('queued');
                $table->integer('retry_count')->default(0);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('gondal_notification_logs');
    }
};
