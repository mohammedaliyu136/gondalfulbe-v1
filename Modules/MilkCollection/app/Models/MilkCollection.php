<?php

namespace Modules\MilkCollection\Models;

use App\Models\Gondal\MilkQualityTest;
use App\Models\Project;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\Vender;
use App\Models\User;

class MilkCollection extends Model
{
    use HasFactory;

    protected $fillable = [
        'batch_id',
        'mcc_id',
        'milk_collection_center_id',
        'farmer_id',
        'cooperative_id',
        'project_id',
        'quantity',
        'fat_percentage',
        'snf_percentage',
        'temperature',
        'quality_grade',
        'rejection_reason',
        'adulteration_test',
        'recorded_by',
        'photo_path',
        'collection_date',
        'unit_price',
        'total_price',
        'status',
        'validated_by',
        'validated_at',
    ];

    protected $casts = [
        'collection_date' => 'datetime',
        'validated_at' => 'datetime',
    ];

    public function assignQualityGrade()
    {
        if ($this->fat_percentage === null || $this->quantity <= 0) {
            return; // Skip grading if not fully filled
        }

        if ($this->adulteration_test === 'failed') {
            $this->quality_grade = 'C';
            $this->rejection_reason = 'Auto-rejected due to failed adulteration test';
            return;
        }

        // Rule for Grade A
        if ($this->fat_percentage > 4 && $this->temperature < 20) {
            $this->quality_grade = 'A';
            $this->rejection_reason = null;
        } 
        // Rule for Grade B
        elseif (($this->fat_percentage >= 3 && $this->fat_percentage <= 4) || ($this->temperature >= 20 && $this->temperature <= 25)) {
            $this->quality_grade = 'B';
            $this->rejection_reason = null;
        } 
        // Rule for Grade C (Auto-rejection)
        else {
            $this->quality_grade = 'C';
            if (empty($this->rejection_reason)) {
                $this->rejection_reason = 'Auto-rejected due to quality metrics';
            }
        }
    }

    public function farmer()
    {
        return $this->belongsTo(Vender::class, 'farmer_id', 'id');
    }

    public function recorder()
    {
        return $this->belongsTo(User::class, 'recorded_by', 'id');
    }

    public function cooperative()
    {
        return $this->belongsTo(\Modules\Cooperatives\Models\Cooperative::class, 'cooperative_id', 'id');
    }

    public function collectionCenter()
    {
        return $this->belongsTo(MilkCollectionCenter::class, 'milk_collection_center_id', 'id');
    }

    public function project()
    {
        return $this->belongsTo(Project::class, 'project_id', 'id');
    }

    public function qualityTest()
    {
        return $this->hasOne(MilkQualityTest::class, 'milk_collection_id', 'id');
    }
}
