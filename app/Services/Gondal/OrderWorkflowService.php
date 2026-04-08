<?php

namespace App\Services\Gondal;

use App\Models\Gondal\AgentInventoryAdjustment;
use App\Models\Gondal\AgentProfile;
use App\Models\Gondal\GondalOrder;
use App\Models\Gondal\GondalOrderItem;
use App\Models\Gondal\InventoryCredit;
use App\Models\Gondal\InventoryItem;
use App\Models\Gondal\Obligation;
use App\Models\Gondal\JournalEntry;
use App\Models\User;
use App\Models\Vender;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class OrderWorkflowService
{
    public const PAYMENT_MODE_MIXED = 'mixed';
    public const PAYMENT_MODE_CASH = 'cash';
    public const PAYMENT_MODE_MILK_DEDUCTION = 'milk_deduction';
    public const PAYMENT_MODE_SPONSOR_FUNDED = 'sponsor_funded';

    public function __construct(
        protected InventoryWorkflowService $inventoryWorkflowService,
        protected LedgerService $ledgerService,
        protected ProgramScopeService $programScopeService,
        protected ProgramFundingService $programFundingService,
    ) {}

    public function createOrder(array $payload, User $actor): GondalOrder
    {
        $items = collect($payload['items'] ?? [])->filter(fn (array $item) => (float) ($item['quantity'] ?? 0) > 0)->values();

        if ($items->isEmpty()) {
            throw ValidationException::withMessages([
                'items' => [__('Add at least one order item before saving the order.')],
            ]);
        }

        $paymentMode = Str::lower((string) ($payload['payment_mode'] ?? ''));
        if (! in_array($paymentMode, $this->paymentModes(), true)) {
            throw ValidationException::withMessages([
                'payment_mode' => [__('The selected payment mode is invalid.')],
            ]);
        }

        $farmer = Vender::query()->findOrFail($payload['farmer_id']);
        
        if (! $farmer->is_active || $farmer->status === 'inactive') {
            throw ValidationException::withMessages([
                'farmer_id' => [__('Inactive farmers cannot place new orders.')],
            ]);
        }

        $agentProfile = ! empty($payload['agent_profile_id'])
            ? AgentProfile::query()->findOrFail((int) $payload['agent_profile_id'])
            : null;
        $projectId = $payload['project_id']
            ?? $this->programScopeService->resolveFarmerProjectId($farmer, $actor, $agentProfile);
        $initialStatus = in_array(($payload['status'] ?? 'draft'), ['draft', 'submitted'], true)
            ? ($payload['status'] ?? 'draft')
            : 'draft';

        if ($paymentMode === self::PAYMENT_MODE_SPONSOR_FUNDED && ! $projectId) {
            throw ValidationException::withMessages([
                'project_id' => [__('Sponsor-funded orders require a program project.')],
            ]);
        }

        return DB::transaction(function () use ($actor, $agentProfile, $farmer, $initialStatus, $items, $payload, $paymentMode, $projectId): GondalOrder {
            $order = GondalOrder::query()->create([
                'reference' => $payload['reference'] ?? $this->nextReference('ORD'),
                'farmer_id' => $farmer->id,
                'agent_profile_id' => $agentProfile?->id,
                'project_id' => $projectId,
                'status' => $initialStatus,
                'payment_mode' => $paymentMode,
                'subtotal_amount' => 0,
                'total_amount' => 0,
                'settled_amount' => 0,
                'outstanding_amount' => 0,
                'ordered_on' => $payload['ordered_on'] ?? now()->toDateString(),
                'submitted_at' => $initialStatus === 'submitted' ? now() : null,
                'sponsor_name' => $payload['sponsor_name'] ?? null,
                'sponsor_reference' => $payload['sponsor_reference'] ?? null,
                'created_by' => $actor->id,
                'notes' => $payload['notes'] ?? null,
                'meta' => [
                    'created_via' => 'order_workflow',
                ],
            ]);

            $subtotal = 0.0;
            foreach ($items as $itemPayload) {
                $inventoryItem = InventoryItem::query()->findOrFail((int) $itemPayload['inventory_item_id']);

                if (! empty($agentProfile?->permitted_categories) && ! in_array($inventoryItem->category, $agentProfile->permitted_categories, true)) {
                    throw ValidationException::withMessages([
                        'inventory_item_id' => [__('Agent is not permitted to sell items in the :category category.', ['category' => $inventoryItem->category])],
                    ]);
                }

                $unitPrice = (float) ($itemPayload['unit_price'] ?? $inventoryItem->unit_price);

                $lineTotal = round((float) $itemPayload['quantity'] * $unitPrice, 2);
                $subtotal = round($subtotal + $lineTotal, 2);

                $order->items()->create([
                    'inventory_item_id' => $itemPayload['inventory_item_id'],
                    'quantity' => $itemPayload['quantity'],
                    'unit_price' => $unitPrice,
                    'line_total' => $lineTotal,
                    'status' => $order->status,
                    'meta' => $itemPayload['meta'] ?? null,
                ]);
            }

            $cashAmount = match ($paymentMode) {
                self::PAYMENT_MODE_CASH => $subtotal,
                self::PAYMENT_MODE_MIXED => min($subtotal, (float) ($payload['cash_amount'] ?? 0)),
                default => 0.0,
            };

            $order->update([
                'subtotal_amount' => $subtotal,
                'total_amount' => $subtotal,
                'cash_amount' => ($paymentMode === self::PAYMENT_MODE_MIXED) ? $cashAmount : 0,
                'settled_amount' => $cashAmount,
                'outstanding_amount' => max($subtotal - $cashAmount, 0),
            ]);

            return $order->fresh('items');
        });
    }

    public function fulfillOrder(GondalOrder $order, User $actor, array $payload = []): GondalOrder
    {
        $order->loadMissing(['farmer', 'agentProfile', 'items']);

        if (! in_array($order->status, ['draft', 'submitted'], true)) {
            throw ValidationException::withMessages([
                'order' => [__('Only draft or submitted orders can be fulfilled.')],
            ]);
        }

        if ($order->items->isEmpty()) {
            throw ValidationException::withMessages([
                'order' => [__('Orders must contain at least one item before fulfillment.')],
            ]);
        }

        $fulfilledOn = Carbon::parse($payload['fulfilled_on'] ?? now())->toDateString();

        return DB::transaction(function () use ($actor, $fulfilledOn, $order): GondalOrder {
            $fundingSnapshot = $order->payment_mode === self::PAYMENT_MODE_SPONSOR_FUNDED
                ? $this->programFundingService->enforceSponsorFundingLimit($order)
                : [];

            foreach ($order->items as $item) {
                if ($item->inventory_sale_id) {
                    continue;
                }

                $sale = $this->inventoryWorkflowService->createInventorySale([
                    'inventory_item_id' => $item->inventory_item_id,
                    'agent_profile_id' => $order->agent_profile_id,
                    'vender_id' => $order->farmer_id,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                    'payment_method' => $this->inventoryPaymentMethod($order->payment_mode),
                    'cash_amount' => ($order->total_amount > 0) ? round(($item->line_total / $order->total_amount) * $order->cash_amount, 2) : 0,
                    'sold_on' => $fulfilledOn,
                    'customer_name' => $order->farmer?->name,
                    'order_id' => $order->id,
                ]);

                $credit = InventoryCredit::query()
                    ->where('inventory_sale_id', $sale->id)
                    ->latest('id')
                    ->first();
                $obligation = $credit
                    ? Obligation::query()->where('inventory_credit_id', $credit->id)->latest('id')->first()
                    : null;

                $sale->update(['order_id' => $order->id]);
                if ($credit) {
                    $credit->update(['order_id' => $order->id]);
                }

                $item->update([
                    'inventory_sale_id' => $sale->id,
                    'inventory_credit_id' => $credit?->id,
                    'obligation_id' => $obligation?->id,
                    'status' => 'fulfilled',
                ]);
            }

            $entry = $this->ledgerService->postOrderFulfillment($order->fresh(['items.item']), $actor);
            $outstandingAmount = $this->paymentModeCarriesBalance($order->payment_mode)
                ? (float) $order->total_amount
                : 0.0;

            $order->update([
                'status' => 'fulfilled',
                'submitted_at' => $order->submitted_at ?? now(),
                'fulfilled_at' => now(),
                'fulfilled_entry_id' => $entry->id,
                'outstanding_amount' => $outstandingAmount,
                'meta' => array_merge($order->meta ?? [], [
                    'fulfilled_on' => $fulfilledOn,
                    'funding_snapshot' => $fundingSnapshot,
                ]),
            ]);

            if ($order->farmer_id) {
                app(\App\Services\Gondal\GondalNotificationService::class)->queueNotification(
                    'order_confirmed',
                    'farmer',
                    $order->farmer_id,
                    'sms',
                    "Your order {$order->reference} has been successfully confirmed and fulfilled.",
                    $order->reference . '-FARMER'
                );
            }

            if ($order->agent_profile_id) {
                app(\App\Services\Gondal\GondalNotificationService::class)->queueNotification(
                    'order_confirmed',
                    'agent',
                    $order->agent_profile_id,
                    'sms',
                    "Order {$order->reference} for farmer #{$order->farmer_id} has been successfully confirmed and fulfilled.",
                    $order->reference . '-AGENT'
                );
            }

            return $order->fresh(['items.inventorySale', 'items.inventoryCredit', 'items.obligation', 'fulfilledEntry']);
        });
    }

    public function cancelOrder(GondalOrder $order, string $reason, User $actor): GondalOrder
    {
        $order->loadMissing(['items.inventorySale.item', 'items.inventoryCredit', 'items.obligation', 'fulfilledEntry']);

        if ($order->status === 'cancelled') {
            throw ValidationException::withMessages([
                'order' => [__('This order has already been cancelled.')],
            ]);
        }

        if ((float) $order->settled_amount > 0) {
            throw ValidationException::withMessages([
                'order' => [__('Settled orders cannot be cancelled.')],
            ]);
        }

        if ($order->items->contains(fn (GondalOrderItem $item) => (float) ($item->obligation?->recovered_amount ?? 0) > 0)) {
            throw ValidationException::withMessages([
                'order' => [__('Orders with recovered milk deductions cannot be cancelled.')],
            ]);
        }

        return DB::transaction(function () use ($actor, $order, $reason): GondalOrder {
            $reversal = null;
            if ($order->fulfilledEntry instanceof JournalEntry) {
                $reversal = $this->ledgerService->reverseEntry($order->fulfilledEntry, $reason, $actor);
            }

            foreach ($order->items as $item) {
                if ($item->inventorySale && ! $item->inventorySale->cancelled_at) {
                    $this->restoreOrderItemStock($item, $actor, $reason);

                    $item->inventorySale->update([
                        'cancelled_at' => now(),
                        'cancelled_reason' => $reason,
                    ]);
                }

                if ($item->inventoryCredit) {
                    $item->inventoryCredit->update([
                        'order_id' => $order->id,
                        'status' => 'cancelled',
                        'outstanding_amount' => 0,
                        'cancelled_at' => now(),
                    ]);
                }

                if ($item->obligation) {
                    $item->obligation->update([
                        'status' => 'cancelled',
                        'outstanding_amount' => 0,
                        'meta' => array_merge($item->obligation->meta ?? [], [
                            'cancelled_order_id' => $order->id,
                            'cancellation_reason' => $reason,
                        ]),
                    ]);
                }

                $item->update(['status' => 'cancelled']);
            }

            $order->update([
                'status' => 'cancelled',
                'outstanding_amount' => 0,
                'cancelled_at' => now(),
                'cancelled_by' => $actor->id,
                'cancelled_entry_id' => $reversal?->id,
                'meta' => array_merge($order->meta ?? [], [
                    'cancellation_reason' => $reason,
                ]),
            ]);

            return $order->fresh(['items.inventorySale', 'items.inventoryCredit', 'items.obligation', 'cancelledEntry']);
        });
    }

    public function paymentModes(): array
    {
        return [
            self::PAYMENT_MODE_CASH,
            self::PAYMENT_MODE_MILK_DEDUCTION,
            self::PAYMENT_MODE_SPONSOR_FUNDED,
            self::PAYMENT_MODE_MIXED,
        ];
    }

    protected function paymentModeCarriesBalance(string $paymentMode): bool
    {
        return in_array($paymentMode, [self::PAYMENT_MODE_MILK_DEDUCTION, self::PAYMENT_MODE_SPONSOR_FUNDED, self::PAYMENT_MODE_MIXED], true);
    }

    protected function inventoryPaymentMethod(string $paymentMode): string
    {
        return match ($paymentMode) {
            self::PAYMENT_MODE_CASH => 'Cash',
            self::PAYMENT_MODE_MILK_DEDUCTION => 'Milk Collection Balance',
            self::PAYMENT_MODE_SPONSOR_FUNDED => 'Transfer',
            self::PAYMENT_MODE_MIXED => 'Mixed',
            default => throw ValidationException::withMessages([
                'payment_mode' => [__('The selected payment mode is invalid.')],
            ]),
        };
    }

    protected function restoreOrderItemStock(GondalOrderItem $item, User $actor, string $reason): void
    {
        $sale = $item->inventorySale;
        if (! $sale) {
            return;
        }

        if ($sale->agent_profile_id) {
            AgentInventoryAdjustment::query()->create([
                'agent_profile_id' => $sale->agent_profile_id,
                'inventory_item_id' => $sale->inventory_item_id,
                'created_by' => $actor->id,
                'reference' => $this->nextReference('ADJ'),
                'quantity_delta' => $sale->quantity,
                'reason' => 'order_cancellation',
                'effective_on' => now()->toDateString(),
                'notes' => __('Inventory restored for cancelled order :reference. Reason: :reason', [
                    'reference' => $item->order->reference,
                    'reason' => $reason,
                ]),
            ]);

            return;
        }

        $sale->item()->increment('stock_qty', $sale->quantity);
    }

    protected function nextReference(string $prefix): string
    {
        return $prefix.'-'.now()->format('YmdHis').'-'.Str::upper(Str::random(4));
    }
}
