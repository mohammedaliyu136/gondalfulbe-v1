<?php

namespace App\Models\Gondal;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class JournalEntry extends Model
{
    use HasFactory;

    protected $table = 'gondal_journal_entries';

    protected $fillable = [
        'entry_number',
        'entry_date',
        'entry_type',
        'reference_type',
        'reference_id',
        'source_key',
        'description',
        'status',
        'reversal_of_entry_id',
        'created_by',
        'posted_by',
        'reversed_at',
        'reversed_by',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'entry_date' => 'date',
            'reversed_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(JournalLine::class, 'journal_entry_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function reversalOf(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reversal_of_entry_id');
    }

    public function reversalEntry(): HasOne
    {
        return $this->hasOne(self::class, 'reversal_of_entry_id');
    }

    public function reverser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reversed_by');
    }
}
