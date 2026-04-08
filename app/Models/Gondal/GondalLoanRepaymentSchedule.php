<?php

namespace App\Models\Gondal;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GondalLoanRepaymentSchedule extends Model
{
    use HasFactory;

    protected $table = 'gondal_loan_repayment_schedules';

    protected $fillable = [
        'gondal_loan_id', 'due_date', 'amount_due', 'amount_paid', 'status'
    ];

    protected function casts(): array
    {
        return [
            'due_date' => 'date',
            'amount_due' => 'float',
            'amount_paid' => 'float',
        ];
    }

    public function loan(): BelongsTo
    {
        return $this->belongsTo(GondalLoan::class, 'gondal_loan_id');
    }
}
