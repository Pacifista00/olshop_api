<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Order extends Model
{
    const STATUS_CREATED = 'created';
    const STATUS_PENDING = 'pending';
    const STATUS_PROCESSING = 'processing';
    const STATUS_PACKED = 'packed';
    const STATUS_SHIPPED = 'shipped';
    const STATUS_COMPLETED = 'completed';
    const STATUS_CANCELLED = 'cancelled';

    const PAYMENT_UNPAID = 'unpaid';
    const PAYMENT_PENDING = 'pending';
    const PAYMENT_PAID = 'paid';
    const PAYMENT_FAILED = 'failed';
    const PAYMENT_EXPIRED = 'expired';

    use HasFactory, HasUuids, SoftDeletes;

    public $incrementing = false;
    protected $keyType = 'string';
    protected $fillable = [
        'user_id',
        'shipping_address_id',
        'customer_name',
        'customer_phone',
        'shipping_address_snapshot',
        'order_number',
        'subtotal_amount',
        'total_amount',
        'shipping_cost',
        'voucher_id',
        'voucher_discount',
        'midtrans_transaction_id',
        'midtrans_snap_token',
        'status',
        'payment_status',
        'payment_method',
        'transaction_time',
        'courier',
        'courier_service',
        'shipping_etd',
        'paid_at',
        'midtrans_response',
        'voucher_usage_counted',
        'points_used',
        'points_discount',
        'points_deducted',
        'biteship_order_id',
        'tracking_number',
        'shipment_created_at',
        'shipment_response',
        'expired_at',
    ];
    protected $casts = [
        'total_amount' => 'decimal:2',
        'shipping_cost' => 'decimal:2',
        'voucher_discount' => 'decimal:2',
        'transaction_time' => 'datetime',
        'midtrans_response' => 'array',
        'shipping_address_snapshot' => 'array',
        'expired_at' => 'datetime',
        'shipment_response' => 'array',
    ];

    protected static function booted()
    {
        static::creating(function ($order) {
            if (
                $order->payment_status === self::PAYMENT_UNPAID
                && empty($order->expired_at)
            ) {
                $order->expired_at = now()->addHour(); // ubah kalau mau 2 jam / 24 jam
            }
        });
    }



    /**
     * Pesanan ini dimiliki oleh satu user (user).
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Pesanan ini merujuk ke satu alamat pengiriman (shippingAddress).
     */
    public function shippingAddress(): BelongsTo
    {
        // FK: shipping_address_id, PK di tabel address: id
        return $this->belongsTo(Address::class, 'shipping_address_id');
    }

    /**
     * Pesanan ini terdiri dari banyak produk (products) melalui order_items.
     */
    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'order_items')
            ->withPivot('quantity', 'unit_price')
            ->using(OrderItem::class); // Jika Anda membuat Model untuk OrderItem
    }

    /**
     * Pesanan ini memiliki banyak catatan transaksi Midtrans.
     */
    public function midtransTransactions(): HasMany
    {
        return $this->hasMany(MidtransTransaction::class);
    }
    public function voucher()
    {
        return $this->belongsTo(Voucher::class);
    }
    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

}
