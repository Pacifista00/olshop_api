<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    use HasFactory, HasUuids;

    public $incrementing = false;
    protected $keyType = 'string';
    protected $fillable = [
        'user_id',
        'shipping_address_id',
        'order_number',
        'total_amount',
        'shipping_cost',
        'midtrans_transaction_id',
        'midtrans_snap_token',
        'status',
        'payment_status',
        'payment_method',
        'transaction_time',
    ];


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
}
