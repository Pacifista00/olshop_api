<?php

namespace App\Http\Controllers;

use App\Http\Resources\ProductResource;
use App\Models\Product;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $query = Product::with('category')->where('is_active', 1);

        // SEARCH 
        if ($request->filled('search')) {
            $search = $request->search;

            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // FILTER KATEGORI
        if ($request->filled('category')) {
            $query->whereHas('category', function ($q) use ($request) {
                $q->where('slug', $request->category);
            });
        }

        // SORTING
        if ($request->filled('sort')) {
            switch ($request->sort) {
                case 'price_low':
                    $query->orderBy('price', 'asc');
                    break;

                case 'price_high':
                    $query->orderBy('price', 'desc');
                    break;

                case 'name_asc':
                    $query->orderBy('name', 'asc');
                    break;

                default:
                    $query->latest();
            }
        } else {
            $query->latest();
        }

        $products = $query->paginate(12);

        return response()->json([
            'status' => 'success',
            'message' => 'List of products retrieved successfully.',
            'data' => ProductResource::collection($products),
            'meta' => [
                'current_page' => $products->currentPage(),
                'last_page' => $products->lastPage(),
                'per_page' => $products->perPage(),
                'total' => $products->total(),
            ],
        ], 200);
    }
    // GET /products
    public function adminIndex(Request $request)
    {
        $query = Product::with('category');

        // SEARCH 
        if ($request->filled('search')) {
            $search = $request->search;

            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // FILTER KATEGORI
        if ($request->filled('category')) {
            $query->whereHas('category', function ($q) use ($request) {
                $q->where('slug', $request->category);
            });
        }

        // SORTING
        if ($request->filled('sort')) {
            switch ($request->sort) {
                case 'price_low':
                    $query->orderBy('price', 'asc');
                    break;

                case 'price_high':
                    $query->orderBy('price', 'desc');
                    break;

                case 'name_asc':
                    $query->orderBy('name', 'asc');
                    break;

                default:
                    $query->latest();
            }
        } else {
            $query->latest();
        }

        $products = $query->paginate(12);

        return response()->json([
            'status' => 'success',
            'message' => 'List of products retrieved successfully.',
            'data' => ProductResource::collection($products),
            'meta' => [
                'current_page' => $products->currentPage(),
                'last_page' => $products->lastPage(),
                'per_page' => $products->perPage(),
                'total' => $products->total(),
            ],
        ], 200);
    }

    public function homeProducts(Request $request)
    {
        $query = Product::with('category')->where('is_active', 1);

        // FILTER KATEGORI
        if ($request->filled('category')) {
            $query->whereHas('category', function ($q) use ($request) {
                $q->where('slug', $request->category);
            });
        }

        // Ambil maksimal 6 produk (misalnya terbaru)
        $products = $query
            ->latest()
            ->limit(5)
            ->get();

        return response()->json([
            'status' => 'success',
            'message' => 'List of products retrieved successfully.',
            'data' => ProductResource::collection($products),
        ], 200);
    }


    public function getProduct($id)
    {
        $product = Product::with('category')->where('is_active', 1)
            ->findOrFail($id);

        return response()->json([
            'status' => 'success',
            'message' => 'product retrieved successfully.',
            'data' => new ProductResource($product)
        ], 200);
    }
    public function getAdminProduct($id)
    {
        $product = Product::with('category')
            ->findOrFail($id);

        return response()->json([
            'status' => 'success',
            'message' => 'product retrieved successfully.',
            'data' => new ProductResource($product)
        ], 200);
    }
    public function latest()
    {
        $products = Product::with('category')
            ->latest()
            ->limit(12)
            ->where('is_active', 1)
            ->get();

        return response()->json([
            'status' => 'success',
            'message' => 'List of products retrieved successfully.',
            'data' => ProductResource::collection($products)
        ], 200);
    }
    public function bestSeller()
    {
        $products = Product::with('category')
            ->latest()
            ->limit(8)
            ->where('is_active', 1)
            ->get();

        return response()->json([
            'status' => 'success',
            'message' => 'List of products retrieved successfully.',
            'data' => ProductResource::collection($products)
        ], 200);
    }

    // POST /products
    public function store(Request $request)
    {
        $validated = $request->validate([
            'category_id' => 'required|exists:categories,id',
            'name' => 'required|string|max:255|unique:products,name',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'stock' => 'required|integer|min:0',
            'image' => 'nullable|image|max:2048',
            'is_active' => 'boolean',
            'use_dimension' => 'boolean',
            'weight' => 'nullable|numeric|min:0',
            'length' => 'nullable|numeric|min:0',
            'width' => 'nullable|numeric|min:0',
            'height' => 'nullable|numeric|min:0',
        ]);

        DB::beginTransaction();

        try {

            // Handle image upload
            if ($request->hasFile('image')) {
                $validated['image'] = $request->file('image')->store('products', 'public');
            }

            // Create product
            $product = Product::create([
                'category_id' => $validated['category_id'],
                'name' => $validated['name'],
                'slug' => Str::slug($validated['name']),
                'description' => $validated['description'] ?? null,
                'price' => $validated['price'],
                'stock' => $validated['stock'],
                'image' => $validated['image'] ?? null,
                'is_active' => $validated['is_active'] ?? true,
                'weight' => $validated['weight'] ?? null,
                'length' => $validated['length'] ?? null,
                'width' => $validated['width'] ?? null,
                'height' => $validated['height'] ?? null,
                'use_dimension' => $validated['use_dimension'] ?? 0,
            ]);

            if (!$product) {
                throw new \Exception("Gagal membuat produk.");
            }

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Product created successfully.',
                'data' => new ProductResource($product)
            ], 201);

        } catch (\Exception $e) {

            DB::rollBack();

            return response()->json([
                'status' => 'error',
                'message' => 'Gagal membuat produk.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // PUT /products/{product}
    public function update(Request $request, Product $product)
    {
        $validated = $request->validate([
            'category_id' => 'sometimes|exists:categories,id',
            'name' => 'sometimes|string|max:255|unique:products,name,' . $product->id,
            'description' => 'sometimes|string|nullable',
            'price' => 'sometimes|numeric|min:0',
            'stock' => 'sometimes|integer|min:0',
            'image' => 'sometimes|image|max:2048',
            'is_active' => 'boolean',
            'use_dimension' => 'boolean',
            'weight' => 'nullable|numeric|min:0',
            'length' => 'nullable|numeric|min:0',
            'width' => 'nullable|numeric|min:0',
            'height' => 'nullable|numeric|min:0',
        ]);

        DB::beginTransaction();

        try {

            // Replace image if uploaded
            if ($request->hasFile('image')) {
                if ($product->image && Storage::disk('public')->exists($product->image)) {
                    Storage::disk('public')->delete($product->image);
                }

                $validated['image'] = $request->file('image')->store('products', 'public');
            }

            // Update product
            $updated = $product->update([
                'category_id' => $validated['category_id'] ?? $product->category_id,
                'name' => $validated['name'] ?? $product->name,
                'slug' => isset($validated['name']) ? Str::slug($validated['name']) : $product->slug,
                'description' => $validated['description'] ?? $product->description,
                'price' => $validated['price'] ?? $product->price,
                'stock' => $validated['stock'] ?? $product->stock,
                'image' => $validated['image'] ?? $product->image,
                'is_active' => $validated['is_active'] ?? $product->is_active,
                'weight' => $validated['weight'] ?? null,
                'length' => $validated['length'] ?? null,
                'width' => $validated['width'] ?? null,
                'height' => $validated['height'] ?? null,
                'use_dimension' => $validated['use_dimension'] ?? 0,
            ]);

            if (!$updated) {
                throw new \Exception("Gagal mengupdate produk.");
            }

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Product updated successfully.',
                'data' => new ProductResource($product)
            ], 200);

        } catch (\Exception $e) {

            DB::rollBack();

            return response()->json([
                'status' => 'error',
                'message' => 'Gagal mengupdate produk.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // DELETE /products/{product}
    public function destroy(Product $product)
    {
        DB::beginTransaction();

        try {

            // Delete image file
            if ($product->image && Storage::disk('public')->exists($product->image)) {
                Storage::disk('public')->delete($product->image);
            }

            if (!$product->delete()) {
                throw new \Exception("Gagal menghapus produk.");
            }

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Product deleted successfully.',
                'data' => null
            ], 200);

        } catch (\Exception $e) {

            DB::rollBack();

            return response()->json([
                'status' => 'error',
                'message' => 'Gagal menghapus produk.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
