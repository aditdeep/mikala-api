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

        $digit = preg_match('/^[\d\s+\-]{4,}$/', $q) ? preg_replace('/^(62|0)/', '', preg_replace('/\D/', '', $q)) : '';
        if ($q !== '') {
            $like = '%' . strtolower($q) . '%';
            $query->where(function ($w) use ($like, $digit) {
                $w->whereRaw('LOWER(name) LIKE ?', [$like])
                  ->orWhereRaw('LOWER(email) LIKE ?', [$like])
                  ->orWhere('phone', 'like', str_replace(' ', '', $like))
                  // "0812.." vs tersimpan "62812.." / "0812-345.." -> cocokkan digit tanpa awalan 0/62
                  ->when($digit !== '', fn ($w) => $w->orWhereRaw("regexp_replace(COALESCE(phone,''), '[^0-9]', '', 'g') LIKE ?", ['%' . $digit . '%']))
                  ->orWhereHas('mitra', fn ($m) => $m->whereRaw('LOWER(nama_lengkap) LIKE ?', [$like])
                      ->orWhereRaw('LOWER(nomor_induk) LIKE ?', [$like]))
                  ->orWhereHas('klien', fn ($k) => $k->whereRaw('LOWER(nama_lengkap) LIKE ?', [$like]));
            });
        }

        $data = $query->orderBy('name')->limit(50)->get();

        // Deal (aktif/stop) yg Cust/PJ-nya belum punya akun apps Klien -> ikut tampil supaya admin
        // bisa langsung "Buat Akun & PIN". Sebelumnya akun klien cuma lahir dari command
        // klien:buat-akun, jadi Deal baru gak pernah muncul di sini.
        if (in_array('klien', $roles, true)) {
            $leads = \App\Models\Lead::whereNull('klien_id')
                ->whereIn('status', [\App\Models\Lead::STATUS_DEAL, \App\Models\Lead::STATUS_STOP])
                ->when($q !== '', function ($l) use ($q, $digit) {
                    $like = '%' . strtolower($q) . '%';
                    $l->where(fn ($w) => $w->whereRaw('LOWER(nama_leads) LIKE ?', [$like])
                        ->orWhereRaw('LOWER(nama_pasien) LIKE ?', [$like])
                        ->orWhereRaw('LOWER(nomor_deal) LIKE ?', [$like])
                        ->when($digit !== '', fn ($w) => $w->orWhereRaw("regexp_replace(COALESCE(kontak,'') || ' ' || COALESCE(no_wa_klien,''), '[^0-9 ]', '', 'g') LIKE ?", ['%' . $digit . '%'])));
                })
                ->orderByRaw('status = ? desc', [\App\Models\Lead::STATUS_DEAL])->orderByDesc('deal_at')->limit(50)
                ->get(['id', 'nama_leads', 'nama_pasien', 'kontak', 'no_wa_klien', 'nomor_deal', 'status']);
            foreach ($leads as $l) {
                $data->push(['id' => 'lead-' . $l->id, 'lead_id' => $l->id, 'tanpa_akun' => true, 'role' => 'klien',
                    'name' => $l->nama_leads ?: '-', 'email' => null, 'phone' => \App\Support\AkunKlien::hpLead($l) ?: $l->kontak,
                    'pasien' => $l->nama_pasien, 'nomor_deal' => $l->nomor_deal, 'status_deal' => (int) $l->status === \App\Models\Lead::STATUS_DEAL ? 'aktif' : 'stop']);
            }
        }

        return response()->json(['success' => true, 'data' => $data]);
    }

    /**
     * Buat akun apps Klien (No HP PJ + PIN) dari 1 Deal yg belum punya akun. Kalau No HP-nya
     * ternyata sudah punya akun klien, Deal ditautkan ke situ & PIN di-reset.
     */
    public function buatAkunKlienDariLead(Request $request, $leadId)
    {
        if (!in_array('klien', $this->allowedRoles($request), true)) {
            return response()->json(['success' => false, 'message' => 'Tidak punya akses'], 403);
        }
        $request->validate(['password' => 'nullable|string|min:6']);
        $lead = \App\Models\Lead::findOrFail($leadId);
        try {
            [$user, $pin] = \App\Support\AkunKlien::buatDariLead($lead, $request->password);
        } catch (\RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
        \Log::info('ADMIN BUAT AKUN KLIEN', ['by' => $request->user()->id, 'user_id' => $user->id, 'lead_id' => $lead->id]);
        return $this->hasil($user, $pin, 'Akun klien siap');
    }

    /**
     * Set password baru utk akun mitra/klien. Kalau `password` kosong, digenerate otomatis.
     * Balikin password baru (sekali tampil) + link WA ke nomor user biar admin tinggal kirim.
     */
    public function resetPassword(Request $request, $userId)
    {
        $request->validate(['password' => 'nullable|string|min:6']);

        $user = User::findOrFail($userId);
        if (!in_array($user->role, $this->allowedRoles($request), true)) {
            return response()->json(['success' => false, 'message' => 'Tidak punya akses untuk reset password akun ini'], 403);
        }

        $newPassword = $request->filled('password')
            ? $request->password
            // klien (banyak yg lansia) login pakai No HP + PIN 6 angka
            : ($user->role === 'klien' ? \App\Support\AkunKlien::pin()
            // tanpa karakter yg gampang ketuker (0/O, 1/l/I) biar aman dibaca dari chat WA
            : 'Mkl' . collect(str_split('abcdefghjkmnpqrstuvwxyz23456789'))->random(6)->implode(''));

        $user->update(['password' => Hash::make($newPassword)]);

        // Link reset dari email (kalau ada) jadi gak berlaku lagi, dan semua sesi login lama di-logout
        if ($user->email) DB::table('password_reset_tokens')->whereRaw('LOWER(email) = ?', [strtolower($user->email)])->delete();
        $user->tokens()->delete();

        \Log::info('ADMIN RESET PASSWORD', ['by' => $request->user()->id, 'user_id' => $user->id, 'role' => $user->role]);

        return $this->hasil($user, $newPassword, 'Password berhasil direset');
    }

    /** Respon sekali tampil: password/PIN baru + link WA siap kirim ke user. */
    private function hasil(User $user, string $pw, string $pesan)
    {
        $loginUrl = $user->role === 'klien' ? config('services.frontend.klien') : config('services.frontend.mitra');
        $waUrl = null;
        $phone = preg_replace('/\D/', '', (string) $user->phone);
        if ($phone !== '') {
            if (Str::startsWith($phone, '0')) $phone = '62' . substr($phone, 1);
            elseif (Str::startsWith($phone, '8')) $phone = '62' . $phone;
            $msg = ($pesan === 'Akun klien siap'
                    ? "Halo Bapak/Ibu {$user->name}, berikut akun aplikasi Klien Mikala Global Medika untuk memantau layanan.\n\n"
                    : "Halo {$user->name}, password akun Mikala Anda sudah direset oleh admin.\n\n")
                 . ($user->email ? "Email: {$user->email}\n" : '') . ($user->role === 'klien' ? "No HP: {$user->phone}\nPIN: " : 'Password baru: ') . "{$pw}\n\n"
                 . "Silakan login di {$loginUrl}/auth/login. Mohon jangan bagikan password ini ke siapa pun.";
            $waUrl = "https://wa.me/{$phone}?text=" . rawurlencode($msg);
        }

        $warning = null;
        if ($user->status !== 'active') {
            $warning = 'Akun ini berstatus non-aktif, jadi tetap belum bisa login walau password sudah direset.';
        } elseif ($user->role === 'mitra' && $user->mitra && $user->mitra->status_rekrutmen !== 'verified') {
            $warning = 'Mitra ini belum diterima Rekrutmen (status: ' . ($user->mitra->status_rekrutmen ?: 'pending') . '), jadi tetap belum bisa login ke app mitra.';
        }

        return response()->json([
            'success'      => true,
            'message'      => $pesan,
            'new_password' => $pw,
            'email'        => $user->email,
            'phone'        => $user->phone,
            'wa_url'       => $waUrl,
            'warning'      => $warning,
        ]);
    }
}
