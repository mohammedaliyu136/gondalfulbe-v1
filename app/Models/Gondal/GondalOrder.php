<?php

namespace App\Models\Gondal;

use App\Models\Project;
use App\Models\User;
use App\Models\Vender;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GondalOrder extends Model
{
    use HasFactory;

    protected $table = 'gondal_orders';

    protected $fillable = [
        'reference',
        'farmer_id',
        'agent_profile_id',
        'project_id',
        'status',
        'payment_mode',
        'subtotal_amount',
        'total_amount',
        'settled_amount',
        'outstanding_amount',
        'ordered_on',
        'submitted_at',
        'fulfilled_at',
        'settled_at',
        'cancelled_at',
        'sponsor_name',
        'sponsor_reference',
        'fulfilled_entry_id',
        'cancelled_entry_id',
        'created_by',
        'cancelled_by',
        'notes',
        'meta',
        'cash_amount',
    ];

    protected function casts(): array
    {
        return [
            'subtotal_amount' => 'float',
            'total_amount' => 'float',
            'settled_amount' => 'float',
            'outstanding_amount' => 'float',
            'ordered_on' => 'date',
            'submitted_at' => 'datetime',
            'fulfilled_at' => 'datetime',
            'settled_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'meta' => 'array',
            'cash_amount' => 'float',
        ];
    }

    public function farmer(): BelongsTo
    {
        return $this->belongsTo(Vender::class, 'farmer_id');
    }

    public function agentProfile(): BelongsTo
    {
        return $this->belongsTo(AgentProfile::class, 'agent_profile_id');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(GondalOrderItem::class, 'order_id');
    }

    public function fulfilledEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'fulfilled_entry_id');
    }

    public function cancelledEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'cancelled_entry_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function canceller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }
}
