<?php

namespace App\Models\Gondal;

use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PaymentBatch extends Model
{
    use HasFactory;

    protected $table = 'gondal_payment_batches';

    protected $fillable = ['name', 'payee_type', 'project_id', 'period_start', 'period_end', 'status', 'total_amount'];

    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'total_amount' => 'float',
        ];
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class, 'batch_id');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id');
    }
}
