<?php

namespace App\Services\Gondal;

use App\Models\Gondal\FinanceAccount;
use App\Models\Gondal\GondalOrder;
use App\Models\Gondal\InventorySale;
use App\Models\Gondal\JournalEntry;
use App\Models\Gondal\JournalLine;
use App\Models\Gondal\Obligation;
use App\Models\Gondal\SettlementRun;
use App\Models\Project;
use App\Models\User;
use App\Models\Vender;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\MilkCollection\Models\MilkCollection;

class LedgerService
{
    public const ENTRY_TYPE_MILK_ACCRUAL = 'milk_accrual';
    public const ENTRY_TYPE_DEDUCTION_RECOVERY = 'deduction_recovery';
    public const ENTRY_TYPE_PAYOUT = 'payout';
    public const ENTRY_TYPE_ORDER_FULFILLMENT = 'order_fulfillment';
    public const ENTRY_TYPE_INVENTORY_SALE = 'inventory_sale';
    public const ENTRY_TYPE_REVERSAL = 'reversal';

    public function __construct(protected BusinessRuleService $businessRuleService) {}

    public function ensureDefaultAccounts(?int $createdBy = null): array
    {
        return [
            'milk_expense' => $this->firstOrCreateAccount($createdBy, 'GL-MILK-EXP', 'Milk Procurement Expense', 'expense'),
            'farmer_payable' => $this->firstOrCreateAccount($createdBy, 'GL-FARMER-PAY', 'Farmer Milk Payable', 'liability'),
            'farmer_obligation' => $this->firstOrCreateAccount($createdBy, 'GL-FARMER-OBL', 'Farmer Recovery Receivable', 'asset'),
            'inventory_credit_revenue' => $this->firstOrCreateAccount($createdBy, 'GL-INV-CREDIT', 'Inventory Credit Recoveries', 'income'),
            'inventory_sales_revenue' => $this->firstOrCreateAccount($createdBy, 'GL-INV-SALES', 'Inventory Sales Revenue', 'income'),
            'sponsor_order_receivable' => $this->firstOrCreateAccount($createdBy, 'GL-SPONSOR-REC', 'Sponsor Order Receivable', 'asset'),
            'cash_clearing' => $this->firstOrCreateAccount($createdBy, 'GL-CASH-CLEAR', 'Cash Clearing', 'asset'),
        ];
    }

    public function postMilkCollectionValue(MilkCollection $collection, ?float $unitPrice = null, ?User $actor = null, ?Project $project = null): JournalEntry
    {
        $farmer = $collection->farmer ?: Vender::find($collection->farmer_id);
        $adminId = $this->resolveAdminIdForFarmer($farmer, $actor);
        $accounts = $this->ensureDefaultAccounts($adminId);
        $price = $unitPrice ?? $this->resolveMilkPrice($collection);
        $amount = round((float) $collection->quantity * $price, 2);
        $projectId = $project?->id ?? $collection->project_id;

        // If the milk has no financial value (e.g., zero quantity or zero-priced grade), 
        // we skip the ledger posting as it has no accounting impact.
        if ($amount <= 0) {
            return new JournalEntry();
        }

        if ($project && $collection->project_id && (int) $collection->project_id !== (int) $project->id) {
            throw new \InvalidArgumentException('Milk collection project does not match the posting project.');
        }

        return $this->postEntry([
            'entry_type' => self::ENTRY_TYPE_MILK_ACCRUAL,
            'entry_date' => optional($collection->collection_date)->toDateString() ?: now()->toDateString(),
            'reference_type' => MilkCollection::class,
            'reference_id' => $collection->id,
            'source_key' => 'milk_collection:'.$collection->id,
            'description' => __('Milk value posted for farmer #:farmer', ['farmer' => $collection->farmer_id]),
            'created_by' => $actor?->id,
            'posted_by' => $actor?->id,
            'meta' => [
                'price_per_liter' => $price,
                'quantity' => (float) $collection->quantity,
                'quality_grade' => $collection->quality_grade,
            ],
            'lines' => [
                [
                    'finance_account_id' => $accounts['milk_expense']->id,
                    'farmer_id' => $collection->farmer_id,
                    'project_id' => $projectId,
                    'direction' => 'debit',
                    'amount' => $amount,
                    'memo' => 'Milk procurement expense recognized',
                ],
                [
                    'finance_account_id' => $accounts['farmer_payable']->id,
                    'farmer_id' => $collection->farmer_id,
                    'project_id' => $projectId,
                    'direction' => 'credit',
                    'amount' => $amount,
                    'memo' => 'Milk payable accrued to farmer',
                ],
            ],
        ]);
    }

