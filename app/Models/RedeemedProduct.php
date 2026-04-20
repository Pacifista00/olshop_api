<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class RedeemedProduct extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'id',
        'user_id',
        'reward_redemption_id',
        'product_name',
        'address',
        'recipient_name',
        'phone',
        'status',
        'tracking_number',
    ];

    public $incrementing = false;
    protected $keyType = 'string';

    // RELATION
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function rewardRedemption()
    {
        return $this->belongsTo(RewardRedemption::class);
    }
    public function redemption()
    {
        return $this->belongsTo(RewardRedemption::class, 'reward_redemption_id');
    }
}
