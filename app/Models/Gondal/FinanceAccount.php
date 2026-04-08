<?php

namespace App\Models\Gondal;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FinanceAccount extends Model
{
    use HasFactory;

    protected $table = 'gondal_finance_accounts';

    protected $fillable = [
        'code',
        'name',
        'type',
        'created_by',
        'is_system',
    ];

    protected function casts(): array
    {
        return [
            'is_system' => 'boolean',
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(JournalLine::class, 'finance_account_id');
    }
}
