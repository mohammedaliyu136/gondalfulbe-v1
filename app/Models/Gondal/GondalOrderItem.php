<?php

namespace App\Models\Gondal;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GondalOrderItem extends Model
{
    use HasFactory;

    protected $table = 'gondal_order_items';

    protected $fillable = [
        'order_id',
        'inventory_item_id',
        'inventory_sale_id',
        'inventory_credit_id',
        'obligation_id',
        'quantity',
        'unit_price',
        'line_total',
        'status',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'float',
            'unit_price' => 'float',
            'line_total' => 'float',
            'meta' => 'array',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(GondalOrder::class, 'order_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_item_id');
    }

    public function inventorySale(): BelongsTo
    {
        return $this->belongsTo(InventorySale::class, 'inventory_sale_id');
    }

    public function inventoryCredit(): BelongsTo
    {
        return $this->belongsTo(InventoryCredit::class, 'inventory_credit_id');
    }

    public function obligation(): BelongsTo
    {
        return $this->belongsTo(Obligation::class, 'obligation_id');
    }
}
