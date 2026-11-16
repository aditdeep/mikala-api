<?php

namespace App\Support;

use App\Models\Klien;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Akun login apps Klien (No HP/WA Cust/PJ + PIN 6 angka). Dipakai command klien:buat-akun
 * (massal) dan tombol admin "Buat Akun & PIN" per Deal di modal Reset PIN Klien.
 */
class AkunKlien
{
    /** Kolom kontak kadang berisi >1 nomor ("0215xxx/0812xxx") -> ambil nomor HP valid pertama. */
    public static function hpPertama(?string $kontak): ?string
    {
        foreach (preg_split('#[/,;]|\s{2,}#', (string) $kontak) as $bagian) {
            if ($n = NomorHp::normal($bagian)) return $n;
        }
        return null;
    }

    public static function hpLead(Lead $l): ?string
    {
        return self::hpPertama($l->kontak) ?? self::hpPertama($l->no_wa_klien);
    }

    /** PIN 6 angka acak, hindari yg gampang ditebak (angka kembar semua / urut). */
    public static function pin(): string
    {
        do {
            $p = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        } while (count(array_unique(str_split($p))) < 3 || str_contains('0123456789', $p) || str_contains('9876543210', $p));
        return $p;
    }

    /**
     * Buat akun klien utk Cust/PJ sebuah Deal yg belum tertaut akun. Semua Deal lain dgn No HP
     * PJ yg sama & belum tertaut ikut ditautkan. Kalau No HP sudah punya akun klien, Deal
     * ditautkan ke akun itu dan PIN-nya di-reset (admin tetap dapat PIN utk dikirim).
     *
     * @return array{0: User, 1: string} [user, pin]
     * @throws \RuntimeException pesan siap tampil ke admin
     */
    public static function buatDariLead(Lead $lead, ?string $pin = null): array
    {
        $hp = self::hpLead($lead);
        if (!$hp) throw new \RuntimeException('No HP/WA Cust/PJ di Deal ini kosong/tidak valid (' . ($lead->kontak ?: '-') . '). Lengkapi dulu di data Leads.');

        $ada = NomorHp::cariUser($hp);
        if ($ada->count() > 1) throw new \RuntimeException("No HP {$hp} dipakai {$ada->count()} akun, rapikan manual dulu.");
        $u = $ada->first();
        if ($u && ($u->role !== 'klien' || !$u->klien)) throw new \RuntimeException("No HP {$hp} sudah dipakai akun {$u->role} \"{$u->name}\".");

        $pin = $pin ?: self::pin();
        $sejenis = Lead::whereNull('klien_id')->whereIn('status', [Lead::STATUS_DEAL, Lead::STATUS_STOP])->get()
            ->filter(fn ($l) => self::hpLead($l) === $hp)->pluck('id')->push($lead->id)->unique();

        $user = DB::transaction(function () use ($u, $lead, $hp, $pin, $sejenis) {
            if ($u) {
                $u->update(['password' => Hash::make($pin)]);
                $u->tokens()->delete();
                $klienId = $u->klien->id;
            } else {
                $nama = trim((string) $lead->nama_leads) ?: (trim((string) $lead->nama_pasien) ?: 'Klien');
                $u = User::create(['name' => $nama, 'email' => null, 'phone' => $hp,
                    'password' => Hash::make($pin), 'role' => 'klien', 'status' => 'active']);
                $klienId = Klien::create(['user_id' => $u->id, 'nama_lengkap' => $nama, 'tipe' => 'keluarga',
                    'alamat' => $lead->alamat_cust_pj ?: ($lead->alamat_klien ?: '-'), 'kota' => '-', 'provinsi' => '-',
                    'status' => 'active', 'is_verified' => DB::raw('true')])->id;
            }
            Lead::whereIn('id', $sejenis)->update(['klien_id' => $klienId]);
            return $u;
        });

        return [$user, $pin];
    }
}