    public function createObligation(array $payload, ?User $actor = null): Obligation
    {
        $projectId = $payload['project_id'] ?? null;
        $inventoryCreditDefaults = $this->businessRuleService->inventoryCreditObligationDefaults($projectId);

        return Obligation::query()->create([
            'reference' => $payload['reference'] ?? $this->nextReference('OBL'),
            'farmer_id' => $payload['farmer_id'],
            'agent_profile_id' => $payload['agent_profile_id'] ?? null,
            'inventory_credit_id' => $payload['inventory_credit_id'] ?? null,
            'project_id' => $projectId,
            'source_type' => $payload['source_type'] ?? null,
            'source_id' => $payload['source_id'] ?? null,
            'principal_amount' => $payload['principal_amount'],
            'outstanding_amount' => $payload['outstanding_amount'] ?? $payload['principal_amount'],
            'recovered_amount' => $payload['recovered_amount'] ?? 0,
            'priority' => $payload['priority'] ?? $inventoryCreditDefaults['priority'],
            'max_deduction_percent' => $payload['max_deduction_percent'] ?? $inventoryCreditDefaults['max_deduction_percent'],
            'payout_floor_amount' => $payload['payout_floor_amount'] ?? $inventoryCreditDefaults['payout_floor_amount'],
            'due_date' => $payload['due_date'] ?? null,
            'status' => $payload['status'] ?? 'open',
            'meta' => $payload['meta'] ?? null,
            'created_by' => $actor?->id,
        ]);
    }

    public function postDeduction(SettlementRun $settlementRun, Obligation $obligation, float $amount, ?User $actor = null): JournalEntry
    {
        $farmer = Vender::find($obligation->farmer_id);
        $adminId = $this->resolveAdminIdForFarmer($farmer, $actor);
        $accounts = $this->ensureDefaultAccounts($adminId);

        if ($amount <= 0) {
            throw new \InvalidArgumentException('Deduction journal postings require a positive amount.');
        }

        if ((int) $settlementRun->farmer_id !== (int) $obligation->farmer_id) {
            throw new \InvalidArgumentException('Deduction journal postings require the settlement and obligation farmer to match.');
        }

        if ($settlementRun->project_id !== $obligation->project_id) {
            throw new \InvalidArgumentException('Deduction journal postings require the settlement and obligation project to match.');
        }

        return $this->postEntry([
            'entry_type' => self::ENTRY_TYPE_DEDUCTION_RECOVERY,
            'entry_date' => $settlementRun->period_end->toDateString(),
            'reference_type' => SettlementRun::class,
            'reference_id' => $settlementRun->id,
            'source_key' => 'settlement:'.$settlementRun->id.':deduction:'.$obligation->id,
            'description' => __('Deduction applied against obligation :reference', ['reference' => $obligation->reference]),
            'created_by' => $actor?->id,
            'posted_by' => $actor?->id,
            'meta' => [
                'settlement_reference' => $settlementRun->reference,
                'obligation_reference' => $obligation->reference,
            ],
            'lines' => [
                [
                    'finance_account_id' => $accounts['farmer_payable']->id,
                    'farmer_id' => $settlementRun->farmer_id,
                    'project_id' => $settlementRun->project_id,
                    'direction' => 'debit',
                    'amount' => $amount,
                    'memo' => 'Farmer payable reduced by deduction',
                ],
                [
                    'finance_account_id' => $accounts['farmer_obligation']->id,
                    'farmer_id' => $settlementRun->farmer_id,
                    'project_id' => $settlementRun->project_id,
                    'direction' => 'credit',
                    'amount' => $amount,
                    'memo' => 'Farmer obligation recovered',
                ],
            ],
        ]);
    }

