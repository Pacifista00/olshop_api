<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureEmailIsVerified
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        // Pastikan user login
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 401);
        }

        // Cek apakah user sudah verifikasi email
        if (is_null($user->email_verified_at)) {
            return response()->json([
                'success' => false,
                'message' => 'Email belum terverifikasi'
            ], 403);
        }

        return $next($request);
    }
}
