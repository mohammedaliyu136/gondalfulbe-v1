<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('gondal_inventory_batches')) {
            Schema::create('gondal_inventory_batches', function (Blueprint $table) {
                $table->id();
                $table->foreignId('inventory_item_id')->constrained('gondal_inventory_items');
                $table->string('batch_number')->unique();
                $table->date('expires_at')->nullable();
                $table->decimal('stock_qty', 10, 2)->default(0);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (Schema::hasTable('gondal_inventory_sales')) {
            Schema::table('gondal_inventory_sales', function (Blueprint $table) {
                if (!Schema::hasColumn('gondal_inventory_sales', 'batch_id')) {
                    $table->foreignId('batch_id')->nullable()->after('inventory_item_id')->constrained('gondal_inventory_batches');
                }
            });
        }

        if (Schema::hasTable('gondal_extension_visits')) {
            Schema::table('gondal_extension_visits', function (Blueprint $table) {
                if (!Schema::hasColumn('gondal_extension_visits', 'status')) {
                    $table->string('status')->default('requested')->after('visit_date');
                }
                if (!Schema::hasColumn('gondal_extension_visits', 'technician_user_id')) {
                    $table->foreignId('technician_user_id')->nullable()->after('agent_profile_id')->constrained('users');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('gondal_extension_visits')) {
            Schema::table('gondal_extension_visits', function (Blueprint $table) {
                if (Schema::hasColumn('gondal_extension_visits', 'technician_user_id')) {
                    $table->dropForeign(['technician_user_id']);
                    $table->dropColumn('technician_user_id');
                }
                if (Schema::hasColumn('gondal_extension_visits', 'status')) {
                    $table->dropColumn('status');
                }
            });
        }

        if (Schema::hasTable('gondal_inventory_sales')) {
            Schema::table('gondal_inventory_sales', function (Blueprint $table) {
                if (Schema::hasColumn('gondal_inventory_sales', 'batch_id')) {
                    $table->dropForeign(['batch_id']);
                    $table->dropColumn('batch_id');
                }
            });
        }

        Schema::dropIfExists('gondal_inventory_batches');
    }
};
