<?php

namespace Modules\MilkCollection\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class MilkCollectionCenter extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'location',
        'contact_number',
        'created_by',
    ];
}
