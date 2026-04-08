<?php

namespace App\Models\Gondal;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphOne;

class GondalLoanDisbursement extends Model
{
    use HasFactory;

    protected $table = 'gondal_loan_disbursements';

    protected $fillable = [
        'gondal_loan_id', 'disbursal_date', 'amount', 'status', 'disbursed_by', 'notes'
    ];

    protected function casts(): array
    {
        return [
            'disbursal_date' => 'date',
            'amount' => 'float',
        ];
    }

    public function loan(): BelongsTo
    {
        return $this->belongsTo(GondalLoan::class, 'gondal_loan_id');
    }

    public function disburser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'disbursed_by');
    }

    public function obligation(): MorphOne
    {
        return $this->morphOne(Obligation::class, 'source');
    }
}
