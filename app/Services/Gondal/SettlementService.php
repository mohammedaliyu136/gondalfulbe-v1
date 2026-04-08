<?php

namespace App\Services\Gondal;

use App\Models\Gondal\DeductionRun;
use App\Models\Gondal\Obligation;
use App\Models\Gondal\Payment;
use App\Models\Gondal\PaymentBatch;
use App\Models\Gondal\Payout;
use App\Models\Gondal\SettlementRun;
use App\Models\ProductService;
use App\Models\Project;
use App\Models\User;
use App\Models\Vender;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\MilkCollection\Models\MilkCollection;

class SettlementService
{
    public function __construct(
        protected BusinessRuleService $businessRuleService,
        protected LedgerService $ledgerService,
        protected ProgramScopeService $programScopeService,
    ) {}

    public function runFarmerSettlement(array $payload, User $actor): SettlementRun
    {
        $farmer = Vender::query()->findOrFail($payload['farmer_id']);
        $periodStart = Carbon::parse($payload['period_start'])->startOfDay();
        $periodEnd = Carbon::parse($payload['period_end'])->endOfDay();
        $projectId = $this->programScopeService->resolveFarmerProjectId($farmer, $actor);
        $project = $projectId ? Project::query()->find($projectId) : null;
        $settlementDefaults = $this->businessRuleService->settlementDefaults($projectId);
        $maxDeductionPercent = (float) ($payload['max_deduction_percent'] ?? $settlementDefaults['max_deduction_percent']);
        $payoutFloorAmount = (float) ($payload['payout_floor_amount'] ?? $settlementDefaults['payout_floor_amount']);

        \Illuminate\Support\Facades\Log::info("Settlement Debug: Payload Max %: " . ($payload['max_deduction_percent'] ?? 'NULL'));
        \Illuminate\Support\Facades\Log::info("Settlement Debug: Resolved Max %: " . $maxDeductionPercent);

        $collections = MilkCollection::query()
            ->where('farmer_id', $farmer->id)
            ->whereDate('collection_date', '>=', $periodStart->toDateString())
            ->whereDate('collection_date', '<=', $periodEnd->toDateString())
            ->where('quality_grade', '!=', 'C')
            ->orderBy('collection_date');

        if ($projectId) {
            $collections->where('project_id', $projectId);
        } else {
            $collections->whereNull('project_id');
        }

        $collections = $collections->get();

        if ($collections->isEmpty()) {
            throw ValidationException::withMessages([
                'farmer_id' => __('No eligible milk collections were found for the selected farmer and period.'),
            ]);
        }

        return DB::transaction(function () use (
            $actor,
            $collections,
            $farmer,
            $maxDeductionPercent,
            $payoutFloorAmount,
            $periodEnd,
            $periodStart,
            $project,
            $projectId
        ): SettlementRun {
            $grossMilkValue = 0.0;

            foreach ($collections as $collection) {
                $entry = $this->ledgerService->postMilkCollectionValue($collection, null, $actor, $project);
                $grossMilkValue += (float) $entry->lines->where('direction', 'credit')->sum('amount');
            }

            $grossMilkValue = round($grossMilkValue, 2);
            $deductionCapAmount = round($grossMilkValue * ($maxDeductionPercent / 100), 2);
            $availableForDeductions = max($grossMilkValue - $payoutFloorAmount, 0);
            $deductionBudget = min($deductionCapAmount, $availableForDeductions);

            \Illuminate\Support\Facades\Log::info("Settlement Debug: Gross Value: " . $grossMilkValue);
            \Illuminate\Support\Facades\Log::info("Settlement Debug: Deduction Budget: " . $deductionBudget);

            $settlementRun = SettlementRun::query()->create([
                'reference' => 'SET-'.now()->format('YmdHis').'-'.$farmer->id,
                'farmer_id' => $farmer->id,
                'project_id' => $projectId,
                'period_start' => $periodStart->toDateString(),
                'period_end' => $periodEnd->toDateString(),
                'gross_milk_value' => $grossMilkValue,
                'total_deductions' => 0,
                'net_payout' => $grossMilkValue,
                'status' => 'processing',
                'created_by' => $actor->id,
                'meta' => [
                    'max_deduction_percent' => $maxDeductionPercent,
                    'payout_floor_amount' => $payoutFloorAmount,
                    'collection_count' => $collections->count(),
                ],
            ]);

            $deductionRun = DeductionRun::query()->create([
                'settlement_run_id' => $settlementRun->id,
                'farmer_id' => $farmer->id,
                'period_start' => $periodStart->toDateString(),
                'period_end' => $periodEnd->toDateString(),
                'gross_amount' => $grossMilkValue,
                'deduction_cap_amount' => $deductionCapAmount,
                'payout_floor_amount' => $payoutFloorAmount,
                'total_deducted_amount' => 0,
                'net_payout_amount' => $grossMilkValue,
                'status' => 'processing',
                'created_by' => $actor->id,
            ]);

            $appliedDeductions = $this->applyObligationDeductions(
                $deductionRun,
                $settlementRun,
                $farmer,
                $deductionBudget,
                $actor
            );

            $netPayout = round($grossMilkValue - $appliedDeductions, 2);

            $batch = PaymentBatch::query()->create([
                'name' => __('Farmer Settlement - :farmer - :date', [
                    'farmer' => $farmer->name,
                    'date' => $periodEnd->toDateString(),
                ]),
                'payee_type' => 'farmer',
                'project_id' => $projectId,
                'period_start' => $periodStart->toDateString(),
                'period_end' => $periodEnd->toDateString(),
                'status' => 'approved',
                'total_amount' => $netPayout,
            ]);

            $payment = Payment::query()->create([
                'batch_id' => $batch->id,
                'farmer_id' => $farmer->id,
                'project_id' => $projectId,
                'amount' => $netPayout,
                'status' => 'pending',
            ]);

            $payout = Payout::query()->create([
                'settlement_run_id' => $settlementRun->id,
                'farmer_id' => $farmer->id,
                'payment_id' => $payment->id,
                'amount' => $netPayout,
                'status' => 'scheduled',
                'scheduled_at' => now(),
            ]);

            $this->ledgerService->postPayout($settlementRun, $netPayout, $actor);

            $deductionRun->update([
                'total_deducted_amount' => $appliedDeductions,
                'net_payout_amount' => $netPayout,
                'status' => 'completed',
            ]);

            $settlementRun->update([
                'payment_batch_id' => $batch->id,
                'total_deductions' => $appliedDeductions,
                'net_payout' => $netPayout,
                'status' => 'completed',
            ]);

            app(\App\Services\Gondal\GondalNotificationService::class)->queueNotification(
                'payment_settled',
                'farmer',
                $farmer->id,
                'sms',
                "Your settlement for {$periodEnd->toDateString()} has been processed. Net payout: {$netPayout}. Total deductions: {$appliedDeductions}.",
                $settlementRun->reference . '-PAYOUT'
            );

            return $settlementRun->fresh(['deductionRuns.allocations.obligation', 'payouts.payment', 'paymentBatch']);
        });
    }

