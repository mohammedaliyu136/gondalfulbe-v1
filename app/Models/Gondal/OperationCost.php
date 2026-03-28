<?php

namespace App\Models\Gondal;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Cooperatives\Models\Cooperative;

class OperationCost extends Model
{
    use HasFactory;

    protected $table = 'gondal_operation_costs';

    protected $fillable = ['cost_date', 'cooperative_id', 'category', 'amount', 'description', 'status', 'approval_status'];

    protected function casts(): array
    {
        return [
            'cost_date' => 'date',
            'amount' => 'float',
        ];
    }

    public function cooperative(): BelongsTo
    {
        return $this->belongsTo(Cooperative::class, 'cooperative_id');
    }
}