    public function postPayout(SettlementRun $settlementRun, float $amount, ?User $actor = null): JournalEntry
    {
        $farmer = Vender::find($settlementRun->farmer_id);
        $adminId = $this->resolveAdminIdForFarmer($farmer, $actor);
        $accounts = $this->ensureDefaultAccounts($adminId);

        if ($amount <= 0) {
            throw new \InvalidArgumentException('Payout journal postings require a positive amount.');
        }

        return $this->postEntry([
            'entry_type' => self::ENTRY_TYPE_PAYOUT,
            'entry_date' => $settlementRun->period_end->toDateString(),
            'reference_type' => SettlementRun::class,
            'reference_id' => $settlementRun->id,
            'source_key' => 'settlement:'.$settlementRun->id.':payout',
            'description' => __('Net payout scheduled for settlement :reference', ['reference' => $settlementRun->reference]),
            'created_by' => $actor?->id,
            'posted_by' => $actor?->id,
            'meta' => [
                'settlement_reference' => $settlementRun->reference,
            ],
            'lines' => [
                [
                    'finance_account_id' => $accounts['farmer_payable']->id,
                    'farmer_id' => $settlementRun->farmer_id,
                    'project_id' => $settlementRun->project_id,
                    'direction' => 'debit',
                    'amount' => $amount,
                    'memo' => 'Farmer payable settled',
                ],
                [
                    'finance_account_id' => $accounts['cash_clearing']->id,
                    'farmer_id' => $settlementRun->farmer_id,
                    'project_id' => $settlementRun->project_id,
                    'direction' => 'credit',
                    'amount' => $amount,
                    'memo' => 'Cash clearing credited for payout',
                ],
            ],
        ]);
    }

    public function postInventorySale(InventorySale $sale, ?User $actor = null): JournalEntry
    {
        $farmer = $sale->vender ?: Vender::find($sale->vender_id);
        $adminId = $this->resolveAdminIdForFarmer($farmer, $actor);
        $accounts = $this->ensureDefaultAccounts($adminId);

        if ((float) $sale->total_amount <= 0) {
            return new JournalEntry();
        }

        $creditPaymentMethods = $this->businessRuleService->inventoryCreditPaymentMethods($sale->project_id);
        $isCredit = in_array($sale->payment_method, $creditPaymentMethods, true) || $sale->payment_method === 'Mixed';

        $debitAccount = $isCredit ? $accounts['farmer_obligation'] : $accounts['cash_clearing'];

        return $this->postEntry([
            'entry_type' => self::ENTRY_TYPE_INVENTORY_SALE,
            'entry_date' => Carbon::parse($sale->sold_on)->toDateString(),
            'reference_type' => InventorySale::class,
            'reference_id' => $sale->id,
            'source_key' => 'inventory_sale:'.$sale->id,
            'description' => __('Inventory sale recorded for farmer #:farmer', ['farmer' => $sale->vender_id]),
            'created_by' => $actor?->id,
            'posted_by' => $actor?->id,
            'meta' => [
                'inventory_item_id' => $sale->inventory_item_id,
                'quantity' => (float) $sale->quantity,
                'unit_price' => (float) $sale->unit_price,
            ],
            'lines' => [
                [
                    'finance_account_id' => $debitAccount->id,
                    'farmer_id' => $sale->vender_id,
                    'project_id' => $sale->project_id,
                    'direction' => 'debit',
                    'amount' => $sale->total_amount,
                    'memo' => 'Inventory sale receivable recognized',
                ],
                [
                    'finance_account_id' => $accounts['inventory_sales_revenue']->id,
                    'farmer_id' => $sale->vender_id,
                    'project_id' => $sale->project_id,
                    'direction' => 'credit',
                    'amount' => $sale->total_amount,
                    'memo' => 'Inventory sale revenue recognized',
                ],
            ],
        ]);
    }

