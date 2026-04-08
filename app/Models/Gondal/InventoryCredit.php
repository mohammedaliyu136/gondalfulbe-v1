<?php

namespace App\Models\Gondal;

use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryCredit extends Model
{
    use HasFactory;

    protected $table = 'gondal_inventory_credits';

    protected $fillable = ['inventory_item_id', 'agent_profile_id', 'order_id', 'project_id', 'inventory_sale_id', 'vender_id', 'customer_name', 'amount', 'outstanding_amount', 'status', 'credit_date', 'due_date', 'cancelled_at'];

    protected function casts(): array
    {
        return [
            'amount' => 'float',
            'outstanding_amount' => 'float',
            'credit_date' => 'date',
            'due_date' => 'date',
            'cancelled_at' => 'datetime',
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

    public function order(): BelongsTo
    {
        return $this->belongsTo(GondalOrder::class, 'order_id');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(InventorySale::class, 'inventory_sale_id');
    }
}
