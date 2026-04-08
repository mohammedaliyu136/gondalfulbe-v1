<?php

namespace App\Services\Gondal;

use App\Models\Gondal\MilkCollectionReconciliation;
use App\Models\Gondal\MilkQualityTest;
use App\Models\User;
use App\Models\Vender;
use Illuminate\Support\Facades\DB;
use Modules\MilkCollection\Models\MilkCollection;
use Modules\MilkCollection\Models\MilkCollectionCenter;

class MilkCollectionWorkflowService
{
    public function __construct(
        protected LedgerService $ledgerService,
        protected ProgramScopeService $programScopeService,
    ) {}

    public function recordCollection(array $payload, Vender $farmer, User $actor, ?MilkCollectionCenter $center = null, ?int $projectId = null): MilkCollection
    {
        if (! $farmer->is_active || $farmer->status === 'inactive') {
            throw ValidationException::withMessages([
                'farmer_id' => [__('Inactive farmers cannot submit milk collections.')],
            ]);
        }

        return DB::transaction(function () use ($actor, $center, $farmer, $payload, $projectId): MilkCollection {
            $resolvedCenter = $this->resolveCenter($farmer, $actor, $center, $payload['mcc_id'] ?? null);
            $resolvedProjectId = $projectId ?? $this->programScopeService->resolveFarmerProjectId($farmer, $actor);

            $attributes = [
                'batch_id' => $payload['batch_id'] ?? 'MILK-'.now()->format('YmdHis'),
                'mcc_id' => $payload['mcc_id'] ?? ($resolvedCenter?->location ?: $resolvedCenter?->name ?: 'N/A'),
                'milk_collection_center_id' => $resolvedCenter?->id,
                'farmer_id' => $farmer->id,
                'cooperative_id' => $payload['cooperative_id'] ?? $farmer->cooperative_id,
                'project_id' => $resolvedProjectId,
                'quantity' => $payload['quantity'],
                'fat_percentage' => $payload['fat_percentage'] ?? null,
                'snf_percentage' => $payload['snf_percentage'] ?? null,
                'temperature' => $payload['temperature'] ?? null,
                'quality_grade' => $payload['quality_grade'] ?? null,
                'rejection_reason' => $payload['rejection_reason'] ?? null,
                'adulteration_test' => $payload['adulteration_test'] ?? 'passed',
                'recorded_by' => $payload['recorded_by'] ?? $actor->id,
                'photo_path' => $payload['photo_path'] ?? null,
                'collection_date' => $payload['collection_date'],
                'status' => 'pending',
            ];

            $unitPrice = $this->ledgerService->resolveMilkPrice(new MilkCollection(['quality_grade' => $payload['quality_grade'] ?? null]));
            $attributes['unit_price'] = $unitPrice;
            $attributes['total_price'] = round((float) $payload['quantity'] * $unitPrice, 2);

            $collection = array_key_exists('quality_grade', $payload)
                ? MilkCollection::withoutEvents(fn () => MilkCollection::query()->create($attributes))
                : MilkCollection::query()->create($attributes);

            MilkQualityTest::query()->updateOrCreate(
                ['milk_collection_id' => $collection->id],
                [
                    'milk_collection_center_id' => $collection->milk_collection_center_id,
                    'farmer_id' => $collection->farmer_id,
                    'project_id' => $collection->project_id,
                    'tested_by' => $actor->id,
                    'fat_percentage' => $collection->fat_percentage,
                    'snf_percentage' => $collection->snf_percentage,
                    'temperature' => $collection->temperature,
                    'adulteration_test' => $collection->adulteration_test ?: 'passed',
                    'quality_grade' => $collection->quality_grade,
                    'is_rejected' => strtoupper((string) $collection->quality_grade) === 'C',
                    'rejection_reason' => $collection->rejection_reason,
                    'tested_at' => $collection->collection_date,
                    'meta' => [
                        'captured_via' => $payload['captured_via'] ?? 'web',
                    ],
                ],
            );

            // Skipping ledger posting during initial recording
            // if (strtoupper((string) $collection->quality_grade) !== 'C') {
            //     $this->ledgerService->postMilkCollectionValue($collection, null, $actor);
            // }

            if ($collection->milk_collection_center_id) {
                $this->syncDailyReconciliation($collection, $actor);
            }

            return $collection->fresh(['qualityTest', 'collectionCenter', 'project']);
        });
    }

