<?php

namespace App\Http\Controllers;

use App\Http\Resources\HotelBookingResource;
use App\Http\Resources\RedeemedProductResource;
use App\Http\Resources\RewardRedemptionResource;
use App\Http\Resources\VoucherResource;
use App\Models\HotelBooking;
use App\Models\RedeemedProduct;
use App\Models\RewardRedemption;
use App\Models\Voucher;
use DB;
use Illuminate\Http\Request;

class RewardRedemptionController extends Controller
{
    public function index(Request $request)
    {
        $redemptions = RewardRedemption::with([
            'reward',
            'hotelBooking'
        ])
            ->where('user_id', $request->user()->id)
            ->latest()
            ->paginate(10);

        return RewardRedemptionResource::collection($redemptions);
    }
    public function adminShow(Request $request)
    {
        $redemptions = RewardRedemption::with([
            'reward',
            'user',
            'hotelBooking',
        ])
            ->latest()
            ->paginate(10);

        return RewardRedemptionResource::collection($redemptions);
    }

    /**
     * Detail redemption
     */
    public function show(Request $request, $id)
    {
        $redemption = RewardRedemption::with([
            'reward',
            'hotelBooking',
            'voucher',
        ])
            ->where('user_id', $request->user()->id)
            ->findOrFail($id);

        return new RewardRedemptionResource($redemption);
    }
    public function adminVoucherReedem()
    {
        $vouchers = Voucher::with('user')
            ->whereNotNull('user_id')
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
    public function adminHotelReedem()
    {
        $bookings = HotelBooking::with([
            'user',
            'reward',
        ])
            ->latest()
            ->paginate(10);

        return response()->json([
            'status' => 'success',
            'message' => 'List hotel booking berhasil diambil',
            'data' => HotelBookingResource::collection($bookings->items()),
            'meta' => [
                'current_page' => $bookings->currentPage(),
                'last_page' => $bookings->lastPage(),
                'per_page' => $bookings->perPage(),
                'total' => $bookings->total(),
            ],
        ]);
    }
    public function adminProductReedem()
    {
        $data = RedeemedProduct::with([
            'user',
            'rewardRedemption'
        ])
            ->latest()
            ->paginate(10);

        return response()->json([
            'status' => 'success',
            'message' => 'List penukaran produk berhasil diambil',
            'data' => RedeemedProductResource::collection($data->items()),
            'meta' => [
                'current_page' => $data->currentPage(),
                'last_page' => $data->lastPage(),
                'per_page' => $data->perPage(),
                'total' => $data->total(),
            ],
        ]);
    }

    public function updateProduct(Request $request, $id)
    {
        DB::beginTransaction();

        try {
            $request->validate([
                'tracking_number' => 'required|string|max:255',
            ]);

            // 🔥 ambil redeemed product
            $redeemedProduct = RedeemedProduct::lockForUpdate()
                ->where('id', $id)
                ->firstOrFail();

            // 🔥 ambil redemption
            $redemption = RewardRedemption::lockForUpdate()
                ->where('id', $redeemedProduct->reward_redemption_id)
                ->firstOrFail();

            // 🔥 cek apakah ini pertama kali complete
            $isFirstComplete = $redeemedProduct->status !== 'completed';

            // =========================
            // ✅ UPDATE REDEEMED PRODUCT
            // =========================
            $redeemedProduct->update([
                'tracking_number' => $request->tracking_number,
                'status' => 'completed', // tetap completed
            ]);

            // =========================
            // ✅ HANDLE DETAILS (AMAN)
            // =========================
            $details = $redemption->details;

            // handle null
            if (is_null($details)) {
                $details = [];
            }

            // handle string JSON
            if (is_string($details)) {
                $decoded = json_decode($details, true);
                $details = is_array($decoded) ? $decoded : [];
            }

            // kalau sudah array biarkan
            if (!is_array($details)) {
                $details = [];
            }

            // update tracking
            $details['tracking_number'] = $request->tracking_number;

            // =========================
            // ✅ UPDATE REDEMPTION
            // =========================
            $redemption->update([
                // hanya set completed saat pertama kali
                'status' => $isFirstComplete ? 'completed' : $redemption->status,
                'details' => $details,
            ]);

            DB::commit();

            return response()->json([
                'message' => $isFirstComplete
                    ? 'Redemption completed successfully'
                    : 'Tracking updated successfully',
                'data' => new RewardRedemptionResource(
                    $redemption->load('redeemedProduct')
                )
            ]);

        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Failed to complete redemption',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    public function updateHotel(Request $request, $id)
    {
        DB::beginTransaction();

        try {
            $request->validate([
                'booking_code' => 'required|string|max:255',
            ]);

            // ✅ ambil hotel booking (BUKAN RedeemedProduct)
            $hotelBooking = HotelBooking::lockForUpdate()
                ->where('id', $id)
                ->firstOrFail();

            // cek pertama kali set booking code
            $isFirstSet = empty($hotelBooking->booking_code);

            // ✅ update hotel booking
            $hotelBooking->update([
                'booking_code' => $request->booking_code,
                'status' => $isFirstSet ? 'booked' : $hotelBooking->status,
            ]);

            // =========================
            // 🔥 SYNC KE REDEMPTION
            // =========================
            $redemption = RewardRedemption::lockForUpdate()
                ->where('id', $hotelBooking->reward_redemption_id)
                ->first();

            if ($redemption) {
                $details = $redemption->details ?? [];

                if (!is_array($details)) {
                    $details = [];
                }

                $details['booking_code'] = $request->booking_code;

                $redemption->update([
                    'details' => $details,
                    'status' => $isFirstSet ? 'completed' : $redemption->status,
                ]);
            }

            DB::commit();

            return response()->json([
                'message' => $isFirstSet
                    ? 'Booking completed successfully'
                    : 'Booking code updated successfully',
                'data' => $hotelBooking
            ]);

        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Failed to update booking',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
