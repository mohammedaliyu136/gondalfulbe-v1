<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->addMilkCollectionColumns();
        $this->addTransactionProjectColumns();

        $this->backfillMilkCollectionCenters();
        $this->backfillMilkCollectionProjects();
        $this->backfillInventorySaleProjects();
        $this->backfillInventoryCreditProjects();
        $this->backfillPaymentBatchProjects();
        $this->backfillPaymentProjects();

        $this->addMilkCollectionConstraints();
    }

    public function down(): void
    {
        if (Schema::hasTable('gondal_payments')) {
            Schema::table('gondal_payments', function (Blueprint $table) {
                if (Schema::hasColumn('gondal_payments', 'project_id')) {
                    $table->dropConstrainedForeignId('project_id');
                }
            });
        }

        if (Schema::hasTable('gondal_payment_batches')) {
            Schema::table('gondal_payment_batches', function (Blueprint $table) {
                if (Schema::hasColumn('gondal_payment_batches', 'project_id')) {
                    $table->dropConstrainedForeignId('project_id');
                }
            });
        }

        if (Schema::hasTable('gondal_inventory_credits')) {
            Schema::table('gondal_inventory_credits', function (Blueprint $table) {
                if (Schema::hasColumn('gondal_inventory_credits', 'project_id')) {
                    $table->dropConstrainedForeignId('project_id');
                }
            });
        }

        if (Schema::hasTable('gondal_inventory_sales')) {
            Schema::table('gondal_inventory_sales', function (Blueprint $table) {
                if (Schema::hasColumn('gondal_inventory_sales', 'project_id')) {
                    $table->dropConstrainedForeignId('project_id');
                }
            });
        }

        if (Schema::hasTable('milk_collections')) {
            Schema::table('milk_collections', function (Blueprint $table) {
                if (Schema::hasColumn('milk_collections', 'project_id')) {
                    $table->dropConstrainedForeignId('project_id');
                }

                if (Schema::hasColumn('milk_collections', 'milk_collection_center_id')) {
                    $table->dropConstrainedForeignId('milk_collection_center_id');
                }
            });
        }
    }

    protected function addMilkCollectionColumns(): void
    {
        if (! Schema::hasTable('milk_collections')) {
            return;
        }

        Schema::table('milk_collections', function (Blueprint $table) {
            if (! Schema::hasColumn('milk_collections', 'milk_collection_center_id')) {
                $table->foreignId('milk_collection_center_id')
                    ->nullable()
                    ->after('mcc_id')
                    ->constrained('milk_collection_centers')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('milk_collections', 'project_id')) {
                $table->foreignId('project_id')
                    ->nullable()
                    ->after('cooperative_id')
                    ->constrained('projects')
                    ->nullOnDelete();
            }
        });
    }

    protected function addTransactionProjectColumns(): void
    {
        if (Schema::hasTable('gondal_inventory_sales')) {
            Schema::table('gondal_inventory_sales', function (Blueprint $table) {
                if (! Schema::hasColumn('gondal_inventory_sales', 'project_id')) {
                    $table->foreignId('project_id')
                        ->nullable()
                        ->after('agent_profile_id')
                        ->constrained('projects')
                        ->nullOnDelete();
                }
            });
        }

        if (Schema::hasTable('gondal_inventory_credits')) {
            Schema::table('gondal_inventory_credits', function (Blueprint $table) {
                if (! Schema::hasColumn('gondal_inventory_credits', 'project_id')) {
                    $table->foreignId('project_id')
                        ->nullable()
                        ->after('agent_profile_id')
                        ->constrained('projects')
                        ->nullOnDelete();
                }
            });
        }

        if (Schema::hasTable('gondal_payment_batches')) {
            Schema::table('gondal_payment_batches', function (Blueprint $table) {
                if (! Schema::hasColumn('gondal_payment_batches', 'project_id')) {
                    $table->foreignId('project_id')
                        ->nullable()
                        ->after('payee_type')
                        ->constrained('projects')
                        ->nullOnDelete();
                }
            });
        }

        if (Schema::hasTable('gondal_payments')) {
            Schema::table('gondal_payments', function (Blueprint $table) {
                if (! Schema::hasColumn('gondal_payments', 'project_id')) {
                    $table->foreignId('project_id')
                        ->nullable()
                        ->after('farmer_id')
                        ->constrained('projects')
                        ->nullOnDelete();
                }
            });
        }
    }

    protected function backfillMilkCollectionCenters(): void
    {
        if (! Schema::hasTable('milk_collections') || ! Schema::hasTable('milk_collection_centers')) {
            return;
        }

        $cooperatives = Schema::hasTable('cooperatives')
            ? DB::table('cooperatives')->select('id', 'name', 'location')->get()->keyBy('id')
            : collect();
        $centers = DB::table('milk_collection_centers')->select('id', 'name', 'location')->get();
        $centerCache = [];

        foreach ($centers as $center) {
            $centerCache[$this->centerCacheKey($center->name, $center->location)] = (int) $center->id;
        }

        DB::table('milk_collections')
            ->select('id', 'mcc_id', 'cooperative_id', 'milk_collection_center_id')
            ->orderBy('id')
            ->chunkById(100, function (Collection $collections) use ($cooperatives, &$centerCache): void {
                foreach ($collections as $collection) {
                    if ($collection->milk_collection_center_id) {
                        continue;
                    }

                    $cooperative = $cooperatives->get($collection->cooperative_id);
                    $legacyCenterName = trim((string) $collection->mcc_id);
                    $centerName = $cooperative->name ?? ($legacyCenterName !== '' ? $legacyCenterName : 'Unassigned Center');
                    $centerLocation = $cooperative->location ?? ($legacyCenterName !== '' ? $legacyCenterName : null);
                    $cacheKey = $this->centerCacheKey($centerName, $centerLocation);

                    if (! isset($centerCache[$cacheKey])) {
                        $centerCache[$cacheKey] = (int) DB::table('milk_collection_centers')->insertGetId([
                            'name' => $centerName,
                            'location' => $centerLocation,
                            'contact_number' => null,
                            'created_by' => 0,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }

                    DB::table('milk_collections')
                        ->where('id', $collection->id)
                        ->update(['milk_collection_center_id' => $centerCache[$cacheKey]]);
                }
            });
    }

    protected function backfillMilkCollectionProjects(): void
    {
        if (! Schema::hasTable('milk_collections')) {
            return;
        }

        $farmerProjectMap = $this->activeFarmerProjectMap();

        DB::table('milk_collections')
            ->select('id', 'farmer_id', 'project_id')
            ->whereNull('project_id')
            ->orderBy('id')
            ->chunkById(100, function (Collection $collections) use ($farmerProjectMap): void {
                foreach ($collections as $collection) {
                    $projectId = $farmerProjectMap[$collection->farmer_id] ?? null;

                    if (! $projectId) {
                        continue;
                    }

                    DB::table('milk_collections')
                        ->where('id', $collection->id)
                        ->update(['project_id' => $projectId]);
                }
            });
    }

    protected function backfillInventorySaleProjects(): void
    {
        if (! Schema::hasTable('gondal_inventory_sales')) {
            return;
        }

        $farmerProjectMap = $this->activeFarmerProjectMap();
        $agentProjectMap = $this->activeAgentProjectMap();

        DB::table('gondal_inventory_sales')
            ->select('id', 'agent_profile_id', 'vender_id', 'project_id')
            ->whereNull('project_id')
            ->orderBy('id')
            ->chunkById(100, function (Collection $sales) use ($agentProjectMap, $farmerProjectMap): void {
                foreach ($sales as $sale) {
                    $projectId = $agentProjectMap[$sale->agent_profile_id] ?? $farmerProjectMap[$sale->vender_id] ?? null;

                    if (! $projectId) {
                        continue;
                    }

                    DB::table('gondal_inventory_sales')
                        ->where('id', $sale->id)
                        ->update(['project_id' => $projectId]);
                }
            });
    }

    protected function backfillInventoryCreditProjects(): void
    {
        if (! Schema::hasTable('gondal_inventory_credits')) {
            return;
        }

        $farmerProjectMap = $this->activeFarmerProjectMap();
        $agentProjectMap = $this->activeAgentProjectMap();
        $saleProjectMap = Schema::hasTable('gondal_inventory_sales')
            ? DB::table('gondal_inventory_sales')->pluck('project_id', 'id')->all()
            : [];

        DB::table('gondal_inventory_credits')
            ->select('id', 'inventory_sale_id', 'agent_profile_id', 'vender_id', 'project_id')
            ->whereNull('project_id')
            ->orderBy('id')
            ->chunkById(100, function (Collection $credits) use ($saleProjectMap, $agentProjectMap, $farmerProjectMap): void {
                foreach ($credits as $credit) {
                    $projectId = $saleProjectMap[$credit->inventory_sale_id] ?? $agentProjectMap[$credit->agent_profile_id] ?? $farmerProjectMap[$credit->vender_id] ?? null;

                    if (! $projectId) {
                        continue;
                    }

                    DB::table('gondal_inventory_credits')
                        ->where('id', $credit->id)
                        ->update(['project_id' => $projectId]);
                }
            });
    }

    protected function backfillPaymentBatchProjects(): void
    {
        if (! Schema::hasTable('gondal_payment_batches') || ! Schema::hasTable('gondal_settlement_runs')) {
            return;
        }

        $batchProjectMap = DB::table('gondal_settlement_runs')
            ->whereNotNull('payment_batch_id')
            ->whereNotNull('project_id')
            ->pluck('project_id', 'payment_batch_id')
            ->all();

        DB::table('gondal_payment_batches')
            ->select('id', 'project_id')
            ->whereNull('project_id')
            ->orderBy('id')
            ->chunkById(100, function (Collection $batches) use ($batchProjectMap): void {
                foreach ($batches as $batch) {
                    $projectId = $batchProjectMap[$batch->id] ?? null;

                    if (! $projectId) {
                        continue;
                    }

                    DB::table('gondal_payment_batches')
                        ->where('id', $batch->id)
                        ->update(['project_id' => $projectId]);
                }
            });
    }

    protected function backfillPaymentProjects(): void
    {
        if (! Schema::hasTable('gondal_payments') || ! Schema::hasTable('gondal_payment_batches')) {
            return;
        }

        $batchProjectMap = DB::table('gondal_payment_batches')->pluck('project_id', 'id')->all();

        DB::table('gondal_payments')
            ->select('id', 'batch_id', 'project_id')
            ->whereNull('project_id')
            ->orderBy('id')
            ->chunkById(100, function (Collection $payments) use ($batchProjectMap): void {
                foreach ($payments as $payment) {
                    $projectId = $batchProjectMap[$payment->batch_id] ?? null;

                    if (! $projectId) {
                        continue;
                    }

                    DB::table('gondal_payments')
                        ->where('id', $payment->id)
                        ->update(['project_id' => $projectId]);
                }
            });
    }

    protected function addMilkCollectionConstraints(): void
    {
        if (! Schema::hasTable('milk_collections')) {
            return;
        }

        $hasInvalidFarmerIds = Schema::hasTable('venders')
            && DB::table('milk_collections')
                ->leftJoin('venders', 'venders.id', '=', 'milk_collections.farmer_id')
                ->whereNull('venders.id')
                ->exists();

        $hasInvalidRecorderIds = Schema::hasTable('users')
            && DB::table('milk_collections')
                ->leftJoin('users', 'users.id', '=', 'milk_collections.recorded_by')
                ->whereNull('users.id')
                ->exists();

        Schema::table('milk_collections', function (Blueprint $table) use ($hasInvalidFarmerIds, $hasInvalidRecorderIds) {
            if (Schema::hasTable('venders') && ! $hasInvalidFarmerIds) {
                $table->foreign('farmer_id')->references('id')->on('venders')->restrictOnDelete();
            }

            if (Schema::hasTable('users') && ! $hasInvalidRecorderIds) {
                $table->foreign('recorded_by')->references('id')->on('users')->restrictOnDelete();
            }
        });
    }

    protected function activeFarmerProjectMap(): array
    {
        if (! Schema::hasTable('gondal_program_farmer_enrollments')) {
            return [];
        }

        $projectMap = [];

        DB::table('gondal_program_farmer_enrollments')
            ->select('farmer_id', 'project_id')
            ->where('status', 'active')
            ->orderByDesc('starts_on')
            ->orderByDesc('id')
            ->get()
            ->each(function ($enrollment) use (&$projectMap): void {
                if (! isset($projectMap[$enrollment->farmer_id])) {
                    $projectMap[$enrollment->farmer_id] = (int) $enrollment->project_id;
                }
            });

        return $projectMap;
    }

    protected function activeAgentProjectMap(): array
    {
        $projectMap = [];

        if (Schema::hasTable('gondal_agent_profiles')) {
            DB::table('gondal_agent_profiles')
                ->select('id', 'project_id')
                ->whereNotNull('project_id')
                ->orderBy('id')
                ->get()
                ->each(function ($agent) use (&$projectMap): void {
                    $projectMap[$agent->id] = (int) $agent->project_id;
                });
        }

        if (! Schema::hasTable('gondal_program_agent_assignments')) {
            return $projectMap;
        }

        DB::table('gondal_program_agent_assignments')
            ->select('agent_profile_id', 'project_id')
            ->where('status', 'active')
            ->orderByDesc('starts_on')
            ->orderByDesc('id')
            ->get()
            ->each(function ($assignment) use (&$projectMap): void {
                if (! isset($projectMap[$assignment->agent_profile_id])) {
                    $projectMap[$assignment->agent_profile_id] = (int) $assignment->project_id;
                }
            });

        return $projectMap;
    }

    protected function centerCacheKey(?string $name, ?string $location): string
    {
        return mb_strtolower(trim((string) $name).'|'.trim((string) ($location ?? '')));
    }
};
