<?php

namespace Modules\Cooperatives\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
// use Modules\Cooperatives\Database\Factories\CooperativeFactory;

class Cooperative extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'code',
        'name',
        'location',
        'status',
        'leader_name',
        'leader_phone',
        'site_location',
        'formation_date',
        'average_daily_supply',
    ];

    public function farmers()
    {
        return $this->hasMany(\App\Models\Vender::class, 'cooperative_id', 'id');
    }

    public function milkCollections()
    {
        return $this->hasMany(\Modules\MilkCollection\Models\MilkCollection::class, 'cooperative_id', 'id');
    }
}
