<?php

namespace App\Http\Controllers;

use App\Http\Resources\CategoryResource;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CategoryController extends Controller
{
    // GET /categories
    public function index()
    {
        $categories = Category::all();

        return response()->json([
            'status' => 'success',
            'message' => 'List of categories retrieved successfully.',
            'data' => CategoryResource::collection($categories)
        ], 200);
    }

    // POST /categories
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:categories,name',
            'icon' => 'nullable|image|mimes:png,jpg,jpeg,svg,webp|max:2048',
        ]);

        DB::beginTransaction();

        try {
            $iconPath = null;

            if ($request->hasFile('icon')) {
                $iconPath = $request->file('icon')->store(
                    'category_icon',
                    'public'
                );
            }

            $category = Category::create([
                'name' => $validated['name'],
                'slug' => Str::slug($validated['name']),
                'icon' => $iconPath,
            ]);

            if (!$category) {
                throw new \Exception("Gagal membuat kategori.");
            }

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Category created successfully.',
                'data' => new CategoryResource($category)
            ], 201);

        } catch (\Exception $e) {

            DB::rollBack();

            if ($iconPath && Storage::disk('public')->exists($iconPath)) {
                Storage::disk('public')->delete($iconPath);
            }

            return response()->json([
                'status' => 'error',
                'message' => 'Gagal membuat kategori.',
                'error' => $e->getMessage(),

                // 'error' => $e->getMessage(),
            ], 500);
        }
    }

    // PUT /categories/{category}
    public function update(Request $request, Category $category)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:categories,name,' . $category->id,
            'icon' => 'nullable|image|mimes:png,jpg,jpeg,svg,webp|max:2048',
        ]);

        DB::beginTransaction();

        try {
            $iconPath = $category->icon; // simpan icon lama

            // jika ada icon baru
            if ($request->hasFile('icon')) {

                // upload icon baru
                $iconPath = $request->file('icon')->store(
                    'category_icon',
                    'public'
                );

                // hapus icon lama
                if ($category->icon && Storage::disk('public')->exists($category->icon)) {
                    Storage::disk('public')->delete($category->icon);
                }
            }

            $updated = $category->update([
                'name' => $validated['name'],
                'slug' => Str::slug($validated['name']),
                'icon' => $iconPath,
            ]);

            if (!$updated) {
                throw new \Exception("Gagal mengupdate kategori.");
            }

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Category updated successfully.',
                'data' => new CategoryResource($category)
            ], 200);

        } catch (\Exception $e) {

            DB::rollBack();

            if (
                isset($iconPath) &&
                $iconPath !== $category->icon &&
                Storage::disk('public')->exists($iconPath)
            ) {
                Storage::disk('public')->delete($iconPath);
            }

            return response()->json([
                'status' => 'error',
                'message' => 'Gagal mengupdate kategori.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    // DELETE /categories/{category}
    public function destroy(Category $category)
    {
        DB::beginTransaction();

        try {
            $iconPath = $category->icon; // simpan path icon

            if (!$category->delete()) {
                throw new \Exception("Gagal menghapus kategori.");
            }

            // hapus icon dari storage setelah data berhasil dihapus
            if ($iconPath && Storage::disk('public')->exists($iconPath)) {
                Storage::disk('public')->delete($iconPath);
            }

            if (!$category->delete()) {
                throw new \Exception("Gagal menghapus kategori.");
            }

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Category deleted successfully.',
                'data' => null
            ], 200);

        } catch (\Exception $e) {

            DB::rollBack();

            return response()->json([
                'status' => 'error',
                'message' => 'Gagal menghapus kategori.',
                // 'error' => $e->getMessage(),
            ], 500);
        }
    }
}
