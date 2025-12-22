<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $categories = DB::table('categories')->pluck('id');

        for ($i = 1; $i <= 30; $i++) {
            DB::table('products')->insert([
                'id' => Str::uuid(),
                'category_id' => $categories->random(),
                'name' => "Produk $i",
                'slug' => Str::slug("Produk $i"),
                'description' => "Deskripsi produk ke-$i",
                'price' => rand(10000, 500000),
                'stock' => rand(1, 100),
                'image' => "product-$i.jpg",
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
