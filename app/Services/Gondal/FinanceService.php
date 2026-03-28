<?php

namespace App\Services\Gondal;

use App\Models\Gondal\InventoryCredit;
use App\Models\Gondal\InventorySale;
use App\Models\Gondal\PaymentBatch;

class FinanceService
{
    public function openReceivables(): float
    {
        return (float) InventoryCredit::query()->where('status', 'open')->sum('amount');
    }

    public function salesTotal(): float
    {
        return (float) InventorySale::query()->get()->sum(fn (InventorySale $sale) => $sale->quantity * $sale->unit_price);
    }

    public function batchStatusCounts(): array
    {
        return PaymentBatch::query()
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status')
            ->map(fn ($count) => (int) $count)
            ->all();
    }
}
