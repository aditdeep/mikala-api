<?php

namespace App\Services;

use App\Events\NotifikasiCreated;
use App\Models\Notifikasi;

class NotifikasiService
{
    /**
     * Buat notifikasi + auto broadcast realtime
     *
     * @param int $userId
     * @param string $tipe       — kategori: order|payroll|cuti|sertifikat|kasbon|info
     * @param string $judul
     * @param string $pesan
     * @param array $data        — payload tambahan (link, order_id, dll)
     */
    public static function send(int $userId, string $tipe, string $judul, string $pesan, array $data = []): Notifikasi
    {
        $notif = Notifikasi::create([
            'user_id' => $userId,
            'tipe'    => $tipe,
            'judul'   => $judul,
            'pesan'   => $pesan,
            'data'    => $data,
            'read_at' => null,
        ]);

        // Broadcast event ke Pusher (jika driver bukan null)
        if (config('broadcasting.default') !== 'null') {
            try {
                broadcast(new NotifikasiCreated($notif));
            } catch (\Exception $e) {
                \Log::warning('Broadcast notifikasi failed: ' . $e->getMessage());
            }
        }

        return $notif;
    }

    /**
     * Kirim notifikasi ke banyak user sekaligus
     */
    public static function sendBulk(array $userIds, string $tipe, string $judul, string $pesan, array $data = []): int
    {
        $count = 0;
        foreach ($userIds as $userId) {
            self::send($userId, $tipe, $judul, $pesan, $data);
            $count++;
        }
        return $count;
    }
}
