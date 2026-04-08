<?php

namespace App\Models\Gondal;

use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventorySale extends Model
{
    use HasFactory;

    protected $table = 'gondal_inventory_sales';

    protected $fillable = ['inventory_item_id', 'batch_id', 'agent_profile_id', 'order_id', 'project_id', 'extension_visit_id', 'vender_id', 'quantity', 'unit_price', 'total_amount', 'cash_amount', 'payment_method', 'credit_allowed_snapshot', 'sold_on', 'cancelled_at', 'cancelled_reason', 'customer_name', 'journal_entry_id'];

    protected function casts(): array
    {
        return [
            'quantity' => 'float',
            'unit_price' => 'float',
            'total_amount' => 'float',
            'cash_amount' => 'float',
            'credit_allowed_snapshot' => 'boolean',
            'sold_on' => 'date',
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

    public function extensionVisit(): BelongsTo
    {
        return $this->belongsTo(ExtensionVisit::class, 'extension_visit_id');
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'journal_entry_id');
    }
}
