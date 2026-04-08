<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('gondal_one_stop_shops')) {
            Schema::create('gondal_one_stop_shops', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('code')->unique();
                $table->foreignId('warehouse_id')->nullable()->constrained('warehouses')->nullOnDelete();
                $table->string('state')->nullable();
                $table->string('lga')->nullable();
                $table->foreignId('community_id')->nullable()->constrained('gondal_communities')->nullOnDelete();
                $table->text('address')->nullable();
                $table->string('status')->default('active');
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('gondal_one_stop_shop_stocks')) {
            Schema::create('gondal_one_stop_shop_stocks', function (Blueprint $table) {
                $table->id();
                $table->foreignId('one_stop_shop_id')->constrained('gondal_one_stop_shops')->cascadeOnDelete();
                $table->foreignId('inventory_item_id')->constrained('gondal_inventory_items')->cascadeOnDelete();
                $table->decimal('quantity', 15, 2)->default(0);
                $table->decimal('reorder_level', 15, 2)->default(0);
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();
                $table->unique(['one_stop_shop_id', 'inventory_item_id'], 'gondal_oss_stock_unique');
            });
        }

        Schema::table('gondal_agent_profiles', function (Blueprint $table) {
            if (! Schema::hasColumn('gondal_agent_profiles', 'one_stop_shop_id')) {
                $table->foreignId('one_stop_shop_id')
                    ->nullable()
                    ->after('project_id')
                    ->constrained('gondal_one_stop_shops')
                    ->nullOnDelete();
            }
        });

        Schema::table('gondal_stock_issues', function (Blueprint $table) {
            if (! Schema::hasColumn('gondal_stock_issues', 'one_stop_shop_id')) {
                $table->foreignId('one_stop_shop_id')
                    ->nullable()
                    ->after('warehouse_id')
                    ->constrained('gondal_one_stop_shops')
                    ->nullOnDelete();
            }
            if (! Schema::hasColumn('gondal_stock_issues', 'issue_stage')) {
                $table->string('issue_stage')->default('oss_to_agent')->after('one_stop_shop_id');
            }
        });

        Schema::table('gondal_agent_remittances', function (Blueprint $table) {
            if (! Schema::hasColumn('gondal_agent_remittances', 'one_stop_shop_id')) {
                $table->foreignId('one_stop_shop_id')
                    ->nullable()
                    ->after('agent_profile_id')
                    ->constrained('gondal_one_stop_shops')
                    ->nullOnDelete();
            }
        });

        Schema::table('gondal_inventory_reconciliations', function (Blueprint $table) {
            if (! Schema::hasColumn('gondal_inventory_reconciliations', 'one_stop_shop_id')) {
                $table->foreignId('one_stop_shop_id')
                    ->nullable()
                    ->after('agent_profile_id')
                    ->constrained('gondal_one_stop_shops')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('gondal_inventory_reconciliations', function (Blueprint $table) {
            if (Schema::hasColumn('gondal_inventory_reconciliations', 'one_stop_shop_id')) {
                $table->dropConstrainedForeignId('one_stop_shop_id');
            }
        });

        Schema::table('gondal_agent_remittances', function (Blueprint $table) {
            if (Schema::hasColumn('gondal_agent_remittances', 'one_stop_shop_id')) {
                $table->dropConstrainedForeignId('one_stop_shop_id');
            }
        });

        Schema::table('gondal_stock_issues', function (Blueprint $table) {
            if (Schema::hasColumn('gondal_stock_issues', 'one_stop_shop_id')) {
                $table->dropConstrainedForeignId('one_stop_shop_id');
            }
            if (Schema::hasColumn('gondal_stock_issues', 'issue_stage')) {
                $table->dropColumn('issue_stage');
            }
        });

        Schema::table('gondal_agent_profiles', function (Blueprint $table) {
            if (Schema::hasColumn('gondal_agent_profiles', 'one_stop_shop_id')) {
                $table->dropConstrainedForeignId('one_stop_shop_id');
            }
        });

        Schema::dropIfExists('gondal_one_stop_shop_stocks');
        Schema::dropIfExists('gondal_one_stop_shops');
    }
};
