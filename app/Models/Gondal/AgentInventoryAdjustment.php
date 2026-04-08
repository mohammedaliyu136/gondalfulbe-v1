<?php

namespace App\Models\Gondal;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgentInventoryAdjustment extends Model
{
    use HasFactory;

    protected $table = 'gondal_agent_inventory_adjustments';

    protected $fillable = [
        'agent_profile_id',
        'inventory_item_id',
        'reconciliation_id',
        'created_by',
        'reference',
        'quantity_delta',
        'reason',
        'effective_on',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'quantity_delta' => 'float',
            'effective_on' => 'date',
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

    public function reconciliation(): BelongsTo
    {
        return $this->belongsTo(InventoryReconciliation::class, 'reconciliation_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
