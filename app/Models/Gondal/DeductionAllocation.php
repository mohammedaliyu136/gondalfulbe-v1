<?php

namespace App\Models\Gondal;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeductionAllocation extends Model
{
    use HasFactory;

    protected $table = 'gondal_deduction_allocations';

    protected $fillable = [
        'deduction_run_id',
        'obligation_id',
        'amount',
        'priority',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'float',
            'meta' => 'array',
        ];
    }

    public function deductionRun(): BelongsTo
    {
        return $this->belongsTo(DeductionRun::class, 'deduction_run_id');
    }

    public function obligation(): BelongsTo
    {
        return $this->belongsTo(Obligation::class, 'obligation_id');
    }
}