    public function postOrderFulfillment(GondalOrder $order, ?User $actor = null): JournalEntry
    {
        $farmer = $order->farmer ?: Vender::find($order->farmer_id);
        $adminId = $this->resolveAdminIdForFarmer($farmer, $actor);
        $accounts = $this->ensureDefaultAccounts($adminId);

        if ((float) $order->total_amount <= 0) {
            throw new \InvalidArgumentException('Order fulfillment journal postings require a positive amount.');
        }

        if ($order->payment_mode === OrderWorkflowService::PAYMENT_MODE_MIXED) {
            $lines = [
                [
                    'finance_account_id' => $accounts['inventory_sales_revenue']->id,
                    'farmer_id' => $order->farmer_id,
                    'project_id' => $order->project_id,
                    'direction' => 'credit',
                    'amount' => $order->total_amount,
                    'memo' => 'Inventory order revenue recognized',
                ],
            ];
            
            if ($order->cash_amount > 0) {
                $lines[] = [
                    'finance_account_id' => $accounts['cash_clearing']->id,
                    'farmer_id' => $order->farmer_id,
                    'project_id' => $order->project_id,
                    'direction' => 'debit',
                    'amount' => $order->cash_amount,
                    'memo' => 'Cash clearing debit for mixed payment',
                ];
            }
            
            if ($order->outstanding_amount > 0) {
                $lines[] = [
                    'finance_account_id' => $accounts['farmer_obligation']->id,
                    'farmer_id' => $order->farmer_id,
                    'project_id' => $order->project_id,
                    'direction' => 'debit',
                    'amount' => $order->outstanding_amount,
                    'memo' => 'Farmer obligation debit for mixed payment',
                ];
            }
        } else {
            $debitAccount = match ($order->payment_mode) {
                OrderWorkflowService::PAYMENT_MODE_CASH => $accounts['cash_clearing'],
                OrderWorkflowService::PAYMENT_MODE_MILK_DEDUCTION => $accounts['farmer_obligation'],
                OrderWorkflowService::PAYMENT_MODE_SPONSOR_FUNDED => $accounts['sponsor_order_receivable'],
                default => throw new \InvalidArgumentException('Unsupported order payment mode.'),
            };
            $lines = [
                [
                    'finance_account_id' => $debitAccount->id,
                    'farmer_id' => $order->farmer_id,
                    'project_id' => $order->project_id,
                    'direction' => 'debit',
                    'amount' => $order->total_amount,
                    'memo' => 'Order receivable recognized',
                ],
                [
                    'finance_account_id' => $accounts['inventory_sales_revenue']->id,
                    'farmer_id' => $order->farmer_id,
                    'project_id' => $order->project_id,
                    'direction' => 'credit',
                    'amount' => $order->total_amount,
                    'memo' => 'Inventory order revenue recognized',
                ],
            ];
        }

        return $this->postEntry([
            'entry_type' => self::ENTRY_TYPE_ORDER_FULFILLMENT,
            'entry_date' => $order->ordered_on->toDateString(),
            'reference_type' => GondalOrder::class,
            'reference_id' => $order->id,
            'source_key' => 'order:'.$order->id.':fulfillment',
            'description' => __('Order fulfillment posted for :reference', ['reference' => $order->reference]),
            'created_by' => $actor?->id,
            'posted_by' => $actor?->id,
            'meta' => array_filter([
                'order_reference' => $order->reference,
                'payment_mode' => $order->payment_mode,
                'sponsor_name' => $order->sponsor_name,
                'sponsor_reference' => $order->sponsor_reference,
            ], fn ($value) => $value !== null && $value !== ''),
            'lines' => $lines,
        ]);
    }

