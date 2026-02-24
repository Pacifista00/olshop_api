<?php

namespace App\Jobs;

use App\Models\Order;
use App\Services\BiteshipService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CreateShipmentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 5;          // retry kalau gagal
    public $backoff = [60, 120, 300]; // retry delay

    public string $orderId;

    public function __construct(string $orderId)
    {
        $this->orderId = $orderId;
    }

    public function handle(): void
    {
        DB::beginTransaction();

        try {
            $order = Order::lockForUpdate()
                ->with(['items.product'])
                ->findOrFail($this->orderId);

            // idempotent guard
            if (
                $order->tracking_number ||
                $order->payment_status !== Order::PAYMENT_PAID
            ) {
                DB::commit();
                return;
            }

            $shipment = BiteshipService::createShipment($order);

            $trackingNumber = data_get($shipment, 'courier.tracking_number');

            $order->update([
                'biteship_order_id' => $shipment['id'] ?? null,
                'tracking_number' => $trackingNumber,
                'shipment_created_at' => now(),
                'status' => $trackingNumber
                    ? Order::STATUS_SHIPPED
                    : Order::STATUS_PROCESSING,
            ]);

            DB::commit();

        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e; // 🔥 penting supaya retry jalan
        }
    }

    public function failed(\Throwable $e): void
    {
        Log::error('CreateShipmentJob failed', [
            'order_id' => $this->orderId,
            'error' => $e->getMessage(),
        ]);
    }
}