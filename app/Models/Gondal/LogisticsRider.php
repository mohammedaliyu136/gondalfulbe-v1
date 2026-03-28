<?php

namespace App\Models\Gondal;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LogisticsRider extends Model
{
    use HasFactory;

    protected $table = 'gondal_logistics_riders';

    protected $fillable = ['name', 'code', 'phone', 'status'];

    public function trips(): HasMany
    {
        return $this->hasMany(LogisticsTrip::class, 'rider_id');
    }
}
