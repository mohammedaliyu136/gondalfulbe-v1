<?php

namespace App\Models\Gondal;

use App\Models\Project;
use App\Models\User;
use App\Models\Vender;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GondalLoan extends Model
{
    use HasFactory;

    protected $table = 'gondal_loans';

    protected $fillable = [
        'reference', 'farmer_id', 'agent_profile_id', 'project_id',
        'type', 'principal_amount', 'interest_rate', 'status', 'notes',
        'created_by', 'approved_by', 'approved_at'
    ];

    protected function casts(): array
    {
        return [
            'principal_amount' => 'float',
            'interest_rate' => 'float',
            'approved_at' => 'datetime',
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

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function repaymentSchedules(): HasMany
    {
        return $this->hasMany(GondalLoanRepaymentSchedule::class, 'gondal_loan_id');
    }

    public function disbursements(): HasMany
    {
        return $this->hasMany(GondalLoanDisbursement::class, 'gondal_loan_id');
    }
}
