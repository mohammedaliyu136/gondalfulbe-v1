<?php

namespace App\Models\Gondal;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BusinessRule extends Model
{
    use HasFactory;

    protected $table = 'gondal_business_rules';

    protected $fillable = [
        'scope_type',
        'scope_id',
        'rule_key',
        'rule_value',
        'description',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'scope_id' => 'int',
            'rule_value' => 'array',
        ];
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
