<?php

namespace App\Jobs;

use App\Models\Order;
use App\Services\BiteshipService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\Log;

class CancelBiteshipOrderJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3; // 🔁 retry max 3x
    public int $backoff = 10; // ⏱ delay retry (detik)

    public function __construct(public string $orderId)
    {
    }

    public function handle()
    {
        $order = Order::find($this->orderId);

        // ❌ Order tidak ada
        if (!$order) {
            return;
        }

        // ❌ Tidak ada biteship order
        if (!$order->biteship_order_id) {
            return;
        }

        // ❌ Idempotency → pastikan memang sudah cancel di sistem kita
        if ($order->status !== Order::STATUS_CANCELLED) {
            return;
        }

        // 🚚 Status yang sudah tidak bisa dibatalkan
        $nonCancellableStatuses = [
            'allocated',
            'picked_up',
            'on_delivery',
            'delivered'
        ];

        if (in_array($order->shipping_status, $nonCancellableStatuses)) {
            Log::warning('Skip cancel biteship - already processed', [
                'order_id' => $order->id,
                'shipping_status' => $order->shipping_status
            ]);
            return;
        }

        try {
            app(BiteshipService::class)
                ->cancelOrder($order->biteship_order_id);

            Log::info('Biteship order cancelled', [
                'order_id' => $order->id
            ]);

        } catch (\Throwable $e) {

            Log::error('Failed cancel biteship', [
                'order_id' => $order->id,
                'error' => $e->getMessage()
            ]);

            // 🔁 Retry kalau error (network / timeout)
            throw $e;
        }
    }
}