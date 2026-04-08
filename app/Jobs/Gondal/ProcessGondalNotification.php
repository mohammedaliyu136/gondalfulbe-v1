<?php

namespace App\Jobs\Gondal;

use App\Models\Gondal\GondalNotificationLog;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessGondalNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;

    public function __construct(public int $notificationId) {}

    public function handle(): void
    {
        $notification = GondalNotificationLog::find($this->notificationId);
        if (!$notification || $notification->status === 'delivered') {
            return;
        }

        try {
            Log::info("Mock Delivering Notification [{$notification->channel}] to {$notification->recipient_type} #{$notification->recipient_id}: {$notification->message}");
            $notification->update(['status' => 'delivered']);
        } catch (\Exception $e) {
            $notification->increment('retry_count');
            if ($notification->retry_count >= $this->tries) {
                $notification->update(['status' => 'failed']);
            }
            throw $e;
        }
    }
}
