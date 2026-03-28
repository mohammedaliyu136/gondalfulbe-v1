<?php

namespace App\Models\Gondal;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Cooperatives\Models\Cooperative;

class Requisition extends Model
{
    use HasFactory;

    protected $table = 'gondal_requisitions';

    protected $fillable = [
        'reference',
        'requester_id',
        'cooperative_id',
        'title',
        'description',
        'total_amount',
        'priority',
        'status',
        'submitted_at',
        'approved_at',
        'rejected_at',
    ];

    protected function casts(): array
    {
        return [
            'total_amount' => 'float',
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
        ];
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    public function cooperative(): BelongsTo
    {
        return $this->belongsTo(Cooperative::class, 'cooperative_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(RequisitionItem::class, 'requisition_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(RequisitionEvent::class, 'requisition_id');
    }
}
