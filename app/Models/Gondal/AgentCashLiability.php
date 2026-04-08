<?php

namespace App\Models\Gondal;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgentCashLiability extends Model
{
    use HasFactory;

    protected $table = 'gondal_agent_cash_liabilities';

    protected $fillable = [
        'agent_profile_id',
        'reconciliation_id',
        'created_by',
        'reference',
        'amount',
        'liability_type',
        'status',
        'due_date',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'float',
            'due_date' => 'date',
        ];
    }

    public function agentProfile(): BelongsTo
    {
        return $this->belongsTo(AgentProfile::class, 'agent_profile_id');
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
