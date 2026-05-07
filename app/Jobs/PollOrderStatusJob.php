<?php

namespace App\Jobs;

use App\Models\KmOrder;
use App\Services\AslBelgisi\Orders\OrderService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class PollOrderStatusJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 20;
    public int $backoff = 30;

    public function __construct(public readonly int $kmOrderId) {}

    public function handle(OrderService $service): void
    {
        $order = KmOrder::find($this->kmOrderId);

        if (! $order || $order->isDone()) {
            return;
        }

        $service->refreshOrderStatus($order);
        $order->refresh();

        // Auto-download any buffers that just became READY
        foreach ($order->items()->where('status', 'READY')->get() as $item) {
            try {
                $count = $service->downloadCodes($order, $item);
                Log::channel('aslbelgisi')->info("Auto-downloaded {$count} codes for order {$order->external_order_id}");
            } catch (\Throwable $e) {
                Log::channel('aslbelgisi')->error("Auto-download failed: " . $e->getMessage());
            }
        }

        $order->refresh();

        // Keep polling if still pending
        if (! $order->isDone()) {
            self::dispatch($this->kmOrderId)->delay(now()->addSeconds(30));
        }
    }
}
