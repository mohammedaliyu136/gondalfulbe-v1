<?php

namespace App\Models\Gondal;

use App\Models\Project;
use App\Models\Vender;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JournalLine extends Model
{
    use HasFactory;

    protected $table = 'gondal_journal_lines';

    protected $fillable = [
        'journal_entry_id',
        'finance_account_id',
        'farmer_id',
        'project_id',
        'direction',
        'amount',
        'memo',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'float',
            'meta' => 'array',
        ];
    }

    public function entry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'journal_entry_id');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(FinanceAccount::class, 'finance_account_id');
    }

    public function farmer(): BelongsTo
    {
        return $this->belongsTo(Vender::class, 'farmer_id');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id');
    }
}
