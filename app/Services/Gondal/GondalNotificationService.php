<?php

namespace App\Services\Gondal;

use App\Jobs\Gondal\ProcessGondalNotification;
use App\Models\Gondal\GondalNotificationLog;

class GondalNotificationService
{
    public function queueNotification(string $eventType, string $recipientType, int $recipientId, string $channel, string $message, string $referenceKey): ?GondalNotificationLog
    {
        $hash = md5("{$recipientType}_{$recipientId}_{$eventType}_{$referenceKey}");

        if (GondalNotificationLog::where('reference_hash', $hash)->exists()) {
            return null;
        }

        $log = GondalNotificationLog::create([
            'event_type' => $eventType,
            'recipient_type' => $recipientType,
            'recipient_id' => $recipientId,
            'channel' => $channel,
            'message' => $message,
            'reference_hash' => $hash,
            'status' => 'queued',
        ]);

        ProcessGondalNotification::dispatch($log->id);

        return $log;
    }
}
