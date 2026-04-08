<?php

namespace App\Models\Gondal;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GondalNotificationLog extends Model
{
    use HasFactory;

    protected $table = 'gondal_notification_logs';

    protected $fillable = [
        'event_type',
        'recipient_type',
        'recipient_id',
        'channel',
        'message',
        'reference_hash',
        'status',
        'retry_count',
    ];

    protected function casts(): array
    {
        return [
            'retry_count' => 'int',
        ];
    }
}
