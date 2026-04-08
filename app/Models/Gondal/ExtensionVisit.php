<?php

namespace App\Models\Gondal;

use App\Models\Vender;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ExtensionVisit extends Model
{
    use HasFactory;

    protected $table = 'gondal_extension_visits';

    protected $fillable = ['farmer_id', 'agent_profile_id', 'technician_user_id', 'officer_name', 'topic', 'status', 'performance_score', 'notes', 'visit_date'];

    protected function casts(): array
    {
        return [
            'visit_date' => 'date',
            'performance_score' => 'int',
        ];
    }

    public function farmer(): BelongsTo
    {
        return $this->belongsTo(Vender::class, 'farmer_id');
    }

    public function agentProfile(): BelongsTo
    {
        return $this->belongsTo(AgentProfile::class, 'agent_profile_id');
    }

    public function sale(): HasOne
    {
        return $this->hasOne(InventorySale::class, 'extension_visit_id');
    }

    public function technician(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'technician_user_id');
    }
}