    public function validateCollection(MilkCollection $collection, array $payload, User $actor): MilkCollection
    {
        return DB::transaction(function () use ($actor, $collection, $payload): MilkCollection {
            $collection->update([
                'fat_percentage' => $payload['fat_percentage'] ?? $collection->fat_percentage,
                'snf_percentage' => $payload['snf_percentage'] ?? $collection->snf_percentage,
                'temperature' => $payload['temperature'] ?? $collection->temperature,
                'adulteration_test' => $payload['adulteration_test'] ?? ($collection->adulteration_test ?: 'passed'),
                'quality_grade' => $payload['quality_grade'],
                'rejection_reason' => $payload['rejection_reason'] ?? null,
                'status' => strtoupper((string) $payload['quality_grade']) === 'C' ? 'rejected' : 'validated',
                'validated_by' => $actor->id,
                'validated_at' => now(),
            ]);

            // Now resolve the price based on the final grade assigned by supervisor
            $unitPrice = $this->ledgerService->resolveMilkPrice($collection);
            $collection->update([
                'unit_price' => $unitPrice,
                'total_price' => round((float) $collection->quantity * $unitPrice, 2),
            ]);

            // Sync the quality test record
            MilkQualityTest::query()->updateOrCreate(
                ['milk_collection_id' => $collection->id],
                [
                    'tested_by' => $actor->id,
                    'fat_percentage' => $collection->fat_percentage,
                    'snf_percentage' => $collection->snf_percentage,
                    'temperature' => $collection->temperature,
                    'adulteration_test' => $collection->adulteration_test,
                    'quality_grade' => $collection->quality_grade,
                    'is_rejected' => $collection->status === 'rejected',
                    'rejection_reason' => $collection->rejection_reason,
                    'tested_at' => now(),
                ]
            );

            // Post to ledger if NOT rejected
            if ($collection->status === 'validated') {
                $this->ledgerService->postMilkCollectionValue($collection, null, $actor);
            }

            if ($collection->milk_collection_center_id) {
                $this->syncDailyReconciliation($collection, $actor);
            }

            return $collection->fresh(['qualityTest', 'collectionCenter', 'project']);
        });
    }

    public function syncDailyReconciliation(MilkCollection $collection, ?User $actor = null): MilkCollectionReconciliation
    {
        $reconciliationDate = optional($collection->collection_date)->toDateString() ?: now()->toDateString();
        $query = MilkCollection::query()
            ->where('milk_collection_center_id', $collection->milk_collection_center_id)
            ->whereDate('collection_date', $reconciliationDate);

        if ($collection->project_id) {
            $query->where('project_id', $collection->project_id);
        } else {
            $query->whereNull('project_id');
        }

        $collections = $query->get();
        $accepted = $collections->filter(fn (MilkCollection $item) => strtoupper((string) $item->quality_grade) !== 'C');
        $rejected = $collections->filter(fn (MilkCollection $item) => strtoupper((string) $item->quality_grade) === 'C');
        $latestCollection = $collections->sortByDesc('collection_date')->first();

        $acceptedValue = round((float) $accepted->sum(fn (MilkCollection $item) => (float) $item->quantity * $this->ledgerService->resolveMilkPrice($item)), 2);

        $ledgerValue = round((float) \App\Models\Gondal\JournalLine::query()
            ->whereIn('farmer_id', $collections->pluck('farmer_id')->filter()->unique())
            ->whereHas('account', fn ($q) => $q->where('code', 'GL-FARMER-PAY'))
            ->where('direction', 'credit')
            ->whereDate('created_at', $reconciliationDate)
            ->sum('amount'), 2);

        $varianceAmount = $acceptedValue - $ledgerValue;

        $attributes = [
            'milk_collection_center_id' => $collection->milk_collection_center_id,
            'project_id' => $collection->project_id,
            'reconciliation_date' => $reconciliationDate,
        ];

        return MilkCollectionReconciliation::query()->updateOrCreate(
            $attributes,
            [
                'total_collections' => $collections->count(),
                'accepted_collections' => $accepted->count(),
                'rejected_collections' => $rejected->count(),
                'total_quantity' => round((float) $collections->sum('quantity'), 2),
                'accepted_quantity' => round((float) $accepted->sum('quantity'), 2),
                'rejected_quantity' => round((float) $rejected->sum('quantity'), 2),
                'accepted_value' => $acceptedValue,
                'ledger_value' => $ledgerValue,
                'variance_amount' => $varianceAmount,
                'last_recorded_by' => $actor?->id ?? $latestCollection?->recorded_by,
                'last_collection_at' => optional($latestCollection?->collection_date)?->toDateTimeString(),
                'status' => $varianceAmount != 0 ? 'escalated' : 'open',
                'meta' => [
                    'last_synced_at' => now()->toDateTimeString(),
                ],
            ],
        );
    }

    protected function resolveCenter(Vender $farmer, User $actor, ?MilkCollectionCenter $center = null, ?string $legacyMccId = null): ?MilkCollectionCenter
    {
        if ($center) {
            return $center;
        }

        $cooperative = $farmer->cooperative;
        $centerName = trim((string) ($cooperative?->name ?: $legacyMccId ?: $cooperative?->location));
        $centerLocation = $cooperative?->location ?: ($legacyMccId ?: null);

        if ($centerName === '') {
            return null;
        }

        return MilkCollectionCenter::query()->firstOrCreate(
            [
                'name' => $centerName,
                'location' => $centerLocation,
            ],
            [
                'contact_number' => null,
                'created_by' => $actor->creatorId(),
            ],
        );
    }
}