    public function reverseEntry(JournalEntry $entry, string $reason, ?User $actor = null, ?string $entryDate = null): JournalEntry
    {
        $entry->loadMissing('lines', 'reversalEntry');

        if ($entry->reversal_of_entry_id) {
            throw new \InvalidArgumentException('Reversal entries cannot be reversed again.');
        }

        if ($entry->status === 'reversed' || $entry->reversalEntry) {
            throw new \InvalidArgumentException('This journal entry has already been reversed.');
        }

        return DB::transaction(function () use ($actor, $entry, $entryDate, $reason): JournalEntry {
            $reversal = $this->postEntry([
                'entry_type' => self::ENTRY_TYPE_REVERSAL,
                'entry_date' => $entryDate ?: $entry->entry_date->toDateString(),
                'reference_type' => $entry->reference_type,
                'reference_id' => $entry->reference_id,
                'source_key' => $entry->source_key ? $entry->source_key.':reversal' : null,
                'description' => __('Reversal of journal entry :entry_number', ['entry_number' => $entry->entry_number]),
                'status' => 'posted',
                'reversal_of_entry_id' => $entry->id,
                'created_by' => $actor?->id,
                'posted_by' => $actor?->id,
                'meta' => array_filter([
                    'reversal_reason' => $reason,
                    'reversal_of_entry_number' => $entry->entry_number,
                    'original_entry_type' => $entry->entry_type,
                ], fn ($value) => $value !== null),
                'lines' => $entry->lines->map(function (JournalLine $line): array {
                    return [
                        'finance_account_id' => $line->finance_account_id,
                        'farmer_id' => $line->farmer_id,
                        'project_id' => $line->project_id,
                        'direction' => $line->direction === 'debit' ? 'credit' : 'debit',
                        'amount' => $line->amount,
                        'memo' => 'Reversal of '.$line->memo,
                        'meta' => array_filter([
                            'reversal_of_line_id' => $line->id,
                        ], fn ($value) => $value !== null),
                    ];
                })->all(),
            ]);

            $entry->update([
                'status' => 'reversed',
                'reversed_at' => now(),
                'reversed_by' => $actor?->id,
                'meta' => array_merge($entry->meta ?? [], [
                    'reversed_by_entry_number' => $reversal->entry_number,
                    'reversal_reason' => $reason,
                ]),
            ]);

            return $reversal->load('lines', 'reversalOf');
        });
    }

    public function resolveMilkPrice(MilkCollection $collection): float
    {
        return (float) ($collection->unit_price ?? $this->businessRuleService->resolveMilkPrice($collection->quality_grade));
    }

    public function farmerAccountBalance(Vender $farmer, ?int $adminId = null): float
    {
        $adminId = $adminId ?? ($farmer->created_by ?: (\Auth::check() ? (\Auth::user()->creatorId() ?: \Auth::user()->id) : null));
        $accounts = $this->ensureDefaultAccounts($adminId);
        
        $payableId = $accounts['farmer_payable']->id;
        $obligationId = $accounts['farmer_obligation']->id;

        $lines = JournalLine::query()
            ->where('farmer_id', $farmer->id)
            ->whereIn('finance_account_id', [$payableId, $obligationId])
            ->get();

        // 1. Calculate Payable (Liability): Credit increases, Debit decreases
        $payableLines = $lines->where('finance_account_id', $payableId);
        $payableBalance = (float) $payableLines->where('direction', 'credit')->sum('amount') - 
                         (float) $payableLines->where('direction', 'debit')->sum('amount');

        // 2. Calculate Obligation (Asset): Debit increases, Credit decreases
        $obligationLines = $lines->where('finance_account_id', $obligationId);
        $obligationBalance = (float) $obligationLines->where('direction', 'debit')->sum('amount') - 
                            (float) $obligationLines->where('direction', 'credit')->sum('amount');

        // Net Wallet = What we owe farmer - What farmer owes us
        return round($payableBalance - $obligationBalance, 2);
    }

