<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class RewardRedemption extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $casts = [
        'details' => 'array',
    ];

    protected $fillable = [
        'user_id',
        'reward_id',
        'voucher_id',
        'points_used',
        'status',
        'phone',
        'details',
        'reference_code',
        'redeemed_at',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function reward()
    {
        return $this->belongsTo(Reward::class);
    }

    public function voucher()
    {
        return $this->belongsTo(Voucher::class, 'voucher_id');
    }

    public function hotelBooking()
    {
        return $this->hasOne(HotelBooking::class, 'reward_redemption_id');
    }
    public function redeemedProduct()
    {
        return $this->hasOne(RedeemedProduct::class, 'reward_redemption_id');
    }
}
