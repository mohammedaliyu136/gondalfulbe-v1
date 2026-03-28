<?php

namespace App\Models\Gondal;

use App\Models\warehouse;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WarehouseStock extends Model
{
    use HasFactory;

    protected $table = 'gondal_warehouse_stocks';

    protected $fillable = [
        'warehouse_id',
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

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(warehouse::class, 'warehouse_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_item_id');
    }
}
