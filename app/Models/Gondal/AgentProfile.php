<?php

namespace App\Models\Gondal;

use App\Models\User;
use App\Models\Vender;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AgentProfile extends Model
{
    use HasFactory;

    protected $table = 'gondal_agent_profiles';

    protected $fillable = [
        'user_id',
        'vender_id',
        'supervisor_user_id',
        'agent_code',
        'agent_type',
        'outlet_name',
        'assigned_warehouse',
        'reconciliation_frequency',
        'settlement_mode',
        'credit_sales_enabled',
        'credit_limit',
        'stock_variance_tolerance',
        'cash_variance_tolerance',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'credit_sales_enabled' => 'boolean',
            'credit_limit' => 'float',
            'stock_variance_tolerance' => 'float',
            'cash_variance_tolerance' => 'float',
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

    public function stockIssues(): HasMany
    {
        return $this->hasMany(StockIssue::class, 'agent_profile_id');
    }

    public function sales(): HasMany
    {
        return $this->hasMany(InventorySale::class, 'agent_profile_id');
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
}
