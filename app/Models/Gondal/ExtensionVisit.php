<?php

namespace App\Models\Gondal;

use App\Models\Vender;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExtensionVisit extends Model
{
    use HasFactory;

    protected $table = 'gondal_extension_visits';

    protected $fillable = ['farmer_id', 'officer_name', 'topic', 'performance_score', 'visit_date'];

    protected function casts(): array
    {
        return [
            'visit_date' => 'date',
            'performance_score' => 'int',
        ];
    }

    public function farmer(): BelongsTo
    {
        return $this->belongsTo(Vender::class, 'farmer_id');
    }
}
