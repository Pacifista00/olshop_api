<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Address extends Model
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

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Alamat ini bisa menjadi alamat pengiriman untuk banyak pesanan (orders).
     * Gunakan 'shipping_address_id' karena nama FK di tabel orders berbeda dari konvensi.
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'shipping_address_id');
    }
}
