<?php

namespace App\Http\Controllers\Internal;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Reset password akun Mitra / Klien oleh admin.
 *
 * Alur "Lupa Password" di app mitra/klien ujungnya ngarahin user chat WA ke admin, tapi
 * sebelumnya admin gak punya tombol apa pun buat ganti password mitra/klien (menu Users di
 * Settings cuma untuk akun staff internal). Controller ini nutup celah itu.
 *
 * Hak akses per role staff:
 *   manajemen                  -> mitra & klien
 *   rekrutmen, training_center -> mitra
 *   customer_care              -> klien
 */
class AkunController extends Controller
{
    private const ACCESS = [
        'manajemen'       => ['mitra', 'klien'],
        'rekrutmen'       => ['mitra'],
        'training_center' => ['mitra'],
        'customer_care'   => ['klien'],
    ];

    private function allowedRoles(Request $request): array
    {
        return self::ACCESS[$request->user()->role] ?? [];
    }

    /**
     * Cari akun mitra/klien by nama / email / no HP (buat modal Reset Password).
     */
    public function search(Request $request)
    {
        $allowed = $this->allowedRoles($request);
        $roles = $request->filled('role') ? array_intersect([$request->role], $allowed) : $allowed;
        if (empty($roles)) {
            return response()->json(['success' => false, 'message' => 'Tidak punya akses'], 403);
        }

        $q = trim((string) $request->get('q', ''));
        $query = User::whereIn('role', $roles)
            ->with(['mitra:id,user_id,nama_lengkap,nomor_induk,status_rekrutmen', 'klien:id,user_id,nama_lengkap'])
            ->select('id', 'name', 'email', 'phone', 'role', 'status');

        if ($q !== '') {
            $like = '%' . strtolower($q) . '%';
            $query->where(function ($w) use ($like) {
                $w->whereRaw('LOWER(name) LIKE ?', [$like])
                  ->orWhereRaw('LOWER(email) LIKE ?', [$like])
                  ->orWhere('phone', 'like', str_replace(' ', '', $like))
                  ->orWhereHas('mitra', fn ($m) => $m->whereRaw('LOWER(nama_lengkap) LIKE ?', [$like])
                      ->orWhereRaw('LOWER(nomor_induk) LIKE ?', [$like]))
                  ->orWhereHas('klien', fn ($k) => $k->whereRaw('LOWER(nama_lengkap) LIKE ?', [$like]));
            });
        }

        return response()->json([
            'success' => true,
            'data'    => $query->orderBy('name')->limit(30)->get(),
        ]);
    }

    /**
     * Set password baru utk akun mitra/klien. Kalau `password` kosong, digenerate otomatis.
     * Balikin password baru (sekali tampil) + link WA ke nomor user biar admin tinggal kirim.
     */
    public function resetPassword(Request $request, $userId)
    {
        $request->validate(['password' => 'nullable|string|min:8']);

        $user = User::findOrFail($userId);
        if (!in_array($user->role, $this->allowedRoles($request), true)) {
            return response()->json(['success' => false, 'message' => 'Tidak punya akses untuk reset password akun ini'], 403);
        }

        $newPassword = $request->filled('password')
            ? $request->password
            // tanpa karakter yg gampang ketuker (0/O, 1/l/I) biar aman dibaca dari chat WA
            : 'Mkl' . collect(str_split('abcdefghjkmnpqrstuvwxyz23456789'))->random(6)->implode('');

        $user->update(['password' => Hash::make($newPassword)]);

        // Link reset dari email (kalau ada) jadi gak berlaku lagi, dan semua sesi login lama di-logout
        DB::table('password_reset_tokens')->whereRaw('LOWER(email) = ?', [strtolower($user->email)])->delete();
        $user->tokens()->delete();

        $loginUrl = $user->role === 'klien' ? config('services.frontend.klien') : config('services.frontend.mitra');
        $waUrl = null;
        $phone = preg_replace('/\D/', '', (string) $user->phone);
        if ($phone !== '') {
            if (Str::startsWith($phone, '0')) $phone = '62' . substr($phone, 1);
            elseif (Str::startsWith($phone, '8')) $phone = '62' . $phone;
            $msg = "Halo {$user->name}, password akun Mikala Anda sudah direset oleh admin.\n\n"
                 . "Email: {$user->email}\nPassword baru: {$newPassword}\n\n"
                 . "Silakan login di {$loginUrl}/auth/login. Mohon jangan bagikan password ini ke siapa pun.";
            $waUrl = "https://wa.me/{$phone}?text=" . rawurlencode($msg);
        }

        $warning = null;
        if ($user->status !== 'active') {
            $warning = 'Akun ini berstatus non-aktif, jadi tetap belum bisa login walau password sudah direset.';
        } elseif ($user->role === 'mitra' && $user->mitra && $user->mitra->status_rekrutmen !== 'verified') {
            $warning = 'Mitra ini belum diterima Rekrutmen (status: ' . ($user->mitra->status_rekrutmen ?: 'pending') . '), jadi tetap belum bisa login ke app mitra.';
        }

        \Log::info('ADMIN RESET PASSWORD', ['by' => $request->user()->id, 'user_id' => $user->id, 'role' => $user->role]);

        return response()->json([
            'success'      => true,
            'message'      => 'Password berhasil direset',
            'new_password' => $newPassword,
            'email'        => $user->email,
            'wa_url'       => $waUrl,
            'warning'      => $warning,
        ]);
    }
}
