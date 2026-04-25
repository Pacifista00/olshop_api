<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Resources\VoucherResource;
use App\Models\Cart;
use App\Models\Voucher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class VoucherController extends Controller
{
    // GET /vouchers
    public function adminIndex()
    {
        $vouchers = Voucher::orderByDesc('created_at')->paginate(12);

        return response()->json([
            'status' => 'success',
            'message' => 'List of vouchers retrieved successfully.',
            'data' => VoucherResource::collection($vouchers->items()),
            'meta' => [
                'current_page' => $vouchers->currentPage(),
                'last_page' => $vouchers->lastPage(),
                'per_page' => $vouchers->perPage(),
                'total' => $vouchers->total(),
            ],
        ], 200);
    }
    public function index()
    {
        $user = auth()->user();

        $vouchers = Voucher::where('user_id', $user->id)
            ->where('is_active', 1)
            ->orderByDesc('created_at')
            ->paginate(12);

        return response()->json([
            'status' => 'success',
            'message' => 'List of vouchers retrieved successfully.',
            'data' => VoucherResource::collection($vouchers->items()),
            'meta' => [
                'current_page' => $vouchers->currentPage(),
                'last_page' => $vouchers->lastPage(),
                'per_page' => $vouchers->perPage(),
                'total' => $vouchers->total(),
            ],
        ], 200);
    }


    public function getVoucher($id)
    {
        $voucher = Voucher::where('is_active', 1)->findOrFail($id);

        return response()->json([
            'status' => 'success',
            'message' => 'Voucher retrieved successfully.',
            'data' => new VoucherResource($voucher)
        ], 200);
    }
    public function getAdminVoucher($id)
    {
        $voucher = Voucher::findOrFail($id);

        return response()->json([
            'status' => 'success',
            'message' => 'Voucher retrieved successfully.',
            'data' => new VoucherResource($voucher)
        ], 200);
    }

    // POST /vouchers
    public function store(Request $request)
    {
        $validated = $request->validate([
            'code' => 'required|string|max:30|unique:vouchers,code',
            'name' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'visibility' => 'required|in:public,hidden',
            'type' => 'required|in:percentage,fixed',
            'value' => 'required|numeric|min:0',
            'max_discount' => 'nullable|numeric|min:0',
            'min_order_amount' => 'nullable|numeric|min:0',
            'usage_limit' => 'nullable|integer|min:1',
            'starts_at' => 'nullable|date',
            'expires_at' => 'nullable|date|after:starts_at',
            'is_active' => 'boolean',
        ]);

        DB::beginTransaction();
        $type = $validated['type'];
        $value = $validated['value'];

        $maxDiscount = $validated['max_discount'] ?? null;

        // Jika fixed → max_discount = value
        if ($type === 'fixed') {
            $maxDiscount = $value;
        }

        try {
            $voucher = Voucher::create([
                'id' => Str::uuid(),
                'code' => strtoupper($validated['code']),
                'name' => $validated['name'] ?? null,
                'description' => $validated['description'],
                'visibility' => $validated['visibility'],
                'type' => $validated['type'],
                'value' => $validated['value'],
                'max_discount' => $maxDiscount,
                'min_order_amount' => $validated['min_order_amount'] ?? null,
                'usage_limit' => $validated['usage_limit'] ?? null,
                'starts_at' => $validated['starts_at'] ?? null,
                'expires_at' => $validated['expires_at'] ?? null,
                'is_active' => $validated['is_active'] ?? true,
            ]);

            if (!$voucher) {
                throw new \Exception('Gagal membuat voucher.');
            }

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Voucher created successfully.',
                'data' => new VoucherResource($voucher),
            ], 201);

        } catch (\Exception $e) {

            DB::rollBack();

            Log::error('Gagal membuat voucher', [
                'error_message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request' => $request->all(),
                'user_id' => optional($request->user())->id
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Gagal membuat voucher.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    // GET /vouchers/{voucher}
    public function show(Voucher $voucher)
    {
        $user = auth()->user();

        // Cek aktif
        if (!$voucher->is_active) {
            return response()->json([
                'status' => 'error',
                'message' => 'Voucher not found.',
            ], 404);
        }

        // Kalau voucher public → bebas akses
        if ($voucher->visibility === 'public') {
            return response()->json([
                'status' => 'success',
                'message' => 'Voucher retrieved successfully.',
                'data' => new VoucherResource($voucher),
            ], 200);
        }

        // Kalau hidden/private → cek apakah milik user
        $isOwned = $voucher->redemptions()
            ->where('user_id', $user->id)
            ->exists();

        if (!$isOwned) {
            return response()->json([
                'status' => 'error',
                'message' => 'Voucher not found.',
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Voucher retrieved successfully.',
            'data' => new VoucherResource($voucher),
        ], 200);
    }

    // PUT /vouchers/{voucher}
    public function update(Request $request, Voucher $voucher)
    {
        $validated = $request->validate([
            'code' => 'required|string|max:30|unique:vouchers,code,' . $voucher->id,
            'name' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'visibility' => 'required|in:public,hidden',
            'type' => 'required|in:percentage,fixed',
            'value' => 'required|numeric|min:0',
            'max_discount' => 'nullable|numeric|min:0',
            'min_order_amount' => 'nullable|numeric|min:0',
            'usage_limit' => 'nullable|integer|min:1',
            'starts_at' => 'nullable|date',
            'expires_at' => 'nullable|date|after:starts_at',
            'is_active' => 'boolean',
        ]);

        DB::beginTransaction();

        try {
            $updated = $voucher->update([
                'code' => strtoupper($validated['code']),
                'name' => $validated['name'] ?? null,
                'description' => $validated['description'],
                'visibility' => $validated['visibility'],
                'type' => $validated['type'],
                'value' => $validated['value'],
                'max_discount' => $validated['max_discount'] ?? null,
                'min_order_amount' => $validated['min_order_amount'] ?? null,
                'usage_limit' => $validated['usage_limit'] ?? null,
                'starts_at' => $validated['starts_at'] ?? null,
                'expires_at' => $validated['expires_at'] ?? null,
                'is_active' => $validated['is_active'] ?? $voucher->is_active,
            ]);

            if (!$updated) {
                throw new \Exception('Gagal mengupdate voucher.');
            }

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Voucher updated successfully.',
                'data' => new VoucherResource($voucher),
            ], 200);

        } catch (\Exception $e) {

            DB::rollBack();

            Log::error('Gagal mengubah voucher', [
                'error_message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request' => $request->all(),
                'user_id' => optional($request->user())->id
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Gagal mengupdate voucher.',
                // 'error' => $e->getMessage(),
            ], 500);
        }
    }

    // DELETE /vouchers/{voucher}
    public function destroy(Voucher $voucher)
    {
        DB::beginTransaction();

        try {
            if (!$voucher->delete()) {
                throw new \Exception('Gagal menghapus voucher.');
            }

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Voucher deleted successfully.',
                'data' => null,
            ], 200);

        } catch (\Exception $e) {

            DB::rollBack();

            return response()->json([
                'status' => 'error',
                'message' => 'Gagal menghapus voucher.',
            ], 500);
        }
    }
    public function preview(Request $request)
    {
        $request->validate([
            'code' => 'required|string',
        ]);

        // ===== Ambil voucher =====
        $voucher = Voucher::where('code', $request->code)
            ->where(function ($q) {
                $q->whereNull('user_id')
                    ->orWhere('user_id', auth()->id());
            })
            ->first();

        if (!$voucher) {
            return response()->json([
                'message' => 'Voucher tidak valid atau tidak dapat digunakan'
            ], 422);
        }

        // ===== Ambil cart =====
        $cart = Cart::where('user_id', auth()->id())
            ->with('items.product')
            ->first();

        if (!$cart || $cart->items->isEmpty()) {
            return response()->json([
                'message' => 'Keranjang kosong'
            ], 422);
        }

        // ===== Hitung subtotal =====
        $subtotal = $cart->items->sum(
            fn($item) => $item->product->price * $item->quantity
        );

        // ===== Validasi dasar voucher =====
        if (!$voucher->is_active) {
            return response()->json([
                'message' => 'Voucher tidak aktif'
            ], 422);
        }

        if ($voucher->starts_at && now()->lt($voucher->starts_at)) {
            return response()->json([
                'message' => 'Voucher belum berlaku'
            ], 422);
        }

        if ($voucher->expires_at && now()->gt($voucher->expires_at)) {
            return response()->json([
                'message' => 'Voucher sudah kadaluarsa'
            ], 422);
        }

        if ($voucher->usage_limit && $voucher->usage_count >= $voucher->usage_limit) {
            return response()->json([
                'message' => 'Kuota voucher sudah habis'
            ], 422);
        }

        /**
         * =========================================
         * AUTO DISCOUNT (HARUS DULUAN)
         * =========================================
         */
        $productDiscount = (int) floor($subtotal * 0.025);
        $afterAutoDiscount = $subtotal - $productDiscount;

        /**
         * =========================================
         * VALIDASI MIN ORDER (PAKAI AFTER DISCOUNT)
         * =========================================
         */
        if (
            $voucher->min_order_amount &&
            $afterAutoDiscount < $voucher->min_order_amount
        ) {
            return response()->json([
                'message' => 'Minimal belanja Rp ' .
                    number_format($voucher->min_order_amount, 0, ',', '.')
            ], 422);
        }

        /**
         * =========================================
         * HITUNG DISKON (PAKAI AFTER DISCOUNT)
         * =========================================
         */
        if ($voucher->type === 'percentage') {
            $discount = (int) floor(
                $afterAutoDiscount * ($voucher->value / 100)
            );
        } else {
            $discount = (int) $voucher->value;
        }

        // max discount
        if ($voucher->max_discount) {
            $discount = min($discount, $voucher->max_discount);
        }

        // tidak boleh lebih besar dari subtotal setelah auto discount
        $discount = min($discount, $afterAutoDiscount);

        /**
         * =========================================
         * RESPONSE
         * =========================================
         */
        return response()->json([
            'voucher' => [
                'code' => $voucher->code,
                'name' => $voucher->name,
                'type' => $voucher->type,
                'value' => (int) $voucher->value,
                'max_discount' => $voucher->max_discount
                    ? (int) $voucher->max_discount
                    : null,
                'min_order_amount' => $voucher->min_order_amount
                    ? (int) $voucher->min_order_amount
                    : null,
            ],
            'discount' => $discount,
        ]);
    }
}