    protected function applyObligationDeductions(
        DeductionRun $deductionRun,
        SettlementRun $settlementRun,
        Vender $farmer,
        float $deductionBudget,
        User $actor
    ): float {
        $remainingBudget = $deductionBudget;
        $totalApplied = 0.0;
        $deductionPriorityRule = $this->businessRuleService->settlementDeductionPriority($settlementRun->project_id);

        $obligations = Obligation::query()
            ->where('farmer_id', $farmer->id)
            ->whereIn('status', ['open', 'partial']);

        if ($settlementRun->project_id) {
            $obligations->where('project_id', $settlementRun->project_id);
        } else {
            $obligations->whereNull('project_id');
        }

        $obligations = $obligations
            ->orderBy('priority')
            ->orderBy('due_date')
            ->get();
        $obligations = $this->sortObligationsForSettlement($obligations, $deductionPriorityRule);

        foreach ($obligations as $obligation) {
            if ($remainingBudget <= 0) {
                break;
            }

            $obligationCap = $obligation->max_deduction_percent
                ? round($settlementRun->gross_milk_value * ((float) $obligation->max_deduction_percent / 100), 2)
                : $remainingBudget;
            $permittedAmount = min($remainingBudget, $obligationCap, (float) $obligation->outstanding_amount);

            if ($permittedAmount <= 0) {
                continue;
            }

            $deductionRun->allocations()->create([
                'obligation_id' => $obligation->id,
                'amount' => $permittedAmount,
                'priority' => $obligation->priority,
                'meta' => [
                    'obligation_reference' => $obligation->reference,
                    'deduction_category' => $this->resolveDeductionCategory($obligation),
                    'category_order' => $this->resolveDeductionCategoryOrder($obligation, $deductionPriorityRule['order']),
                ],
            ]);

            $obligation->update([
                'outstanding_amount' => round((float) $obligation->outstanding_amount - $permittedAmount, 2),
                'recovered_amount' => round((float) $obligation->recovered_amount + $permittedAmount, 2),
                'status' => round((float) $obligation->outstanding_amount - $permittedAmount, 2) <= 0 ? 'settled' : 'partial',
            ]);

            $this->ledgerService->postDeduction($settlementRun, $obligation, $permittedAmount, $actor);

            app(\App\Services\Gondal\GondalNotificationService::class)->queueNotification(
                'deduction_applied',
                'farmer',
                $farmer->id,
                'sms',
                "A deduction of {$permittedAmount} was applied to your obligation {$obligation->reference}.",
                "DED-" . $settlementRun->id . "-" . $obligation->id
            );

            $remainingBudget = round($remainingBudget - $permittedAmount, 2);
            $totalApplied = round($totalApplied + $permittedAmount, 2);
        }

        return $totalApplied;
    }

