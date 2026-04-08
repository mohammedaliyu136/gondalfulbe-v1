<?php

namespace App\Models\Gondal;

use App\Models\Project;
use App\Models\User;
use App\Models\Vender;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Obligation extends Model
{
    use HasFactory;

    protected $table = 'gondal_obligations';

    protected $fillable = [
        'reference',
        'farmer_id',
        'agent_profile_id',
        'inventory_credit_id',
        'project_id',
        'source_type',
        'source_id',
        'principal_amount',
        'outstanding_amount',
        'recovered_amount',
        'priority',
        'max_deduction_percent',
        'payout_floor_amount',
        'due_date',
        'status',
        'meta',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'principal_amount' => 'float',
            'outstanding_amount' => 'float',
            'recovered_amount' => 'float',
            'max_deduction_percent' => 'float',
            'payout_floor_amount' => 'float',
            'due_date' => 'date',
            'meta' => 'array',
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

    public function inventoryCredit(): BelongsTo
    {
        return $this->belongsTo(InventoryCredit::class, 'inventory_credit_id');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function installments(): HasMany
    {
        return $this->hasMany(ObligationInstallment::class, 'obligation_id');
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(DeductionAllocation::class, 'obligation_id');
    }
}
