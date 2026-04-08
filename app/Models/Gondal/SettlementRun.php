<?php

namespace App\Models\Gondal;

use App\Models\Project;
use App\Models\User;
use App\Models\Vender;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SettlementRun extends Model
{
    use HasFactory;

    protected $table = 'gondal_settlement_runs';

    protected $fillable = [
        'reference',
        'farmer_id',
        'project_id',
        'period_start',
        'period_end',
        'gross_milk_value',
        'total_deductions',
        'net_payout',
        'status',
        'payment_batch_id',
        'meta',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'gross_milk_value' => 'float',
            'total_deductions' => 'float',
            'net_payout' => 'float',
            'meta' => 'array',
        ];
    }

    public function farmer(): BelongsTo
    {
        return $this->belongsTo(Vender::class, 'farmer_id');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    public function paymentBatch(): BelongsTo
    {
        return $this->belongsTo(PaymentBatch::class, 'payment_batch_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function deductionRuns(): HasMany
    {
        return $this->hasMany(DeductionRun::class, 'settlement_run_id');
    }

    public function payouts(): HasMany
    {
        return $this->hasMany(Payout::class, 'settlement_run_id');
    }
}
