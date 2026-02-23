<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Voucher extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'code',
        'name',
        'description',
        'visibility',

        // Diskon
        'type',
        'value',

        // Batasan
        'max_discount',
        'min_order_amount',

        // Kuota
        'usage_limit',
        'usage_count',

        // Periode
        'starts_at',
        'expires_at',

        // Status
        'is_active',
    ];


    public function orders()
    {
        return $this->hasMany(Order::class);
    }
}
