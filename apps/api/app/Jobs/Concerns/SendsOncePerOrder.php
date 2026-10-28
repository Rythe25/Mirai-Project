<?php

namespace App\Jobs\Concerns;

use App\Models\NotificationLog;
use Closure;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Throwable;

trait SendsOncePerOrder
{
    /**
     * Runs $send once per order and channel. notification_log records the result; a failure is
     * logged and rethrown so the queue retries it. The lock stops a retry and a duplicate job racing.
     */
    protected function sendOnce(int $orderId, string $channel, Closure $send): void
    {
        Cache::lock("notify-{$orderId}-{$channel}", 30)->block(10, function () use ($orderId, $channel, $send) {
            $log = NotificationLog::firstOrCreate(['order_id' => $orderId, 'channel' => $channel]);

            if ($log->status === 'sent') {
                return;
            }

            $log->increment('attempts');

            try {
                $send();
            } catch (Throwable $e) {
                $log->update(['status' => 'failed', 'last_error' => Str::limit($e->getMessage(), 500)]);

                throw $e; // the queue retries it
            }

            $log->update(['status' => 'sent', 'sent_at' => now(), 'last_error' => null]);
        });
    }
}
