<?php

namespace App\Models\Gondal;

use App\Models\warehouse;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OneStopShop extends Model
{
    use HasFactory;

    protected $table = 'gondal_one_stop_shops';

    protected $fillable = [
        'name',
        'code',
        'warehouse_id',
        'state',
        'lga',
        'community_id',
        'address',
        'status',
        'created_by',
    ];

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(warehouse::class, 'warehouse_id');
    }

    public function community(): BelongsTo
    {
        return $this->belongsTo(Community::class, 'community_id');
    }

    public function stocks(): HasMany
    {
        return $this->hasMany(OneStopShopStock::class, 'one_stop_shop_id');
    }
}
