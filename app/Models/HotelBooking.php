<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class HotelBooking extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'id',
        'user_id',
        'reward_id',
        'reward_redemption_id',
        'hotel_name',
        'room_type',
        'location',
        'check_in',
        'check_out',
        'booking_code',
        'status',
    ];

    public $incrementing = false;
    protected $keyType = 'string';

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function reward()
    {
        return $this->belongsTo(Reward::class);
    }

    public function redemption()
    {
        return $this->belongsTo(RewardRedemption::class, 'reward_redemption_id');
    }
}
