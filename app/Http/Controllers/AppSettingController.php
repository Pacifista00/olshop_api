<?php

namespace App\Http\Controllers;

use App\Http\Resources\AppSettingResource;
use App\Models\AppSetting;
use Illuminate\Http\Request;

class AppSettingController extends Controller
{
    public function index()
    {
        $setting = AppSetting::latest()->first();

        if (!$setting) {
            return response()->json([
                'success' => false,
                'message' => 'App setting not found',
                'data' => null
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Success',
            'data' => new AppSettingResource($setting)
        ]);
    }
}
