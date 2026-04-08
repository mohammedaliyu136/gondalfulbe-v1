<?php

namespace App\Models\Gondal;

use App\Models\Vender;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Community extends Model
{
    use HasFactory;

    protected $table = 'gondal_communities';

    protected $fillable = [
        'name',
        'state',
        'lga',
        'code',
        'status',
    ];

    public function farmers(): HasMany
    {
        return $this->hasMany(Vender::class, 'community_id');
    }

    public function agents(): HasMany
    {
        return $this->hasMany(AgentProfile::class, 'community_id');
    }
}
