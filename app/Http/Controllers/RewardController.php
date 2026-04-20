<?php

namespace App\Http\Controllers;

use App\Http\Resources\RewardResource;
use App\Models\Reward;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RewardController extends Controller
{
    public function index()
    {
        try {
            $rewards = Reward::latest()->paginate(10);

            return response()->json([
                'status' => 'success',
                'message' => 'Rewards fetched successfully',
                'data' => RewardResource::collection($rewards)
            ], 200);

        } catch (\Throwable $e) {
            Log::error($e);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch rewards'
            ], 500);
        }
    }

    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string',
                'description' => 'nullable|string',
                'type' => 'required|in:voucher,product,hotel',
                'points_required' => 'required|integer|min:0',
                'stock' => 'nullable|integer|min:0',

                'voucher_type' => 'nullable|in:percentage,fixed',
                'voucher_value' => 'nullable|integer',
                'max_discount' => 'nullable|integer',
                'min_order_amount' => 'nullable|integer',

                'product_name' => 'nullable|string',
                'product_price' => 'nullable|integer',
                'need_shipping' => 'boolean',

                'hotel_name' => 'nullable|string',
                'room_type' => 'nullable|string',
                'location' => 'nullable|string',

                'is_active' => 'boolean',
            ]);

            $reward = DB::transaction(function () use ($validated) {
                return Reward::create($validated);
            });

            return response()->json([
                'status' => 'success',
                'message' => 'Reward created successfully',
                'data' => new RewardResource($reward)
            ], 201);

        } catch (\Throwable $e) {
            Log::error($e);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create reward'
            ], 500);
        }
    }

    public function show($id)
    {
        try {
            $reward = Reward::findOrFail($id);

            return response()->json([
                'status' => 'success',
                'message' => 'Reward fetched successfully',
                'data' => new RewardResource($reward)
            ], 200);

        } catch (\Throwable $e) {
            Log::error($e);

            return response()->json([
                'status' => 'error',
                'message' => 'Reward not found'
            ], 404);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $reward = Reward::findOrFail($id);

            $validated = $request->validate([
                'name' => 'sometimes|required|string',
                'description' => 'nullable|string',
                'type' => 'sometimes|required|in:voucher,product,hotel',
                'points_required' => 'sometimes|required|integer|min:0',
                'stock' => 'nullable|integer|min:0',

                'voucher_type' => 'nullable|in:percentage,fixed',
                'voucher_value' => 'nullable|integer',
                'max_discount' => 'nullable|integer',
                'min_order_amount' => 'nullable|integer',

                'product_name' => 'nullable|string',
                'product_price' => 'nullable|integer',
                'need_shipping' => 'boolean',

                'hotel_name' => 'nullable|string',
                'room_type' => 'nullable|string',
                'location' => 'nullable|string',

                'is_active' => 'boolean',
            ]);

            $reward = DB::transaction(function () use ($reward, $validated) {
                $reward->update($validated);
                return $reward;
            });

            return response()->json([
                'status' => 'success',
                'message' => 'Reward updated successfully',
                'data' => new RewardResource($reward)
            ], 200);

        } catch (\Throwable $e) {
            Log::error($e);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update reward'
            ], 500);
        }
    }

    public function destroy($id)
    {
        try {
            $reward = Reward::findOrFail($id);

            DB::transaction(function () use ($reward) {
                $reward->delete();
            });

            return response()->json([
                'status' => 'success',
                'message' => 'Reward deleted successfully'
            ], 200);

        } catch (\Throwable $e) {
            Log::error($e);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete reward'
            ], 500);
        }
    }
}