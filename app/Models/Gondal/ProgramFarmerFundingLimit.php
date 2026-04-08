<?php

namespace App\Models\Gondal;

use App\Models\Project;
use App\Models\User;
use App\Models\Vender;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProgramFarmerFundingLimit extends Model
{
    use HasFactory;

    protected $table = 'gondal_program_farmer_funding_limits';

    protected $fillable = [
        'project_id',
        'farmer_id',
        'limit_amount',
        'status',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'limit_amount' => 'float',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    public function farmer(): BelongsTo
    {
        return $this->belongsTo(Vender::class, 'farmer_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
