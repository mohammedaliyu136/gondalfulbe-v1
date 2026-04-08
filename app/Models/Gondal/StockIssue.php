<?php

namespace App\Models\Gondal;

use App\Models\User;
use App\Models\warehouse;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockIssue extends Model
{
    use HasFactory;

    protected $table = 'gondal_stock_issues';

    protected $fillable = [
        'agent_profile_id',
        'warehouse_id',
        'one_stop_shop_id',
        'issue_stage',
        'inventory_item_id',
        'issued_by',
        'issue_reference',
        'batch_reference',
        'quantity_issued',
        'unit_cost',
        'issued_on',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'quantity_issued' => 'float',
            'unit_cost' => 'float',
            'issued_on' => 'date',
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

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(warehouse::class, 'warehouse_id');
    }

    public function oneStopShop(): BelongsTo
    {
        return $this->belongsTo(OneStopShop::class, 'one_stop_shop_id');
    }

    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }
}
