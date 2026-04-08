<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\MilkCollection\Models\MilkCollection;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('gondal_milk_quality_tests')) {
            Schema::create('gondal_milk_quality_tests', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('milk_collection_id');
                $table->unsignedBigInteger('milk_collection_center_id')->nullable();
                $table->unsignedBigInteger('farmer_id')->nullable();
                $table->unsignedBigInteger('project_id')->nullable();
                $table->unsignedBigInteger('tested_by')->nullable();
                $table->decimal('fat_percentage', 5, 2)->nullable();
                $table->decimal('snf_percentage', 5, 2)->nullable();
                $table->decimal('temperature', 5, 2)->nullable();
                $table->string('adulteration_test')->default('passed');
                $table->string('quality_grade')->nullable();
                $table->boolean('is_rejected')->default(false);
                $table->string('rejection_reason')->nullable();
                $table->timestamp('tested_at')->nullable();
                $table->json('meta')->nullable();
                $table->timestamps();
                $table->unique('milk_collection_id', 'gondal_milk_quality_tests_collection_unique');
                $table->foreign('milk_collection_id', 'gmqt_collection_fk')->references('id')->on('milk_collections')->cascadeOnDelete();
                $table->foreign('milk_collection_center_id', 'gmqt_center_fk')->references('id')->on('milk_collection_centers')->nullOnDelete();
                $table->foreign('farmer_id', 'gmqt_farmer_fk')->references('id')->on('venders')->nullOnDelete();
                $table->foreign('project_id', 'gmqt_project_fk')->references('id')->on('projects')->nullOnDelete();
                $table->foreign('tested_by', 'gmqt_tester_fk')->references('id')->on('users')->nullOnDelete();
            });
        }

        if (! Schema::hasTable('gondal_milk_center_reconciliations')) {
            Schema::create('gondal_milk_center_reconciliations', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('milk_collection_center_id');
                $table->unsignedBigInteger('project_id')->nullable();
                $table->date('reconciliation_date');
                $table->unsignedInteger('total_collections')->default(0);
                $table->unsignedInteger('accepted_collections')->default(0);
                $table->unsignedInteger('rejected_collections')->default(0);
                $table->decimal('total_quantity', 14, 2)->default(0);
                $table->decimal('accepted_quantity', 14, 2)->default(0);
                $table->decimal('rejected_quantity', 14, 2)->default(0);
                $table->decimal('accepted_value', 14, 2)->default(0);
                $table->unsignedBigInteger('last_recorded_by')->nullable();
                $table->timestamp('last_collection_at')->nullable();
                $table->string('status')->default('open');
                $table->json('meta')->nullable();
                $table->timestamps();
                $table->index(['milk_collection_center_id', 'reconciliation_date'], 'gondal_milk_center_recon_center_date_idx');
                $table->foreign('milk_collection_center_id', 'gmcr_center_fk')->references('id')->on('milk_collection_centers')->cascadeOnDelete();
                $table->foreign('project_id', 'gmcr_project_fk')->references('id')->on('projects')->nullOnDelete();
                $table->foreign('last_recorded_by', 'gmcr_last_recorder_fk')->references('id')->on('users')->nullOnDelete();
            });
        }

        $this->backfillQualityTests();
        $this->backfillReconciliations();
    }

    public function down(): void
    {
        Schema::dropIfExists('gondal_milk_center_reconciliations');
        Schema::dropIfExists('gondal_milk_quality_tests');
    }

    protected function backfillQualityTests(): void
    {
        if (! Schema::hasTable('milk_collections') || ! Schema::hasTable('gondal_milk_quality_tests')) {
            return;
        }

        DB::table('milk_collections')
            ->select([
                'id',
                'milk_collection_center_id',
                'farmer_id',
                'project_id',
                'recorded_by',
                'fat_percentage',
                'snf_percentage',
                'temperature',
                'adulteration_test',
                'quality_grade',
                'rejection_reason',
                'collection_date',
            ])
            ->orderBy('id')
            ->chunkById(100, function (Collection $collections): void {
                foreach ($collections as $collection) {
                    $exists = DB::table('gondal_milk_quality_tests')
                        ->where('milk_collection_id', $collection->id)
                        ->exists();

                    if ($exists) {
                        continue;
                    }

                    DB::table('gondal_milk_quality_tests')->insert([
                        'milk_collection_id' => $collection->id,
                        'milk_collection_center_id' => $collection->milk_collection_center_id,
                        'farmer_id' => $collection->farmer_id,
                        'project_id' => $collection->project_id,
                        'tested_by' => $collection->recorded_by,
                        'fat_percentage' => $collection->fat_percentage,
                        'snf_percentage' => $collection->snf_percentage,
                        'temperature' => $collection->temperature,
                        'adulteration_test' => $collection->adulteration_test ?: 'passed',
                        'quality_grade' => $collection->quality_grade,
                        'is_rejected' => strtoupper((string) $collection->quality_grade) === 'C',
                        'rejection_reason' => $collection->rejection_reason,
                        'tested_at' => $collection->collection_date,
                        'meta' => json_encode(['backfilled' => true]),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            });
    }

    protected function backfillReconciliations(): void
    {
        if (! Schema::hasTable('milk_collections') || ! Schema::hasTable('gondal_milk_center_reconciliations')) {
            return;
        }

        $groups = MilkCollection::query()
            ->selectRaw('milk_collection_center_id, project_id, DATE(collection_date) as reconciliation_date')
            ->whereNotNull('milk_collection_center_id')
            ->groupBy('milk_collection_center_id', 'project_id', DB::raw('DATE(collection_date)'))
            ->get();

        foreach ($groups as $group) {
            $query = MilkCollection::query()
                ->where('milk_collection_center_id', $group->milk_collection_center_id)
                ->whereDate('collection_date', $group->reconciliation_date);

            if ($group->project_id) {
                $query->where('project_id', $group->project_id);
            } else {
                $query->whereNull('project_id');
            }

            $collections = $query->get();
            $accepted = $collections->filter(fn (MilkCollection $collection) => strtoupper((string) $collection->quality_grade) !== 'C');
            $rejected = $collections->filter(fn (MilkCollection $collection) => strtoupper((string) $collection->quality_grade) === 'C');

            DB::table('gondal_milk_center_reconciliations')->updateOrInsert(
                [
                    'milk_collection_center_id' => $group->milk_collection_center_id,
                    'project_id' => $group->project_id,
                    'reconciliation_date' => $group->reconciliation_date,
                ],
                [
                    'total_collections' => $collections->count(),
                    'accepted_collections' => $accepted->count(),
                    'rejected_collections' => $rejected->count(),
                    'total_quantity' => round((float) $collections->sum('quantity'), 2),
                    'accepted_quantity' => round((float) $accepted->sum('quantity'), 2),
                    'rejected_quantity' => round((float) $rejected->sum('quantity'), 2),
                    'accepted_value' => round((float) $accepted->sum(function (MilkCollection $collection): float {
                        return (float) $collection->quantity * $this->priceForGrade($collection->project_id, $collection->quality_grade);
                    }), 2),
                    'last_recorded_by' => $collections->sortByDesc('collection_date')->first()?->recorded_by,
                    'last_collection_at' => $collections->max('collection_date'),
                    'status' => 'open',
                    'meta' => json_encode(['backfilled' => true]),
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }
    }

    protected function priceForGrade(?int $projectId, ?string $grade): float
    {
        $grade = strtoupper((string) $grade);
        $ruleQuery = DB::table('gondal_business_rules')
            ->where('rule_key', 'milk.grade_prices')
            ->orderByRaw('CASE WHEN scope_type = ? AND scope_id = ? THEN 0 WHEN scope_type = ? AND scope_id = 0 THEN 1 ELSE 2 END', ['project', $projectId ?? 0, 'global']);

        if ($projectId) {
            $ruleQuery->where(function ($query) use ($projectId) {
                $query->where(function ($scopedQuery) use ($projectId) {
                    $scopedQuery->where('scope_type', 'project')
                        ->where('scope_id', $projectId);
                })->orWhere(function ($globalQuery) {
                    $globalQuery->where('scope_type', 'global')
                        ->where('scope_id', 0);
                });
            });
        } else {
            $ruleQuery->where('scope_type', 'global')->where('scope_id', 0);
        }

        $rule = $ruleQuery->first();
        $prices = $rule?->rule_value ? json_decode($rule->rule_value, true) : null;

        return (float) ($prices[$grade] ?? 0);
    }
};
