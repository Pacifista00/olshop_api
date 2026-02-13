<?php

namespace App\Http\Controllers;

use App\Mail\OtpMail;
use App\Models\Otp;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function me(Request $request)
    {
        $user = $request->user()->load('point');


        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated'
            ], 401);
        }

        return response()->json([
            'success' => true,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'gender' => $user->gender,
                'role' => $user->role,
                'status' => $user->status,
                'points' => $user->point?->total_points ?? 0,
                'email_verified_at' => $user->email_verified_at,
                'created_at' => $user->created_at,
            ]
        ]);
    }

    public function register(Request $request)
    {
        // 1. Validasi input
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'phone' => 'nullable|string|max:20|unique:users,phone',
            'password' => 'required|string|min:6|confirmed',
            'gender' => 'nullable|in:male,female',
        ]);

        DB::beginTransaction();

        try {
            // 2. Create user
            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'phone' => $validated['phone'] ?? null,
                'password' => Hash::make($validated['password']),
                'gender' => $validated['gender'] ?? null,
                'role' => 'customer',
                'status' => 'active',
            ]);

            if (!$user) {
                throw new \Exception("Gagal membuat user.");
            }

            // 3. Generate OTP
            $otp = rand(100000, 999999);

            // 4. Simpan OTP
            $otpRecord = Otp::create([
                'user_id' => $user->id,
                'otp_code' => $otp,
                'expired_at' => now()->addMinutes(5),
                'is_used' => false,
                'type' => 'register',
            ]);

            if (!$otpRecord) {
                throw new \Exception("Gagal menyimpan OTP.");
            }

            // 5. Kirim email OTP
            try {
                Mail::to($user->email)->queue(new OtpMail($otp));
            } catch (\Exception $e) {
                // rollback user & otp
                DB::rollBack();

                return response()->json([
                    'status' => 'error',
                    'message' => 'Gagal mengirim email OTP.',
                    'error' => $e->getMessage(), // debug
                ], 500);
            }

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Register berhasil, OTP telah dikirim ke email.',
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                ],
            ], 201);

        } catch (\Exception $e) {

            DB::rollBack();

            return response()->json([
                'status' => 'error',
                'message' => 'Register gagal.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
    public function verifyOtp(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'otp' => 'required|digits:6',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User tidak ditemukan'
            ], 404);
        }

        // Ambil OTP terbaru yang belum digunakan
        $otpData = Otp::where('user_id', $user->id)
            ->where('is_used', false)
            ->latest()
            ->first();

        if (!$otpData) {
            return response()->json([
                'success' => false,
                'message' => 'OTP tidak ditemukan'
            ], 404);
        }

        // Cek OTP kadaluarsa
        if (now()->greaterThan($otpData->expired_at)) {
            return response()->json([
                'success' => false,
                'message' => 'OTP kadaluarsa'
            ], 400);
        }

        // Cek OTP cocok
        if ($otpData->otp_code !== $request->otp) {
            return response()->json([
                'success' => false,
                'message' => 'OTP salah'
            ], 400);
        }

        DB::transaction(function () use ($user, $otpData) {
            // Verifikasi user
            $user->update([
                'email_verified_at' => now()
            ]);

            // Tandai OTP sebagai used
            $otpData->update([
                'is_used' => true
            ]);
        });

        return response()->json([
            'success' => true,
            'message' => 'Verifikasi berhasil, akun aktif',
        ]);
    }
    public function resendOtp(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        $user = User::where('email', $request->email)->firstOrFail();

        if ($user->email_verified_at) {
            return response()->json([
                'success' => false,
                'message' => 'Akun anda sudah terverifikasi'
            ], 409);
        }

        $lastOtp = Otp::where('user_id', $user->id)
            ->latest()
            ->first();

        // Cegah spam: limit 1x per 1 menit
        if ($lastOtp && $lastOtp->created_at->diffInSeconds(now()) < 60) {
            return response()->json([
                'success' => false,
                'message' => 'Tunggu 1 menit sebelum mengirim ulang OTP'
            ], 429);
        }

        DB::beginTransaction();

        try {
            // Hapus semua OTP lama user (lebih aman)
            Otp::where('user_id', $user->id)->delete();

            // Generate OTP baru
            $otpCode = random_int(100000, 999999);

            Otp::create([
                'id' => Str::uuid(),
                'user_id' => $user->id,
                'otp_code' => $otpCode,
                'expired_at' => now()->addMinutes(5),
                'is_used' => false,
                'type' => 'register'
            ]);

            DB::commit();

            try {
                Mail::to($user->email)->queue(new OtpMail($otpCode));
            } catch (\Exception $e) {
                return response()->json([
                    'success' => false,
                    'message' => 'Gagal mengirim OTP, coba lagi.'
                ], 500);
            }

            return response()->json([
                'success' => true,
                'message' => 'OTP baru telah dikirim'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengirim OTP',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required'
        ]);

        // Ambil user
        $user = User::where('email', $request->email)->first();

        // Cek email atau password salah
        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Email atau password salah'
            ], 401);
        }

        if (is_null($user->email_verified_at)) {
            return response()->json([
                'success' => false,
                'message' => 'Email belum terverifikasi. Silakan verifikasi email Anda.'
            ], 403);
        }

        // Hapus token lama (opsional)
        // $user->tokens()->delete();

        // Buat token baru
        $token = $user->createToken('auth-token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Login berhasil',
            'token' => $token,
            'user' => $user
        ]);
    }
    public function logout(Request $request)
    {
        // Hapus token yang sedang digunakan
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Logout berhasil'
        ]);
    }
}
