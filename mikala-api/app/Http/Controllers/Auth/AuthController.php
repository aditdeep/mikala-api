<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use App\Support\NomorHp;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Login (multi-role)
     */
    public function login(Request $request)
    {
        // Field `login` bisa email ATAU No HP/WA (klien lansia banyak yg gak punya email).
        // `email` tetap diterima biar app versi lama (yg kirim field email) gak rusak.
        $request->validate([
            'login' => 'required_without:email|nullable|string|max:255',
            'email' => 'required_without:login|nullable|string|max:255',
            'password' => 'required',
        ]);
        $login = trim((string) ($request->input('login') ?: $request->input('email')));

        // Password klien = PIN 6 angka -> batasi percobaan gagal per akun+IP biar gak bisa ditebak
        $kunci = 'login:' . sha1(strtolower($login) . '|' . $request->ip());
        if (RateLimiter::tooManyAttempts($kunci, 10)) {
            $menit = (int) ceil(RateLimiter::availableIn($kunci) / 60);
            throw ValidationException::withMessages([
                'email' => ["Terlalu banyak percobaan login. Coba lagi dalam {$menit} menit."],
            ]);
        }

        if (str_contains($login, '@')) {
            $user = User::whereRaw('LOWER(email) = ?', [strtolower($login)])->first();
        } else {
            $kandidat = NomorHp::cariUser($login);
            // nomor yg sama dipakai >1 akun (data lama) -> jangan nebak, minta pakai email
            if ($kandidat->count() > 1) {
                throw ValidationException::withMessages([
                    'email' => ['Nomor HP ini terdaftar di lebih dari satu akun. Silakan login dengan email atau hubungi admin Mikala.'],
                ]);
            }
            $user = $kandidat->first();
        }

        if (!$user || !Hash::check($request->password, $user->password)) {
            RateLimiter::hit($kunci, 15 * 60);
            throw ValidationException::withMessages([
                'email' => ['Email/No HP atau password salah.'],
            ]);
        }
        RateLimiter::clear($kunci);

        // Check user status
        if ($user->status !== 'active') {
            return response()->json([
                'message' => 'Your account is not active. Please contact administrator.',
            ], 403);
        }

        // FIX: mitra yg baru daftar (belum di-"Terima" tim Rekrutmen) bisa langsung login ke
        // apps mitra padahal harusnya belum boleh, karena User.status selalu 'active' begitu
        // register (terlepas dari status review mitra-nya). Blokir di sini berdasar
        // Mitra.status_rekrutmen, bukan User.status.
        if ($user->role === 'mitra') {
            $mitra = $user->mitra;
            if ($mitra && $mitra->status_rekrutmen !== 'verified') {
                return response()->json([
                    'message' => 'Pendaftaran Anda masih menunggu verifikasi tim Rekrutmen. Silakan tunggu konfirmasi sebelum bisa login.',
                ], 403);
            }
        }

        // Create token with role
        $token = $user->createToken('auth-token', [$user->role])->plainTextToken;

        // Load profile relation
        $profile = null;
        if ($user->role === 'mitra') {
            $profile = $user->mitra;
        } elseif ($user->role === 'klien') {
            $profile = $user->klien;
        } elseif ($user->role === 'agen') {
            $profile = $user->agen;
        }

        return response()->json([
            'message' => 'Login successful',
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'role' => $user->role,
                'status' => $user->status,
                'profile' => $profile,
            ],
        ], 200);
    }

    /**
     * Logout
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        // Lepas token push HP supaya notif akun ini gak nyasar ke HP yg sudah logout / dipakai akun lain
        $request->user()->forceFill(['fcm_token' => null])->save();

        return response()->json([
            'message' => 'Logged out successfully',
        ], 200);
    }

    /**
     * Get current user
     */
    public function me(Request $request)
    {
        $user = $request->user();
        
        // Load profile
        $profile = null;
        if ($user->role === 'mitra') {
            $profile = $user->mitra;
        } elseif ($user->role === 'klien') {
            $profile = $user->klien;
        } elseif ($user->role === 'agen') {
            $profile = $user->agen;
        }

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'role' => $user->role,
                'status' => $user->status,
                'profile' => $profile,
            ],
        ], 200);
    }

    /**
     * Refresh token
     */
    public function refresh(Request $request)
    {
        $user = $request->user();
        
        // Delete current token
        $request->user()->currentAccessToken()->delete();
        
        // Create new token
        $token = $user->createToken('auth-token', [$user->role])->plainTextToken;

        return response()->json([
            'message' => 'Token refreshed successfully',
            'token' => $token,
            'token_type' => 'Bearer',
        ], 200);
    }

    /**
     * Forgot password (placeholder - needs mail service)
     */
    public function forgotPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:users,email',
        ]);

        // TODO: Implement password reset email
        // For now, return success
        return response()->json([
            'message' => 'Password reset link has been sent to your email.',
        ], 200);
    }

    /**
     * Reset password (placeholder)
     */
    public function resetPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:users,email',
            'token' => 'required',
            'password' => 'required|min:8|confirmed',
        ]);

        // TODO: Implement password reset logic with token verification
        
        return response()->json([
            'message' => 'Password has been reset successfully.',
        ], 200);
    }
}
