<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Resources\VoucherResource;
use App\Models\Cart;
use App\Models\Voucher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class VoucherController extends Controller
{
    // GET /vouchers
    public function index()
    {
        $vouchers = Voucher::orderByDesc('created_at')->get();

        return response()->json([
            'status' => 'success',
            'message' => 'List of vouchers retrieved successfully.',
            'data' => VoucherResource::collection($vouchers),
        ], 200);
    }

    // POST /vouchers
    public function store(Request $request)
    {
        $validated = $request->validate([
            'code' => 'required|string|max:30|unique:vouchers,code',
            'name' => 'nullable|string|max:255',
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
            $voucher = Voucher::create([
                'id' => Str::uuid(),
                'code' => strtoupper($validated['code']),
                'name' => $validated['name'] ?? null,
                'type' => $validated['type'],
                'value' => $validated['value'],
                'max_discount' => $validated['max_discount'] ?? null,
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
        $voucher = Voucher::where('code', $request->code)->first();

        if (!$voucher) {
            return response()->json([
                'message' => 'Voucher tidak ditemukan'
            ], 422);
        }

        // ===== Ambil cart user =====
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
            fn($item) =>
            $item->product->price * $item->quantity
        );

        // ===== Validasi voucher =====
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

        if ($voucher->min_order_amount && $subtotal < $voucher->min_order_amount) {
            return response()->json([
                'message' => 'Minimal belanja Rp ' .
                    number_format($voucher->min_order_amount, 0, ',', '.')
            ], 422);
        }

        if ($voucher->usage_limit && $voucher->usage_count >= $voucher->usage_limit) {
            return response()->json([
                'message' => 'Kuota voucher sudah habis'
            ], 422);
        }

        // ===== Hitung diskon =====
        if ($voucher->type === 'percentage') {
            $discount = $subtotal * ($voucher->value / 100);
        } else {
            $discount = $voucher->value;
        }

        if ($voucher->max_discount) {
            $discount = min($discount, $voucher->max_discount);
        }

        // ===== Response =====
        return response()->json([
            'voucher' => [
                'code' => $voucher->code,
                'name' => $voucher->name,
                'type' => $voucher->type, // percentage | fixed
                'value' => (int) $voucher->value,
                'max_discount' => $voucher->max_discount
                    ? (int) $voucher->max_discount
                    : null,
                'min_order_amount' => $voucher->min_order_amount
                    ? (int) $voucher->min_order_amount
                    : null,
            ],
            'discount' => (int) $discount,
        ]);
    }
}
