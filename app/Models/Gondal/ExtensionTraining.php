<?php

namespace App\Models\Gondal;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ExtensionTraining extends Model
{
    use HasFactory;

    protected $table = 'gondal_extension_trainings';

    protected $fillable = ['title', 'location', 'attendees', 'training_date'];

    protected function casts(): array
    {
        return [
            'attendees' => 'int',
            'training_date' => 'date',
        ];
    }
}
