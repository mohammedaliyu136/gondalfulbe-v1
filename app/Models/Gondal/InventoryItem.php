<?php

namespace App\Models\Gondal;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventoryItem extends Model
{
    use HasFactory;

    protected $table = 'gondal_inventory_items';

    protected $fillable = ['name', 'category', 'unit', 'sku', 'stock_qty', 'unit_price', 'status'];

    protected function casts(): array
    {
        return [
            'stock_qty' => 'float',
            'unit_price' => 'float',
        ];
    }

    public function sales(): HasMany
    {
        return $this->hasMany(InventorySale::class, 'inventory_item_id');
    }

    public function credits(): HasMany
    {
        return $this->hasMany(InventoryCredit::class, 'inventory_item_id');
    }

    public function stockIssues(): HasMany
    {
        return $this->hasMany(StockIssue::class, 'inventory_item_id');
    }

    public function reconciliations(): HasMany
    {
        return $this->hasMany(InventoryReconciliation::class, 'inventory_item_id');
    }
}
