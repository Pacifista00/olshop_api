<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class MidtransTransaction extends Model
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
}
