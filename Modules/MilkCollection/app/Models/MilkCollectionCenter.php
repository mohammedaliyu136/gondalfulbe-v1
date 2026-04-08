<?php

namespace Modules\MilkCollection\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class MilkCollectionCenter extends Model
{
    use HasFactory;

    protected $table = 'milk_collection_centers';

    protected $fillable = [
        'name',
        'location',
        'contact_number',
        'created_by',
    ];

    public function collections()
    {
        return $this->hasMany(MilkCollection::class, 'milk_collection_center_id', 'id');
    }

    public function reconciliations()
    {
        return $this->hasMany(\App\Models\Gondal\MilkCollectionReconciliation::class, 'milk_collection_center_id', 'id');
    }
}
