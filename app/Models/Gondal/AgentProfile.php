<?php

namespace App\Models\Gondal;

use App\Models\Project;
use App\Models\User;
use App\Models\Vender;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Cooperatives\Models\Cooperative;

class AgentProfile extends Model
{
    use HasFactory;

    protected $table = 'gondal_agent_profiles';

    protected $fillable = [
        'user_id',
        'vender_id',
        'supervisor_user_id',
        'sponsor_user_id',
        'project_id',
        'one_stop_shop_id',
        'agent_code',
        'agent_type',
        'first_name',
        'middle_name',
        'last_name',
        'gender',
        'phone_number',
        'email',
        'nin',
        'state',
        'lga',
        'community_id',
        'community',
        'residential_address',
        'permanent_address',
        'account_number',
        'account_name',
        'bank_details',
        'assigned_communities',
        'assigned_warehouse',
        'reconciliation_frequency',
        'settlement_mode',
        'credit_sales_enabled',
        'credit_limit',
        'stock_variance_tolerance',
        'cash_variance_tolerance',
        'status',
        'notes',
        'permitted_categories',
    ];

    protected function casts(): array
    {
        return [
            'assigned_communities' => 'array',
            'credit_sales_enabled' => 'boolean',
            'credit_limit' => 'float',
            'stock_variance_tolerance' => 'float',
            'cash_variance_tolerance' => 'float',
            'permitted_categories' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function vender(): BelongsTo
    {
        return $this->belongsTo(Vender::class, 'vender_id');
    }

    public function supervisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'supervisor_user_id');
    }

    public function sponsor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sponsor_user_id');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    public function oneStopShop(): BelongsTo
    {
        return $this->belongsTo(OneStopShop::class, 'one_stop_shop_id');
    }

    public function communityRecord(): BelongsTo
    {
        return $this->belongsTo(Community::class, 'community_id');
    }

    public function stockIssues(): HasMany
    {
        return $this->hasMany(StockIssue::class, 'agent_profile_id');
    }

    public function cooperatives(): BelongsToMany
    {
        return $this->belongsToMany(Cooperative::class, 'gondal_agent_profile_cooperative', 'agent_profile_id', 'cooperative_id')
            ->withTimestamps();
    }

    public function sales(): HasMany
    {
        return $this->hasMany(InventorySale::class, 'agent_profile_id');
    }

    public function programAssignments(): HasMany
    {
        return $this->hasMany(ProgramAgentAssignment::class, 'agent_profile_id');
    }

    public function obligations(): HasMany
    {
        return $this->hasMany(Obligation::class, 'agent_profile_id');
    }

    public function credits(): HasMany
    {
        return $this->hasMany(InventoryCredit::class, 'agent_profile_id');
    }

    public function remittances(): HasMany
    {
        return $this->hasMany(AgentRemittance::class, 'agent_profile_id');
    }

    public function reconciliations(): HasMany
    {
        return $this->hasMany(InventoryReconciliation::class, 'agent_profile_id');
    }

    public function getFullNameAttribute(): string
    {
        $name = collect([
            $this->first_name,
            $this->middle_name,
            $this->last_name,
        ])->filter()->implode(' ');

        return $name !== '' ? $name : ($this->user?->name ?: ($this->vender?->name ?: ''));
    }
}
