<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Category extends Model
{
    use HasFactory, HasUuids;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'name',
        'slug',
        'icon',
    ];

    /**
     * Kategori ini memiliki banyak produk (products).
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    // --- Relasi Self-Referencing (Nested Categories) ---

    /**
     * Kategori ini memiliki satu kategori induk (parent).
     */
    public function parent(): BelongsTo
    {
        // FK: parent_id, PK di tabel ini: id
        return $this->belongsTo(Category::class, 'parent_id');
    }

    /**
     * Kategori ini memiliki banyak sub-kategori (children).
     */
    public function children(): HasMany
    {
        // FK di tabel ini: parent_id
        return $this->hasMany(Category::class, 'parent_id');
    }
}