    protected function sortObligationsForSettlement(EloquentCollection $obligations, array $deductionPriorityRule): EloquentCollection
    {
        $categoryOrder = $deductionPriorityRule['order'] ?? [];

        return $obligations->sort(function (Obligation $left, Obligation $right) use ($categoryOrder): int {
            $leftCategoryOrder = $this->resolveDeductionCategoryOrder($left, $categoryOrder);
            $rightCategoryOrder = $this->resolveDeductionCategoryOrder($right, $categoryOrder);

            if ($leftCategoryOrder !== $rightCategoryOrder) {
                return $leftCategoryOrder <=> $rightCategoryOrder;
            }

            $leftDueDate = $left->due_date?->toDateString() ?? '9999-12-31';
            $rightDueDate = $right->due_date?->toDateString() ?? '9999-12-31';
            if ($leftDueDate !== $rightDueDate) {
                return $leftDueDate <=> $rightDueDate;
            }

            $leftCreatedAt = optional($left->created_at)?->format('Y-m-d H:i:s.u') ?? '9999-12-31 23:59:59.999999';
            $rightCreatedAt = optional($right->created_at)?->format('Y-m-d H:i:s.u') ?? '9999-12-31 23:59:59.999999';
            if ($leftCreatedAt !== $rightCreatedAt) {
                return $leftCreatedAt <=> $rightCreatedAt;
            }

            return $left->id <=> $right->id;
        })->values();
    }

    protected function resolveDeductionCategoryOrder(Obligation $obligation, array $categoryOrder): int
    {
        $category = $this->resolveDeductionCategory($obligation);
        $position = array_search($category, $categoryOrder, true);

        return $position === false ? count($categoryOrder) : $position;
    }

    protected function resolveDeductionCategory(Obligation $obligation): string
    {
        $metaCategory = Str::of((string) data_get($obligation->meta, 'deduction_category'))
            ->trim()
            ->lower()
            ->value();
        if ($metaCategory !== '') {
            return $metaCategory;
        }

        $sourceType = ltrim((string) ($obligation->source_type ?? ''), '\\');

        return match ($sourceType) {
            'loan', 'Loan', \App\Models\Loan::class => 'loan',
            \App\Models\Gondal\InventoryCredit::class => 'feed_input_credit',
            \App\Models\Gondal\GondalOrder::class => 'marketplace_order',
            ProductService::class, 'service_charge', 'service' => 'service_charge',
            'manual_adjustment', 'manual' => 'manual_adjustment',
            default => $this->inferDeductionCategoryFromText($sourceType, $obligation),
        };
    }

    protected function inferDeductionCategoryFromText(string $sourceType, Obligation $obligation): string
    {
        $signals = Str::lower(implode(' ', array_filter([
            $sourceType,
            (string) ($obligation->reference ?? ''),
            (string) data_get($obligation->meta, 'label'),
            (string) data_get($obligation->meta, 'reason'),
        ])));

        return match (true) {
            Str::contains($signals, 'loan') => 'loan',
            Str::contains($signals, ['feed', 'input', 'inventory', 'credit']) => 'feed_input_credit',
            Str::contains($signals, ['service', 'extension', 'vet', 'veterinary']) => 'service_charge',
            Str::contains($signals, ['marketplace', 'order']) => 'marketplace_order',
            Str::contains($signals, ['manual', 'adjustment']) => 'manual_adjustment',
            default => 'other',
        };
    }
}
