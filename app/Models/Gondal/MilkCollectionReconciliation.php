<?php

namespace App\Models\Gondal;

use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\MilkCollection\Models\MilkCollectionCenter;

class MilkCollectionReconciliation extends Model
{
    use HasFactory;

    protected $table = 'gondal_milk_center_reconciliations';

    protected $fillable = [
        'milk_collection_center_id',
        'project_id',
        'reconciliation_date',
        'total_collections',
        'accepted_collections',
        'rejected_collections',
        'total_quantity',
        'accepted_quantity',
        'rejected_quantity',
        'accepted_value',
        'ledger_value',
        'variance_amount',
        'last_recorded_by',
        'last_collection_at',
        'status',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'reconciliation_date' => 'date',
            'total_quantity' => 'float',
            'accepted_quantity' => 'float',
            'rejected_quantity' => 'float',
            'accepted_value' => 'float',
            'ledger_value' => 'float',
            'variance_amount' => 'float',
            'last_collection_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    public function center(): BelongsTo
    {
        return $this->belongsTo(MilkCollectionCenter::class, 'milk_collection_center_id');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    public function lastRecorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'last_recorded_by');
    }
}
