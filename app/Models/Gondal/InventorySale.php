<?php

namespace App\Models\Gondal;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventorySale extends Model
{
    use HasFactory;

    protected $table = 'gondal_inventory_sales';

    protected $fillable = ['inventory_item_id', 'agent_profile_id', 'vender_id', 'quantity', 'unit_price', 'total_amount', 'payment_method', 'credit_allowed_snapshot', 'sold_on', 'customer_name'];

    protected function casts(): array
    {
        return [
            'quantity' => 'float',
            'unit_price' => 'float',
            'total_amount' => 'float',
            'credit_allowed_snapshot' => 'boolean',
            'sold_on' => 'date',
        ];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_item_id');
    }

    public function vender(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Vender::class, 'vender_id');
    }

    public function agentProfile(): BelongsTo
    {
        return $this->belongsTo(AgentProfile::class, 'agent_profile_id');
    }
}
