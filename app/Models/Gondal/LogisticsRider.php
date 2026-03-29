<?php

namespace App\Models\Gondal;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LogisticsRider extends Model
{
    use HasFactory;

    protected $table = 'gondal_logistics_riders';

    protected $fillable = [
        'name',
        'code',
        'phone',
        'photo_path',
        'bank_name',
        'account_number',
        'account_name',
        'bike_make',
        'bike_model',
        'bike_plate_number',
        'identification_type',
        'identification_number',
        'identification_document_path',
        'status',
    ];

    public function trips(): HasMany
    {
        return $this->hasMany(LogisticsTrip::class, 'rider_id');
    }
}
