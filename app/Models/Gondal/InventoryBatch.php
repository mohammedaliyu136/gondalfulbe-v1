<?php

namespace App\Models\Gondal;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryBatch extends Model
{
    use HasFactory;

    protected $table = 'gondal_inventory_batches';

    protected $fillable = [
        'inventory_item_id',
        'batch_number',
        'expires_at',
        'stock_qty',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'date',
            'stock_qty' => 'float',
            'is_active' => 'boolean',
        ];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_item_id');
    }

    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }
}
