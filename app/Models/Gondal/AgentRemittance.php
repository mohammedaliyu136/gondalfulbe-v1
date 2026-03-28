<?php

namespace App\Models\Gondal;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgentRemittance extends Model
{
    use HasFactory;

    protected $table = 'gondal_agent_remittances';

    protected $fillable = [
        'agent_profile_id',
        'received_by',
        'reconciliation_mode',
        'reference',
        'amount',
        'payment_method',
        'period_start',
        'period_end',
        'remitted_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'float',
            'period_start' => 'date',
            'period_end' => 'date',
            'remitted_at' => 'datetime',
        ];
    }

    public function agentProfile(): BelongsTo
    {
        return $this->belongsTo(AgentProfile::class, 'agent_profile_id');
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }
}
