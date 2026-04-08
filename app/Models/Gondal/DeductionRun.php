<?php

namespace App\Models\Gondal;

use App\Models\User;
use App\Models\Vender;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DeductionRun extends Model
{
    use HasFactory;

    protected $table = 'gondal_deduction_runs';

    protected $fillable = [
        'settlement_run_id',
        'farmer_id',
        'period_start',
        'period_end',
        'gross_amount',
        'deduction_cap_amount',
        'payout_floor_amount',
        'total_deducted_amount',
        'net_payout_amount',
        'status',
        'meta',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'gross_amount' => 'float',
            'deduction_cap_amount' => 'float',
            'payout_floor_amount' => 'float',
            'total_deducted_amount' => 'float',
            'net_payout_amount' => 'float',
            'meta' => 'array',
        ];
    }

    public function settlementRun(): BelongsTo
    {
        return $this->belongsTo(SettlementRun::class, 'settlement_run_id');
    }

    public function farmer(): BelongsTo
    {
        return $this->belongsTo(Vender::class, 'farmer_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(DeductionAllocation::class, 'deduction_run_id');
    }
}
