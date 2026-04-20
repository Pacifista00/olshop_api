<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Reward extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'name',
        'description',
        'type',
        'points_required',
        'stock',
        'redeemed_count',

        'voucher_type',
        'voucher_value',
        'max_discount',
        'min_order_amount',

        'product_name',
        'product_price',
        'need_shipping',

        'hotel_name',
        'room_type',
        'location',

        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'need_shipping' => 'boolean',
        'starts_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function redemptions()
    {
        return $this->hasMany(RewardRedemption::class);
    }
    public function hotelBookings()
    {
        return $this->hasMany(HotelBooking::class);
    }
}
