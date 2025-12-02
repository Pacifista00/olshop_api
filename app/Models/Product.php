<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Product extends Model
{
    use HasFactory;

    // 1. Menonaktifkan Auto-Increment
    public $incrementing = false;

    // 2. Menentukan tipe Primary Key adalah string
    protected $keyType = 'string';

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->{$model->getKeyName()})) {
                // Menggunakan fungsi Str::uuid() dari helper Laravel
                $model->{$model->getKeyName()} = (string) Str::uuid();
            }
        });
    }

    /**
     * Produk ini dimiliki oleh satu kategori (category).
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Produk ini ada di banyak pesanan (orders) melalui tabel pivot order_items.
     */
    public function orders(): BelongsToMany
    {
        return $this->belongsToMany(Order::class, 'order_items')
            ->withPivot('quantity', 'unit_price')
            ->using(OrderItem::class); // Jika Anda membuat Model untuk OrderItem
    }

    /**
     * Produk ini ada di banyak keranjang (carts) melalui tabel pivot cart_items.
     */
    public function carts(): BelongsToMany
    {
        return $this->belongsToMany(Cart::class, 'cart_items')
            ->withPivot('quantity');
    }
}
