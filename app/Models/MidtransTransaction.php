<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class MidtransTransaction extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    public $incrementing = false;
    protected $keyType = 'string';
    protected $fillable = [
        'order_id',
        'midtrans_transaction_id',
        'status_code',
        'transaction_status',
        'payment_type',
        'va_number',
        'json_data',
    ];
    protected $casts = [
        'json_data' => 'array',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}
