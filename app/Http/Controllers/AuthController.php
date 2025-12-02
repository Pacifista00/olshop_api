<?php

namespace App\Http\Controllers;

use App\Models\Otp;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        // 1. Validasi input
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:6',
        ]);

        // 2. Buat user (email_verified_at masih null)
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => 'customer', // atau default yang kamu inginkan
        ]);

        // 3. Generate OTP 6 digit
        $otp = rand(100000, 999999);

        // 4. Simpan ke tabel otps (berlaku 10 menit)
        Otp::create([
            'user_id' => $user->id,
            'otp_code' => $otp,
            'expired_at' => now()->addMinutes(10),
            'is_used' => false,
            'type' => 'register',
        ]);

        // 5. Kirim OTP (email / whatsapp / sms)
        // Contoh: Mail::to($user->email)->send(new SendOtpMail($otp));
        // Untuk saat ini, kita cukup return dalam response untuk testing.

        return response()->json([
            'message' => 'Register berhasil, OTP telah dikirim ke email.',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email
            ],
            'otp_debug' => $otp // Hapus di production!
        ], 201);
    }
}
