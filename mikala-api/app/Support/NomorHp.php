<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Normalisasi nomor HP Indonesia supaya "0812-3456 789", "62812...", "+62 812..." dianggap sama.
 * Dipakai login pakai No HP/WA (klien lansia yg gak punya email) & pembuatan akun klien.
 */
class NomorHp
{
    /** Bentuk baku "08xxxxxxxxx", atau null kalau tidak valid. */
    public static function normal(?string $nomor): ?string
    {
        $d = preg_replace('/\D/', '', (string) $nomor);
        if (str_starts_with($d, '62')) $d = '0' . substr($d, 2);
        elseif (str_starts_with($d, '8')) $d = '0' . $d;
        return preg_match('/^08\d{7,12}$/', $d) ? $d : null;
    }

    /** Bentuk "628xxx" utk link wa.me. */
    public static function wa(?string $nomor): ?string
    {
        $n = self::normal($nomor);
        return $n ? '62' . substr($n, 1) : null;
    }

    /** Semua user yg nomor HP-nya (setelah dinormalisasi) sama dengan $nomor. */
    public static function cariUser(string $nomor)
    {
        $n = self::normal($nomor);
        if (!$n) return collect();
        $varian = [$n, '62' . substr($n, 1)];

        if (DB::getDriverName() === 'pgsql') {
            return User::whereRaw("regexp_replace(phone, '[^0-9]', '', 'g') IN (?, ?)", $varian)->get();
        }
        // driver lain (testing lokal): saring di PHP
        return User::whereNotNull('phone')->get()->filter(fn ($u) => self::normal($u->phone) === $n)->values();
    }
}
