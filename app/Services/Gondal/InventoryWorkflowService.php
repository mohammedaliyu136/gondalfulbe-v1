<?php

namespace App\Services\Gondal;

use App\Models\Gondal\AgentCashLiability;
use App\Models\Gondal\AgentInventoryAdjustment;
use App\Models\Gondal\AgentProfile;
use App\Models\Gondal\AgentRemittance;
use App\Models\Gondal\InventoryCredit;
use App\Models\Gondal\InventoryItem;
use App\Models\Gondal\InventoryReconciliation;
use App\Models\Gondal\InventorySale;
use App\Models\Gondal\OneStopShopStock;
use App\Models\Gondal\StockIssue;
use App\Models\Gondal\WarehouseStock;
use App\Models\User;
use App\Models\Vender;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class InventoryWorkflowService
{
    public function __construct(
        protected BusinessRuleService $businessRuleService,
        protected LedgerService $ledgerService,
        protected ProgramScopeService $programScopeService,
    ) {}

    public function availableAgentItemStock(int $agentProfileId, int $inventoryItemId, ?Carbon $asOf = null): float
    {
        $issueQuery = StockIssue::query()
            ->where('agent_profile_id', $agentProfileId)
            ->where('inventory_item_id', $inventoryItemId)
            ->where('issue_stage', 'oss_to_agent');
        $salesQuery = InventorySale::query()
            ->where('agent_profile_id', $agentProfileId)
            ->where('inventory_item_id', $inventoryItemId)
            ->whereNull('cancelled_at');
        $adjustmentQuery = AgentInventoryAdjustment::query()
            ->where('agent_profile_id', $agentProfileId)
            ->where('inventory_item_id', $inventoryItemId);

        if ($asOf) {
            $issueQuery->whereDate('issued_on', '<=', $asOf->toDateString());
            $salesQuery->whereDate('sold_on', '<=', $asOf->toDateString());
            $adjustmentQuery->whereDate('effective_on', '<=', $asOf->toDateString());
        }

        return max(
            (float) $issueQuery->sum('quantity_issued')
            - (float) $salesQuery->sum('quantity')
            + (float) $adjustmentQuery->sum('quantity_delta'),
            0
        );
    }

    public function createInventorySale(array $payload): InventorySale
    {
        if ($payload['vender_id'] ?? null) {
            $vender = Vender::query()->find($payload['vender_id']);
            $payload['customer_name'] = $vender ? $vender->name : ($payload['customer_name'] ?? null);
        }

        return DB::transaction(function () use ($payload): InventorySale {
            $item = InventoryItem::query()->findOrFail($payload['inventory_item_id']);
            $agentProfile = ! empty($payload['agent_profile_id'])
                ? AgentProfile::query()->findOrFail($payload['agent_profile_id'])
                : null;
            $vender = ! empty($payload['vender_id'])
                ? Vender::query()->findOrFail($payload['vender_id'])
                : null;

            $this->assertFarmerWithinAgentScope($agentProfile, $vender);
            $projectId = $vender
                ? $this->programScopeService->resolveFarmerProjectId($vender, null, $agentProfile)
                : $this->programScopeService->resolveAgentProjectId($agentProfile);
            $creditPaymentMethods = $this->businessRuleService->inventoryCreditPaymentMethods($projectId);
            $creditObligationDefaults = $this->businessRuleService->inventoryCreditObligationDefaults($projectId);

            $availableStock = $agentProfile
                ? $this->availableAgentItemStock($agentProfile->id, $item->id, Carbon::parse($payload['sold_on'])->endOfDay())
                : (float) $item->stock_qty;

            if ((float) $payload['quantity'] > $availableStock) {
                throw ValidationException::withMessages([
                    'quantity' => [$agentProfile
                        ? __('Quantity exceeds the selected agent sub-store balance.')
                        : __('Quantity exceeds current stock balance.')],
                ]);
            }

            if (in_array(($payload['payment_method'] ?? null), $creditPaymentMethods, true) && $agentProfile && ! $agentProfile->credit_sales_enabled) {
                throw ValidationException::withMessages([
                    'payment_method' => [__('Credit sales are disabled for the selected agent.')],
                ]);
            }

            $totalAmount = round((float) $payload['quantity'] * (float) $payload['unit_price'], 2);
            if (in_array(($payload['payment_method'] ?? null), $creditPaymentMethods, true) && $agentProfile) {
                $currentExposure = (float) InventoryCredit::query()
                    ->where('agent_profile_id', $agentProfile->id)
                    ->whereIn('status', ['open', 'partial'])
                    ->sum(DB::raw('CASE WHEN outstanding_amount > 0 THEN outstanding_amount ELSE amount END'));

                if ((float) $agentProfile->credit_limit > 0 && ($currentExposure + $totalAmount) > (float) $agentProfile->credit_limit) {
                    throw ValidationException::withMessages([
                        'agent_profile_id' => [__('This sale exceeds the selected agent credit limit.')],
                    ]);
                }
            }

            if (isset($payload['batch_id'])) {
                $batch = InventoryBatch::query()->find($payload['batch_id']);
                if ($batch && $batch->isExpired()) {
                    throw ValidationException::withMessages([
                        'batch_id' => [__('The selected batch has expired and cannot be sold.')],
                    ]);
                }
            }

            $sale = InventorySale::query()->create([
                'inventory_item_id' => $payload['inventory_item_id'],
                'batch_id' => $payload['batch_id'] ?? null,
                'agent_profile_id' => $payload['agent_profile_id'] ?? null,
                'order_id' => $payload['order_id'] ?? null,
                'project_id' => $projectId,
                'extension_visit_id' => $payload['extension_visit_id'] ?? null,
                'vender_id' => $payload['vender_id'] ?? null,
                'quantity' => $payload['quantity'],
                'unit_price' => $payload['unit_price'],
                'total_amount' => $totalAmount,
                'payment_method' => $payload['payment_method'],
                'credit_allowed_snapshot' => $agentProfile?->credit_sales_enabled ?? false,
                'sold_on' => $payload['sold_on'],
                'customer_name' => $payload['customer_name'] ?? null,
            ]);

            if (! $agentProfile) {
                $item->update(['stock_qty' => (float) $item->stock_qty - (float) $payload['quantity']]);
            }

            // Post to financial ledger
            $entry = $this->ledgerService->postInventorySale($sale);
            $sale->update(['journal_entry_id' => $entry->id]);

            $creditAmount = max($totalAmount - (float) ($payload['cash_amount'] ?? 0), 0);
            $isCreditGenerating = in_array(($payload['payment_method'] ?? null), $creditPaymentMethods, true) || ($payload['payment_method'] ?? null) === 'Mixed';

            if ($isCreditGenerating && $creditAmount > 0) {
                $credit = InventoryCredit::query()->create([
                    'inventory_item_id' => $item->id,
                    'agent_profile_id' => $payload['agent_profile_id'] ?? null,
                    'order_id' => $payload['order_id'] ?? null,
                    'project_id' => $projectId,
                    'inventory_sale_id' => $sale->id,
                    'vender_id' => $payload['vender_id'] ?? null,
                    'customer_name' => $payload['customer_name'] ?? 'Unknown Customer',
                    'amount' => $creditAmount,
                    'outstanding_amount' => $creditAmount,
                    'status' => 'open',
                    'credit_date' => $payload['sold_on'],
                    'due_date' => $payload['due_date'] ?? Carbon::parse($payload['sold_on'])->addDays($creditObligationDefaults['due_days'])->toDateString(),
                ]);

                if (! empty($payload['vender_id'])) {
                    $this->ledgerService->createObligation([
                        'farmer_id' => $payload['vender_id'],
                        'agent_profile_id' => $payload['agent_profile_id'] ?? null,
                        'inventory_credit_id' => $credit->id,
                        'project_id' => $projectId,
                        'source_type' => InventoryCredit::class,
                        'source_id' => $credit->id,
                        'principal_amount' => $creditAmount,
                        'priority' => $creditObligationDefaults['priority'],
                        'max_deduction_percent' => $creditObligationDefaults['max_deduction_percent'],
                        'payout_floor_amount' => $creditObligationDefaults['payout_floor_amount'],
                        'due_date' => $payload['due_date'] ?? Carbon::parse($payload['sold_on'])->addDays($creditObligationDefaults['due_days'])->toDateString(),
                        'meta' => [
                            'inventory_sale_id' => $sale->id,
                            'payment_method' => $payload['payment_method'],
                        ],
                    ]);
                }
            }

            return $sale;
        });
    }

    public function createWarehouseToOneStopShopIssue(array $payload, User $actor): StockIssue
    {
        $warehouseStock = WarehouseStock::query()
            ->where('warehouse_id', $payload['warehouse_id'])
            ->where('inventory_item_id', $payload['inventory_item_id'])
            ->first();

        if (! $warehouseStock) {
            throw ValidationException::withMessages([
                'warehouse_id' => [__('The selected warehouse does not hold this product.')],
            ]);
        }

        if ((float) $payload['quantity_issued'] > (float) $warehouseStock->quantity) {
            throw ValidationException::withMessages([
                'quantity_issued' => [__('Issued quantity exceeds available warehouse stock.')],
            ]);
        }

        return DB::transaction(function () use ($payload, $actor, $warehouseStock): StockIssue {
            $issue = StockIssue::query()->create([
                'agent_profile_id' => null,
                'warehouse_id' => $payload['warehouse_id'],
                'one_stop_shop_id' => $payload['one_stop_shop_id'],
                'issue_stage' => 'warehouse_to_oss',
                'inventory_item_id' => $payload['inventory_item_id'],
                'issued_by' => $actor->id,
                'issue_reference' => $this->generateInventoryReference('WHS'),
                'batch_reference' => $payload['batch_reference'] ?? null,
                'quantity_issued' => $payload['quantity_issued'],
                'unit_cost' => $payload['unit_cost'],
                'issued_on' => $payload['issued_on'],
                'notes' => $payload['notes'] ?? null,
            ]);

            $warehouseStock->update([
                'quantity' => (float) $warehouseStock->quantity - (float) $payload['quantity_issued'],
            ]);

            $oneStopShopStock = OneStopShopStock::query()->firstOrNew([
                'one_stop_shop_id' => $payload['one_stop_shop_id'],
                'inventory_item_id' => $payload['inventory_item_id'],
            ]);
            $oneStopShopStock->created_by = $actor->creatorId();
            $oneStopShopStock->quantity = (float) ($oneStopShopStock->quantity ?? 0) + (float) $payload['quantity_issued'];
            $oneStopShopStock->reorder_level = (float) ($oneStopShopStock->reorder_level ?? 0);
            $oneStopShopStock->save();

            return $issue;
        });
    }

    public function createOneStopShopToAgentIssue(array $payload, User $actor): StockIssue
    {
        $agent = AgentProfile::query()->findOrFail($payload['agent_profile_id']);

        if ((int) ($agent->one_stop_shop_id ?? 0) !== (int) $payload['one_stop_shop_id']) {
            throw ValidationException::withMessages([
                'agent_profile_id' => [__('The selected agent is not assigned to this one-stop shop.')],
            ]);
        }

        $oneStopShopStock = OneStopShopStock::query()
            ->where('one_stop_shop_id', $payload['one_stop_shop_id'])
            ->where('inventory_item_id', $payload['inventory_item_id'])
            ->first();

        if (! $oneStopShopStock) {
            throw ValidationException::withMessages([
                'one_stop_shop_id' => [__('The selected one-stop shop does not hold this product.')],
            ]);
        }

        if ((float) $payload['quantity_issued'] > (float) $oneStopShopStock->quantity) {
            throw ValidationException::withMessages([
                'quantity_issued' => [__('Issued quantity exceeds available one-stop shop stock.')],
            ]);
        }

        return DB::transaction(function () use ($payload, $actor, $oneStopShopStock): StockIssue {
            $issue = StockIssue::query()->create([
                'agent_profile_id' => $payload['agent_profile_id'],
                'warehouse_id' => null,
                'one_stop_shop_id' => $payload['one_stop_shop_id'],
                'issue_stage' => 'oss_to_agent',
                'inventory_item_id' => $payload['inventory_item_id'],
                'issued_by' => $actor->id,
                'issue_reference' => $this->generateInventoryReference('OSS'),
                'batch_reference' => $payload['batch_reference'] ?? null,
                'quantity_issued' => $payload['quantity_issued'],
                'unit_cost' => $payload['unit_cost'],
                'issued_on' => $payload['issued_on'],
                'notes' => $payload['notes'] ?? null,
            ]);

            $oneStopShopStock->update([
                'quantity' => (float) $oneStopShopStock->quantity - (float) $payload['quantity_issued'],
            ]);

            if ((float) $oneStopShopStock->reorder_level > 0 && (float) $oneStopShopStock->quantity <= (float) $oneStopShopStock->reorder_level) {
                app(\App\Services\Gondal\GondalNotificationService::class)->queueNotification(
                    'low_stock_alert',
                    'agent',
                    $payload['agent_profile_id'],
                    'sms',
                    "Low stock alert: The stock for Item #{$payload['inventory_item_id']} has fallen below its reorder level of {$oneStopShopStock->reorder_level}.",
                    $issue->issue_reference . '-LOWSTOCK'
                );
            }

            return $issue;
        });
    }

    public function recordRemittance(array $payload, User $actor): AgentRemittance
    {
        $agent = AgentProfile::query()->findOrFail($payload['agent_profile_id']);

        return AgentRemittance::query()->create([
            'agent_profile_id' => $payload['agent_profile_id'],
            'one_stop_shop_id' => $agent->one_stop_shop_id,
            'received_by' => $actor->id,
            'reconciliation_mode' => $payload['reconciliation_mode'],
            'reference' => $this->generateInventoryReference('RMT'),
            'amount' => $payload['amount'],
            'payment_method' => $payload['payment_method'],
            'period_start' => $payload['period_start'] ?? null,
            'period_end' => $payload['period_end'] ?? null,
            'remitted_at' => Carbon::parse($payload['remitted_at'])->endOfDay(),
            'notes' => $payload['notes'] ?? null,
        ]);
    }

    public function createReconciliation(array $payload, User $actor): InventoryReconciliation
    {
        $agent = AgentProfile::query()->findOrFail($payload['agent_profile_id']);
        $periodStart = Carbon::parse($payload['period_start'])->startOfDay();
        $periodEnd = Carbon::parse($payload['period_end'])->endOfDay();

        $issueQuery = StockIssue::query()
            ->where('agent_profile_id', $payload['agent_profile_id'])
            ->where('inventory_item_id', $payload['inventory_item_id'])
            ->where('issue_stage', 'oss_to_agent');
        $saleQuery = InventorySale::query()
            ->where('agent_profile_id', $payload['agent_profile_id'])
            ->where('inventory_item_id', $payload['inventory_item_id'])
            ->whereNull('cancelled_at');
        $creditQuery = InventoryCredit::query()
            ->where('agent_profile_id', $payload['agent_profile_id'])
            ->where('inventory_item_id', $payload['inventory_item_id'])
            ->whereNull('cancelled_at');
        $remittanceQuery = AgentRemittance::query()
            ->where('agent_profile_id', $payload['agent_profile_id']);

        $openingStockQty = $this->availableAgentItemStock(
            (int) $payload['agent_profile_id'],
            (int) $payload['inventory_item_id'],
            $periodStart->copy()->subDay()->endOfDay(),
        );

        $issuedStockQty = (float) (clone $issueQuery)
            ->whereBetween('issued_on', [$periodStart->toDateString(), $periodEnd->toDateString()])
            ->sum('quantity_issued');
        $periodSales = (clone $saleQuery)
            ->whereBetween('sold_on', [$periodStart->toDateString(), $periodEnd->toDateString()])
            ->get();
        $soldStockQty = (float) $periodSales->sum('quantity');
        $cashSalesAmount = (float) $periodSales->where('payment_method', 'Cash')->sum('total_amount');
        $transferSalesAmount = (float) $periodSales->where('payment_method', 'Transfer')->sum('total_amount');
        $creditSalesAmount = (float) $periodSales
            ->filter(fn (InventorySale $sale) => in_array($sale->payment_method, ['Credit', 'Milk Collection Balance'], true))
            ->sum('total_amount');
        $expectedStockQty = $this->availableAgentItemStock(
            (int) $payload['agent_profile_id'],
            (int) $payload['inventory_item_id'],
            $periodEnd,
        );
        $countedStockQty = (float) $payload['counted_stock_qty'];
        $stockVarianceQty = $countedStockQty - $expectedStockQty;
        $creditCollectionsAmount = 0.0;
        $expectedCashAmount = $cashSalesAmount + $transferSalesAmount + $creditCollectionsAmount;
        $remittedCashAmount = (float) (clone $remittanceQuery)
            ->whereBetween('remitted_at', [$periodStart, $periodEnd])
            ->sum('amount');
        $cashVarianceAmount = $remittedCashAmount - $expectedCashAmount;
        $outstandingCreditAmount = (float) (clone $creditQuery)
            ->whereIn('status', ['open', 'partial'])
            ->selectRaw('COALESCE(SUM(CASE WHEN outstanding_amount > 0 THEN outstanding_amount ELSE amount END), 0) as balance')
            ->value('balance');

        $status = 'submitted';
        if (abs($stockVarianceQty) > 0.0001 || abs($cashVarianceAmount) > 0.0001) {
            $status = 'under_review';
        }

        return InventoryReconciliation::query()->create([
            'agent_profile_id' => $payload['agent_profile_id'],
            'one_stop_shop_id' => $agent->one_stop_shop_id,
            'inventory_item_id' => $payload['inventory_item_id'],
            'submitted_by' => $actor->id,
            'reconciliation_mode' => $payload['reconciliation_mode'],
            'reference' => $this->generateInventoryReference('REC'),
            'period_start' => $periodStart->toDateString(),
            'period_end' => $periodEnd->toDateString(),
            'opening_stock_qty' => $openingStockQty,
            'issued_stock_qty' => $issuedStockQty,
            'sold_stock_qty' => $soldStockQty,
            'returned_stock_qty' => 0,
            'damaged_stock_qty' => 0,
            'expected_stock_qty' => $expectedStockQty,
            'counted_stock_qty' => $countedStockQty,
            'stock_variance_qty' => $stockVarianceQty,
            'cash_sales_amount' => $cashSalesAmount,
            'transfer_sales_amount' => $transferSalesAmount,
            'credit_sales_amount' => $creditSalesAmount,
            'credit_collections_amount' => $creditCollectionsAmount,
            'expected_cash_amount' => $expectedCashAmount,
            'remitted_cash_amount' => $remittedCashAmount,
            'cash_variance_amount' => $cashVarianceAmount,
            'outstanding_credit_amount' => $outstandingCreditAmount,
            'status' => $status,
            'agent_notes' => $payload['agent_notes'] ?? null,
        ]);
    }

    public function resolveReconciliation(InventoryReconciliation $reconciliation, array $payload, User $actor): InventoryReconciliation
    {
        $status = match ($payload['action']) {
            'approve' => 'approved',
            'approve_with_variance' => 'approved_with_variance',
            'escalate' => 'escalated',
            'request_recount' => 'recount_requested',
        };

        $existingNotes = trim((string) $reconciliation->review_notes);
        $newNotes = trim((string) ($payload['review_notes'] ?? ''));
        $actionLabel = Str::headline(str_replace('_', ' ', $payload['action']));

        DB::transaction(function () use ($reconciliation, $payload, $actor, $status, $existingNotes, $newNotes, $actionLabel): void {
            $reconciliation->update([
                'reviewed_by' => $actor->id,
                'status' => $status,
                'review_notes' => trim($existingNotes.($existingNotes !== '' && $newNotes !== '' ? "\n\n" : '').($newNotes !== '' ? '['.$actionLabel.'] '.$newNotes : '['.$actionLabel.']')),
            ]);

            if (in_array($payload['action'], ['approve', 'approve_with_variance'], true)
                && abs((float) $reconciliation->stock_variance_qty) > 0.0001
                && ! AgentInventoryAdjustment::query()->where('reconciliation_id', $reconciliation->id)->exists()) {
                AgentInventoryAdjustment::query()->create([
                    'agent_profile_id' => $reconciliation->agent_profile_id,
                    'inventory_item_id' => $reconciliation->inventory_item_id,
                    'reconciliation_id' => $reconciliation->id,
                    'created_by' => $actor->id,
                    'reference' => $this->generateInventoryReference('ADJ'),
                    'quantity_delta' => (float) $reconciliation->stock_variance_qty,
                    'reason' => 'reconciliation_variance',
                    'effective_on' => $reconciliation->period_end,
                    'notes' => __('Applied from reconciliation :reference', ['reference' => $reconciliation->reference]),
                ]);
            }

            if ((float) $reconciliation->cash_variance_amount < -0.0001
                && in_array($payload['action'], ['approve_with_variance', 'escalate'], true)
                && ! AgentCashLiability::query()->where('reconciliation_id', $reconciliation->id)->exists()) {
                AgentCashLiability::query()->create([
                    'agent_profile_id' => $reconciliation->agent_profile_id,
                    'reconciliation_id' => $reconciliation->id,
                    'created_by' => $actor->id,
                    'reference' => $this->generateInventoryReference('LIA'),
                    'amount' => abs((float) $reconciliation->cash_variance_amount),
                    'liability_type' => 'cash_shortage',
                    'status' => 'open',
                    'due_date' => optional($reconciliation->period_end)?->copy()?->addDays(7),
                    'notes' => __('Auto-created from reconciliation shortage :reference', ['reference' => $reconciliation->reference]),
                ]);
            }
        });

        return $reconciliation->fresh();
    }

    protected function assertFarmerWithinAgentScope(?AgentProfile $agentProfile, ?Vender $farmer): void
    {
        if (! $agentProfile || ! $farmer) {
            return;
        }

        $communities = collect($this->normalizeCommunities($agentProfile->assigned_communities ?? []))
            ->map(fn (string $community) => Str::lower($community));

        // If no communities are assigned, we treat the agent as 'Global' (allowed to transact with anyone).
        // This prevents blocking transactions for newly created agents who haven't been assigned specific regions.
        if ($communities->isEmpty()) {
            return;
        }

        $farmerCommunity = $farmer->communityRecord?->name ?: (string) $farmer->community;

        if (! $communities->contains(Str::lower($farmerCommunity))) {
            throw ValidationException::withMessages([
                'farmer_id' => [__('This farmer is outside the selected agent community assignment.')],
            ]);
        }
    }

    protected function normalizeCommunities(array $communities): array
    {
        return collect($communities)
            ->map(fn ($community) => trim((string) $community))
            ->filter()
            ->unique(fn (string $community) => Str::lower($community))
            ->values()
            ->all();
    }

    protected function generateInventoryReference(string $prefix): string
    {
        return $prefix.'-'.now()->format('YmdHis').'-'.strtoupper(Str::random(4));
    }
}
