<?php

namespace App\Models\Gondal;

use App\Models\Vender;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    use HasFactory;

    protected $table = 'gondal_payments';

    protected $fillable = ['batch_id', 'farmer_id', 'amount', 'status', 'payment_date', 'gateway_reference'];

    protected function casts(): array
    {
        return [
            'amount' => 'float',
            'payment_date' => 'date',
        ];
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(PaymentBatch::class, 'batch_id');
    }

    public function farmer(): BelongsTo
    {
        return $this->belongsTo(Vender::class, 'farmer_id');
    }
}
