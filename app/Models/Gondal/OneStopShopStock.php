<?php

namespace App\Models\Gondal;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OneStopShopStock extends Model
{
    use HasFactory;

    protected $table = 'gondal_one_stop_shop_stocks';

    protected $fillable = [
        'one_stop_shop_id',
        'inventory_item_id',
        'quantity',
        'reorder_level',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'float',
            'reorder_level' => 'float',
        ];
    }

    public function oneStopShop(): BelongsTo
    {
        return $this->belongsTo(OneStopShop::class, 'one_stop_shop_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_item_id');
    }
}
