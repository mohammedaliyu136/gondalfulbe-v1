<?php

namespace App\Models\Gondal;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ObligationInstallment extends Model
{
    use HasFactory;

    protected $table = 'gondal_obligation_installments';

    protected $fillable = [
        'obligation_id',
        'due_date',
        'amount_due',
        'amount_paid',
        'status',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'due_date' => 'date',
            'amount_due' => 'float',
            'amount_paid' => 'float',
            'meta' => 'array',
        ];
    }

    public function obligation(): BelongsTo
    {
        return $this->belongsTo(Obligation::class, 'obligation_id');
    }
}
