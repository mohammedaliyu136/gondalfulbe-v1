<?php

namespace App\Models\Gondal;

use App\Models\Vender;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payout extends Model
{
    use HasFactory;

    protected $table = 'gondal_payouts';

    protected $fillable = [
        'settlement_run_id',
        'farmer_id',
        'payment_id',
        'amount',
        'status',
        'scheduled_at',
        'paid_at',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'float',
            'scheduled_at' => 'datetime',
            'paid_at' => 'datetime',
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

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class, 'payment_id');
    }
}
