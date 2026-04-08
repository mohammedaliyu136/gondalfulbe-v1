<?php

namespace App\Models\Gondal;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryReconciliation extends Model
{
    use HasFactory;

    protected $table = 'gondal_inventory_reconciliations';

    protected $fillable = [
        'agent_profile_id',
        'one_stop_shop_id',
        'inventory_item_id',
        'submitted_by',
        'reviewed_by',
        'reconciliation_mode',
        'reference',
        'period_start',
        'period_end',
        'opening_stock_qty',
        'issued_stock_qty',
        'sold_stock_qty',
        'returned_stock_qty',
        'damaged_stock_qty',
        'expected_stock_qty',
        'counted_stock_qty',
        'stock_variance_qty',
        'cash_sales_amount',
        'transfer_sales_amount',
        'credit_sales_amount',
        'credit_collections_amount',
        'expected_cash_amount',
        'remitted_cash_amount',
        'cash_variance_amount',
        'outstanding_credit_amount',
        'status',
        'agent_notes',
        'review_notes',
    ];

    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'opening_stock_qty' => 'float',
            'issued_stock_qty' => 'float',
            'sold_stock_qty' => 'float',
            'returned_stock_qty' => 'float',
            'damaged_stock_qty' => 'float',
            'expected_stock_qty' => 'float',
            'counted_stock_qty' => 'float',
            'stock_variance_qty' => 'float',
            'cash_sales_amount' => 'float',
            'transfer_sales_amount' => 'float',
            'credit_sales_amount' => 'float',
            'credit_collections_amount' => 'float',
            'expected_cash_amount' => 'float',
            'remitted_cash_amount' => 'float',
            'cash_variance_amount' => 'float',
            'outstanding_credit_amount' => 'float',
        ];
    }

    public function agentProfile(): BelongsTo
    {
        return $this->belongsTo(AgentProfile::class, 'agent_profile_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_item_id');
    }

    public function oneStopShop(): BelongsTo
    {
        return $this->belongsTo(OneStopShop::class, 'one_stop_shop_id');
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