    public function syncFarmerPayableBalance(int $farmerId): void
    {
        $farmer = Vender::find($farmerId);
        if (!$farmer) {
            return;
        }

        $balance = $this->farmerAccountBalance($farmer, $farmer->created_by);
        $farmer->update(['balance' => $balance]);
    }

    protected function postEntry(array $payload): JournalEntry
    {
        return DB::transaction(function () use ($payload): JournalEntry {
            $entryType = $payload['entry_type'] ?? 'legacy';
            $sourceKey = $payload['source_key'] ?? null;

            if ($sourceKey) {
                $existing = JournalEntry::query()
                    ->where('entry_type', $entryType)
                    ->where('source_key', $sourceKey)
                    ->whereNull('reversal_of_entry_id')
                    ->where('status', 'posted')
                    ->first();

                if ($existing) {
                    return $existing->load('lines');
                }
            }

            if (count($payload['lines']) < 2) {
                throw new \InvalidArgumentException('Journal entries require at least two lines.');
            }

            foreach ($payload['lines'] as $line) {
                if (! in_array($line['direction'] ?? null, ['debit', 'credit'], true)) {
                    throw new \InvalidArgumentException('Journal lines must use debit or credit directions.');
                }

                if (! isset($line['amount']) || round((float) $line['amount'], 2) <= 0) {
                    throw new \InvalidArgumentException('Journal lines must have a positive amount.');
                }

                if (empty($line['finance_account_id'])) {
                    throw new \InvalidArgumentException('Journal lines must reference a finance account.');
                }
            }

            $debitTotal = round(collect($payload['lines'])->where('direction', 'debit')->sum('amount'), 2);
            $creditTotal = round(collect($payload['lines'])->where('direction', 'credit')->sum('amount'), 2);

            if ($debitTotal <= 0 || $creditTotal <= 0 || $debitTotal !== $creditTotal) {
                throw new \InvalidArgumentException('Journal entries must balance with positive debit and credit totals.');
            }

            $entry = JournalEntry::query()->create([
                'entry_number' => $this->nextReference('JRN'),
                'entry_date' => $payload['entry_date'],
                'entry_type' => $entryType,
                'reference_type' => $payload['reference_type'] ?? null,
                'reference_id' => $payload['reference_id'] ?? null,
                'source_key' => $sourceKey,
                'description' => $payload['description'] ?? null,
                'status' => $payload['status'] ?? 'posted',
                'reversal_of_entry_id' => $payload['reversal_of_entry_id'] ?? null,
                'created_by' => $payload['created_by'] ?? null,
                'posted_by' => $payload['posted_by'] ?? null,
                'meta' => $payload['meta'] ?? null,
            ]);

            foreach ($payload['lines'] as $line) {
                $entry->lines()->create($line);
            }

            // Sync farmer balances
            $farmerIds = collect($payload['lines'])->pluck('farmer_id')->filter()->unique();
            foreach ($farmerIds as $farmerId) {
                $this->syncFarmerPayableBalance($farmerId);
            }

            return $entry->load('lines');
        });
    }

    protected function firstOrCreateAccount(?int $createdBy, string $code, string $name, string $type): FinanceAccount
    {
        return FinanceAccount::query()->firstOrCreate(
            [
                'code' => $code,
                'created_by' => $createdBy,
            ],
            [
                'name' => $name,
                'type' => $type,
                'is_system' => true,
            ],
        );
    }

    protected function resolveAdminIdForFarmer(Vender $farmer, ?User $actor = null): ?int
    {
        return $farmer->created_by ?: ($actor?->creatorId() ?? $actor?->id);
    }

    protected function nextReference(string $prefix): string
    {
        return $prefix.'-'.Carbon::now()->format('YmdHis').'-'.Str::upper(Str::random(4));
    }
}
