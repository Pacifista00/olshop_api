<?php

namespace App\Http\Controllers;

use App\Services\RewardService;
use Illuminate\Http\Request;

class RedeemController extends Controller
{
    protected $redeemService;

    public function __construct(RewardService $redeemService)
    {
        $this->redeemService = $redeemService;
    }

    public function redeem(Request $request, $id)
    {
        try {
            $result = $this->redeemService->redeem(
                auth()->user(),
                $id,
                $request->all()
            );

            return response()->json([
                'success' => true,
                'message' => 'Redeem berhasil',
                'data' => $result->load([
                    'hotelBooking',
                    'redeemedProduct',
                    'reward'
                ])
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }
}
