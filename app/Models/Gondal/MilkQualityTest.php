<?php

namespace App\Models\Gondal;

use App\Models\Project;
use App\Models\User;
use App\Models\Vender;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\MilkCollection\Models\MilkCollection;
use Modules\MilkCollection\Models\MilkCollectionCenter;

class MilkQualityTest extends Model
{
    use HasFactory;

    protected $table = 'gondal_milk_quality_tests';

    protected $fillable = [
        'milk_collection_id',
        'milk_collection_center_id',
        'farmer_id',
        'project_id',
        'tested_by',
        'fat_percentage',
        'snf_percentage',
        'temperature',
        'adulteration_test',
        'quality_grade',
        'is_rejected',
        'rejection_reason',
        'tested_at',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'fat_percentage' => 'float',
            'snf_percentage' => 'float',
            'temperature' => 'float',
            'is_rejected' => 'boolean',
            'tested_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    public function collection(): BelongsTo
    {
        return $this->belongsTo(MilkCollection::class, 'milk_collection_id');
    }

    public function center(): BelongsTo
    {
        return $this->belongsTo(MilkCollectionCenter::class, 'milk_collection_center_id');
    }

    public function farmer(): BelongsTo
    {
        return $this->belongsTo(Vender::class, 'farmer_id');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    public function tester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'tested_by');
    }
}
