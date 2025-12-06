<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Otp;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Carbon\Carbon;

class ForgotPasswordController extends Controller
{
    /**
     * 1. Kirim OTP ke email user
     */
    public function sendOtp(Request $request)
    {
        $request->validate([
            'email' => 'required|email'
        ]);

        $user = User::where('email', $request->email)->first();

        Otp::where('user_id', $user->id)
            ->where('type', 'forgot_password')
            ->delete();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Email tidak ditemukan'
            ], 404);
        }

        // Generate OTP
        $otp = rand(100000, 999999);

        // Simpan ke table otps
        $lastOtp = Otp::where('user_id', $user->id)
            ->where('type', 'forgot_password')
            ->orderBy('created_at', 'desc')
            ->first();

        if ($lastOtp && $lastOtp->created_at->diffInSeconds(now()) < 60) {
            return response()->json([
                'success' => false,
                'message' => 'OTP hanya bisa dikirim setiap 1 menit.'
            ], 429);
        }

        // Kirim email OTP
        try {
            Mail::raw("Kode OTP reset password Anda adalah: $otp", function ($message) use ($user) {
                $message->to($user->email)
                    ->subject('OTP Reset Password');
            });
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengirim OTP, coba lagi.'
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'OTP telah dikirim ke email'
        ]);
    }

    /**
     * 2. Verifikasi OTP → generate reset_token
     */
    public function verifyOtp(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'otp' => 'required|digits:6'
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Email tidak ditemukan'
            ], 404);
        }

        $otpData = Otp::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->where('otp_code', $request->otp)
            ->where('type', 'forgot_password')
            ->where('is_used', false)
            ->first();

        if (!$otpData) {
            return response()->json([
                'success' => false,
                'message' => 'OTP tidak valid'
            ], 400);

        }

        if (Carbon::now()->greaterThan($otpData->expired_at)) {
            $otpData->delete();

            return response()->json([
                'success' => false,
                'message' => 'OTP expired'
            ], 400);
        }

        // Buat reset_token (UUID aman)
        $resetToken = Str::uuid()->toString();

        $otpData->is_used = true;
        $otpData->reset_token = $resetToken;
        $otpData->reset_token_expired = Carbon::now()->addMinutes(10);
        $otpData->save();

        return response()->json([
            'success' => true,
            'message' => 'OTP valid. Gunakan reset token ini untuk reset password.',
            'reset_token' => $resetToken
        ]);
    }

    public function resetPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|min:6|confirmed',
            'reset_token' => 'required'
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User tidak ditemukan'
            ], 404);
        }

        $otpData = Otp::where('user_id', $user->id)
            ->where('reset_token', $request->reset_token)
            ->where('type', 'forgot_password')
            ->orderBy('created_at', 'desc')
            ->first();

        if (!$otpData) {
            return response()->json([
                'success' => false,
                'message' => 'Reset token tidak valid'
            ], 400);
        }

        if (Carbon::now()->greaterThan($otpData->reset_token_expired)) {
            return response()->json([
                'success' => false,
                'message' => 'Reset token expired'
            ], 400);
        }

        // Update password
        $user->password = Hash::make($request->password);
        $user->save();

        // Token hanya sekali pakai
        $otpData->reset_token = null;
        $otpData->reset_token_expired = null;
        $otpData->save();

        return response()->json([
            'success' => true,
            'message' => 'Password berhasil direset'
        ]);
    }
}
