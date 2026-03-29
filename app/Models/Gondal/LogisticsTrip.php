<?php

namespace App\Models\Gondal;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Cooperatives\Models\Cooperative;

class LogisticsTrip extends Model
{
    use HasFactory;

    protected $table = 'gondal_logistics_trips';

    protected $fillable = [
        'rider_id',
        'cooperative_id',
        'trip_date',
        'vehicle_name',
        'departure_time',
        'arrival_time',
        'volume_liters',
        'distance_km',
        'fuel_cost',
        'status',
        'payment_batch_id',
    ];

    protected function casts(): array
    {
        return [
            'trip_date' => 'date',
            'volume_liters' => 'float',
            'distance_km' => 'float',
            'fuel_cost' => 'float',
        ];
    }

    public function rider(): BelongsTo
    {
        return $this->belongsTo(LogisticsRider::class, 'rider_id');
    }

    public function cooperative(): BelongsTo
    {
        return $this->belongsTo(Cooperative::class, 'cooperative_id');
    }

    public function paymentBatch(): BelongsTo
    {
        return $this->belongsTo(PaymentBatch::class, 'payment_batch_id');
    }
}
