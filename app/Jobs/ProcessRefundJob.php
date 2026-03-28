<?php

namespace App\Jobs;

use App\Models\Order;
use App\Models\Product;
use App\Models\UserPoint;
use App\Models\PointHistory;
use App\Services\MidtransService;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessRefundJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $orderId;

    public $tries = 3;
    public $backoff = [10, 30, 60];

    public function __construct($orderId)
    {
        $this->orderId = $orderId;
    }

    public function handle(): void
    {
        $order = Order::find($this->orderId);

        if (!$order)
            return;

        // ✅ idempotent
        if ($order->payment_status !== Order::PAYMENT_REFUND_PENDING) {
            return;
        }

        try {

            Log::info('Refund job start', ['order_id' => $order->id]);

            MidtransService::refund($order->order_number, [
                'amount' => $order->total_amount,
                'reason' => 'Cancel by user'
            ]);

        } catch (\Throwable $e) {

            Log::error('Refund job failed', [
                'order_id' => $order->id,
                'error' => $e->getMessage()
            ]);

            throw $e; // biar retry
        }
    }

    public function failed(\Throwable $e)
    {
        Log::critical('Refund job gagal total', [
            'order_id' => $this->orderId,
            'error' => $e->getMessage()
        ]);
    }
}