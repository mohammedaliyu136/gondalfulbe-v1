<?php

namespace App\Models\Gondal;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ApprovalRule extends Model
{
    use HasFactory;

    protected $table = 'gondal_approval_rules';

    protected $fillable = ['name', 'min_amount', 'max_amount', 'approver_role', 'is_active'];

    protected function casts(): array
    {
        return [
            'min_amount' => 'float',
            'max_amount' => 'float',
            'is_active' => 'bool',
        ];
    }
}
