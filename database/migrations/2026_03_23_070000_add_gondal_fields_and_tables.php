<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('venders')) {
            Schema::table('venders', function (Blueprint $table) {
                if (! Schema::hasColumn('venders', 'state')) {
                    $table->string('state')->nullable()->after('status');
                }
                if (! Schema::hasColumn('venders', 'lga')) {
                    $table->string('lga')->nullable()->after('state');
                }
                if (! Schema::hasColumn('venders', 'ward')) {
                    $table->string('ward')->nullable()->after('lga');
                }
                if (! Schema::hasColumn('venders', 'community')) {
                    $table->string('community')->nullable()->after('ward');
                }
                if (! Schema::hasColumn('venders', 'profile_photo_path')) {
                    $table->string('profile_photo_path')->nullable()->after('document_paths');
                }
                if (! Schema::hasColumn('venders', 'target_liters')) {
                    $table->decimal('target_liters', 10, 2)->default(0)->after('digital_payment_enabled');
                }
            });
        }

        if (Schema::hasTable('cooperatives')) {
            Schema::table('cooperatives', function (Blueprint $table) {
                if (! Schema::hasColumn('cooperatives', 'code')) {
                    $table->string('code')->nullable()->after('id');
                }
                if (! Schema::hasColumn('cooperatives', 'status')) {
                    $table->string('status')->default('active')->after('location');
                }
                if (! Schema::hasColumn('cooperatives', 'site_location')) {
                    $table->string('site_location')->nullable()->after('leader_phone');
                }
            });

            $rows = DB::table('cooperatives')->select('id', 'name', 'location', 'code')->get();
            foreach ($rows as $row) {
                if (! empty($row->code)) {
                    continue;
                }

                $seed = strtoupper(preg_replace('/[^A-Z0-9]+/', '-', (string) ($row->location ?: $row->name)));
                $seed = trim($seed, '-');
                $seed = $seed !== '' ? $seed : 'COOP';
                $candidate = 'COOP-'.$seed;
                $suffix = 2;

                while (DB::table('cooperatives')->where('code', $candidate)->exists()) {
                    $candidate = 'COOP-'.$seed.'-'.$suffix;
                    $suffix++;
                }

                DB::table('cooperatives')->where('id', $row->id)->update(['code' => $candidate]);
            }
        }

        if (Schema::hasTable('milk_collections') && ! Schema::hasColumn('milk_collections', 'cooperative_id')) {
            Schema::table('milk_collections', function (Blueprint $table) {
                $table->unsignedBigInteger('cooperative_id')->nullable()->after('farmer_id');
                $table->index('cooperative_id');
            });

            if (Schema::hasTable('venders')) {
                $farmerCooperatives = DB::table('venders')->pluck('cooperative_id', 'id');
                $collections = DB::table('milk_collections')->select('id', 'farmer_id')->whereNull('cooperative_id')->get();

                foreach ($collections as $collection) {
                    DB::table('milk_collections')
                        ->where('id', $collection->id)
                        ->update([
                            'cooperative_id' => $farmerCooperatives[$collection->farmer_id] ?? null,
                        ]);
                }
            }
        }

        if (! Schema::hasTable('gondal_logistics_riders')) {
            Schema::create('gondal_logistics_riders', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('code')->unique();
                $table->string('phone')->nullable();
                $table->string('status')->default('active');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('gondal_logistics_trips')) {
            Schema::create('gondal_logistics_trips', function (Blueprint $table) {
                $table->id();
                $table->foreignId('rider_id')->constrained('gondal_logistics_riders')->cascadeOnDelete();
                $table->foreignId('cooperative_id')->constrained('cooperatives')->cascadeOnDelete();
                $table->date('trip_date');
                $table->string('vehicle_name')->nullable();
                $table->string('departure_time')->nullable();
                $table->string('arrival_time')->nullable();
                $table->decimal('volume_liters', 10, 2)->default(0);
                $table->decimal('distance_km', 10, 2)->default(0);
                $table->decimal('fuel_cost', 12, 2)->default(0);
                $table->string('status')->default('scheduled');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('gondal_operation_costs')) {
            Schema::create('gondal_operation_costs', function (Blueprint $table) {
                $table->id();
                $table->date('cost_date');
                $table->foreignId('cooperative_id')->nullable()->constrained('cooperatives')->nullOnDelete();
                $table->string('category');
                $table->decimal('amount', 12, 2);
                $table->text('description')->nullable();
                $table->string('status')->default('pending');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('gondal_approval_rules')) {
            Schema::create('gondal_approval_rules', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->decimal('min_amount', 12, 2);
                $table->decimal('max_amount', 12, 2);
                $table->string('approver_role');
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('gondal_requisitions')) {
            Schema::create('gondal_requisitions', function (Blueprint $table) {
                $table->id();
                $table->string('reference')->unique();
                $table->foreignId('requester_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('cooperative_id')->nullable()->constrained('cooperatives')->nullOnDelete();
                $table->string('title');
                $table->text('description')->nullable();
                $table->decimal('total_amount', 12, 2);
                $table->string('priority')->default('medium');
                $table->string('status')->default('pending');
                $table->timestamp('submitted_at')->nullable();
                $table->timestamp('approved_at')->nullable();
                $table->timestamp('rejected_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('gondal_requisition_items')) {
            Schema::create('gondal_requisition_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('requisition_id')->constrained('gondal_requisitions')->cascadeOnDelete();
                $table->string('item_name');
                $table->decimal('quantity', 10, 2)->default(1);
                $table->string('unit')->default('unit');
                $table->decimal('unit_cost', 12, 2)->default(0);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('gondal_requisition_events')) {
            Schema::create('gondal_requisition_events', function (Blueprint $table) {
                $table->id();
                $table->foreignId('requisition_id')->constrained('gondal_requisitions')->cascadeOnDelete();
                $table->foreignId('actor_id')->constrained('users')->cascadeOnDelete();
                $table->string('action');
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('gondal_payment_batches')) {
            Schema::create('gondal_payment_batches', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('payee_type');
                $table->date('period_start');
                $table->date('period_end');
                $table->string('status')->default('draft');
                $table->decimal('total_amount', 12, 2)->default(0);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('gondal_payments')) {
            Schema::create('gondal_payments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('batch_id')->constrained('gondal_payment_batches')->cascadeOnDelete();
                $table->foreignId('farmer_id')->nullable()->constrained('venders')->nullOnDelete();
                $table->decimal('amount', 12, 2);
                $table->string('status')->default('pending');
                $table->date('payment_date')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('gondal_inventory_items')) {
            Schema::create('gondal_inventory_items', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('sku')->unique();
                $table->decimal('stock_qty', 10, 2)->default(0);
                $table->decimal('unit_price', 12, 2)->default(0);
                $table->string('status')->default('active');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('gondal_inventory_sales')) {
            Schema::create('gondal_inventory_sales', function (Blueprint $table) {
                $table->id();
                $table->foreignId('inventory_item_id')->constrained('gondal_inventory_items')->cascadeOnDelete();
                $table->decimal('quantity', 10, 2);
                $table->decimal('unit_price', 12, 2);
                $table->date('sold_on');
                $table->string('customer_name');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('gondal_inventory_credits')) {
            Schema::create('gondal_inventory_credits', function (Blueprint $table) {
                $table->id();
                $table->foreignId('inventory_item_id')->constrained('gondal_inventory_items')->cascadeOnDelete();
                $table->string('customer_name');
                $table->decimal('amount', 12, 2);
                $table->string('status')->default('open');
                $table->date('credit_date');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('gondal_extension_visits')) {
            Schema::create('gondal_extension_visits', function (Blueprint $table) {
                $table->id();
                $table->foreignId('farmer_id')->constrained('venders')->cascadeOnDelete();
                $table->string('officer_name');
                $table->string('topic');
                $table->integer('performance_score')->default(0);
                $table->date('visit_date');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('gondal_extension_trainings')) {
            Schema::create('gondal_extension_trainings', function (Blueprint $table) {
                $table->id();
                $table->string('title');
                $table->string('location');
                $table->integer('attendees')->default(0);
                $table->date('training_date');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('gondal_audit_logs')) {
            Schema::create('gondal_audit_logs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('module');
                $table->string('action');
                $table->json('context')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('gondal_audit_logs');
        Schema::dropIfExists('gondal_extension_trainings');
        Schema::dropIfExists('gondal_extension_visits');
        Schema::dropIfExists('gondal_inventory_credits');
        Schema::dropIfExists('gondal_inventory_sales');
        Schema::dropIfExists('gondal_inventory_items');
        Schema::dropIfExists('gondal_payments');
        Schema::dropIfExists('gondal_payment_batches');
        Schema::dropIfExists('gondal_requisition_events');
        Schema::dropIfExists('gondal_requisition_items');
        Schema::dropIfExists('gondal_requisitions');
        Schema::dropIfExists('gondal_approval_rules');
        Schema::dropIfExists('gondal_operation_costs');
        Schema::dropIfExists('gondal_logistics_trips');
        Schema::dropIfExists('gondal_logistics_riders');

        if (Schema::hasTable('milk_collections')) {
            Schema::table('milk_collections', function (Blueprint $table) {
                if (Schema::hasColumn('milk_collections', 'cooperative_id')) {
                    $table->dropIndex(['cooperative_id']);
                    $table->dropColumn('cooperative_id');
                }
            });
        }

        if (Schema::hasTable('cooperatives')) {
            Schema::table('cooperatives', function (Blueprint $table) {
                $columns = [];

                if (Schema::hasColumn('cooperatives', 'code')) {
                    $columns[] = 'code';
                }
                if (Schema::hasColumn('cooperatives', 'status')) {
                    $columns[] = 'status';
                }
                if (Schema::hasColumn('cooperatives', 'site_location')) {
                    $columns[] = 'site_location';
                }

                if ($columns !== []) {
                    $table->dropColumn($columns);
                }
            });
        }

        if (Schema::hasTable('venders')) {
            Schema::table('venders', function (Blueprint $table) {
                $columns = [];

                foreach (['state', 'lga', 'ward', 'community', 'profile_photo_path', 'target_liters'] as $column) {
                    if (Schema::hasColumn('venders', $column)) {
                        $columns[] = $column;
                    }
                }

                if ($columns !== []) {
                    $table->dropColumn($columns);
                }
            });
        }
    }
};
