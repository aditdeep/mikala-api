<?php

namespace App\Support;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Jadwal publish artikel CMS MGM (cms_artikel) & MGA (mga_artikel).
 *
 * Sebelumnya status 'scheduled' -> 'published' cuma diubah oleh command artikel:publish-scheduled
 * lewat Laravel scheduler, yang butuh cron `schedule:run` di VPS. Cron itu gak jalan, jadi artikel
 * terjadwal nyangkut di status "Jadwal" selamanya. Sekarang publishDue() juga dipanggil tiap
 * daftar/detail artikel diakses, jadi gak bergantung ke cron lagi (command tetap ada sbg cadangan).
 */
class ArtikelScheduler
{
    /** Zona waktu yang dipakai admin waktu isi jadwal di CMS. */
    public const INPUT_TZ = 'Asia/Jakarta';

    public static function publishDue(): array
    {
        $now = now();
        $count = [];
        foreach (['cms_artikel' => 'mgm', 'mga_artikel' => 'mga'] as $table => $key) {
            $count[$key] = DB::table($table)
                ->where('status', 'scheduled')
                ->where('published_at', '<=', $now)
                ->update(['status' => 'published', 'updated_at' => $now]);
        }
        return $count;
    }

    /**
     * Parse jadwal dari form CMS. Input <input type="datetime-local"> gak bawa zona waktu
     * (mis. "2026-10-05T08:00"), dan sebelumnya dibaca sbg UTC -> tayang 7 jam telat.
     * Sekarang string tanpa zona dianggap WIB, lalu disimpan dalam zona waktu app (UTC).
     */
    public static function parseInput(?string $value): Carbon
    {
        if (!$value) return now();
        return Carbon::parse($value, self::INPUT_TZ)->setTimezone(config('app.timezone'));
    }
}
