<?php

namespace App\Console\Commands;

use App\Http\Controllers\Internal\TrainingController;
use App\Models\TrainingMateri;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Rapikan daftar materi checklist Training (permintaan Training Center, Okt 2026):
 *  - XVII  -> "Mobilisasi dan Ambulasi"
 *  - XXIII (Norma Anggaran Belanja Rumah Tangga) & XXIV (Gender) -> DIHAPUS dari daftar
 *    (dinonaktifkan, bukan dihapus permanen: riwayat ceklis mitra tetap tersimpan)
 *  - XXV   -> "Pendampingan Pasien Menjelang Ajal/Paliatif Care"
 *  - XXVI  -> "Perawatan Pasien Diabetes Melitus"
 *  - Semua nama materi: HURUF KAPITAL SEMUA -> Huruf Awal Kapital (tampil juga di CV)
 *
 *   php artisan training:rapikan-materi              DRY RUN
 *   php artisan training:rapikan-materi --jalankan   eksekusi + hitung ulang status mitra yg masih training
 */
class RapikanMateriTraining extends Command
{
    protected $signature = 'training:rapikan-materi {--jalankan}';
    protected $description = 'Ganti nama / hapus materi checklist & ubah ke Huruf Awal Kapital (default dry-run)';

    private const GANTI = [
        'XVII' => 'Mobilisasi dan Ambulasi',
        'XXV'  => 'Pendampingan Pasien Menjelang Ajal/Paliatif Care',
        'XXVI' => 'Perawatan Pasien Diabetes Melitus',
    ];
    private const HAPUS = ['XXIII', 'XXIV'];

    // kata sambung tetap huruf kecil (kecuali di awal)
    private const KECIL = ['dan', 'atau', 'pada', 'di', 'ke', 'dari', 'yang', 'untuk', 'dengan', 'serta', 'bagi', 'oleh', 'dalam', 'tentang', 'terhadap', 'antara', 'per'];
    // singkatan yg tetap huruf besar
    private const SINGKATAN = ['ADL', 'TTV', 'BAB', 'BAK', 'NGT', 'P3K', 'APD', 'RJP', 'CPR', 'DM', 'HIV', 'AIDS', 'TBC', 'TB', 'ASI', 'MPASI', 'K3', 'PHBS', 'KDRT', 'ROM', 'O2', 'BHD', 'BLS', 'PPI', 'ICU', 'IGD', 'UGD', 'RS', 'COVID', 'COVID-19', 'DBD', 'ISPA', 'PHC', 'CG', 'BS', 'MGM', 'SOP', 'TT'];

    public static function hurufAwalKapital(string $teks): string
    {
        $i = 0;
        return preg_replace_callback('/[\p{L}\p{N}][\p{L}\p{N}\'-]*/u', function ($m) use (&$i) {
            $kata = $m[0];
            $up = mb_strtoupper($kata);
            $pertama = $i++ === 0;
            if (in_array($up, self::SINGKATAN, true)) return $up;
            if (preg_match('/^[IVXLC]+$/', $up) && mb_strlen($up) <= 5) return $up; // angka romawi
            $low = mb_strtolower($kata);
            if (!$pertama && in_array($low, self::KECIL, true)) return $low;
            return mb_strtoupper(mb_substr($low, 0, 1)) . mb_substr($low, 1);
        }, trim(preg_replace('/\s+/', ' ', $teks)));
    }

    public function handle()
    {
        $jalankan = (bool) $this->option('jalankan');
        $this->info($jalankan ? '*** MODE EKSEKUSI ***' : '*** DRY RUN (tambah --jalankan untuk eksekusi) ***');
        $norm = fn ($k) => strtoupper(trim(rtrim(trim((string) $k), '.')));

        $materi = TrainingMateri::whereRaw('is_active = true')->orderBy('kategori')->orderBy('urutan')->get();
        $ubah = []; $hapus = [];
        foreach ($materi as $m) {
            $kode = $norm($m->kode);
            if (in_array($kode, self::HAPUS, true) && $m->kategori === 'Dasar' && !$m->parent_kode) { $hapus[] = $m; continue; }
            $baru = (isset(self::GANTI[$kode]) && $m->kategori === 'Dasar' && !$m->parent_kode) ? self::GANTI[$kode] : self::hurufAwalKapital($m->nama);
            if ($baru !== $m->nama) $ubah[] = [$m, $baru, isset(self::GANTI[$kode]) && $m->kategori === 'Dasar' && !$m->parent_kode];
        }

        if ($hapus) {
            $this->warn('DIHAPUS dari daftar (dinonaktifkan):');
            $this->table(['ID', 'Kode', 'Kategori', 'Nama', 'Sudah diceklis'], array_map(fn ($m) => [$m->id, $m->kode, $m->kategori, $m->nama,
                DB::table('training_checklist')->where('materi_id', $m->id)->count() . ' mitra'], $hapus));
        }
        $ganti = array_filter($ubah, fn ($u) => $u[2]);
        if ($ganti) {
            $this->warn('GANTI NAMA:');
            $this->table(['Kode', 'Nama lama', 'Nama baru'], array_map(fn ($u) => [$u[0]->kode, $u[0]->nama, $u[1]], array_values($ganti)));
        }
        $kapital = array_filter($ubah, fn ($u) => !$u[2]);
        if ($kapital) {
            $this->line('HURUF AWAL KAPITAL (' . count($kapital) . ' materi):');
            $this->table(['Kategori', 'Kode', 'Sebelum', 'Sesudah'], array_map(fn ($u) => [$u[0]->kategori, $u[0]->kode, mb_strimwidth($u[0]->nama, 0, 50, '…'), mb_strimwidth($u[1], 0, 50, '…')], array_values($kapital)));
        }
        $tidakKetemu = array_diff(array_merge(array_keys(self::GANTI), self::HAPUS), array_map(fn ($m) => $norm($m->kode), $materi->where('kategori', 'Dasar')->whereNull('parent_kode')->all()));
        if ($tidakKetemu) $this->warn('Kode materi Dasar tidak ditemukan: ' . implode(', ', $tidakKetemu));

        if (!$ubah && !$hapus) { $this->info('Tidak ada yg perlu diubah.'); return 0; }
        if (!$jalankan) { $this->newLine(); $this->info('Jalankan ulang dengan --jalankan untuk eksekusi.'); return 0; }

        DB::transaction(function () use ($ubah, $hapus) {
            foreach ($ubah as [$m, $baru]) $m->update(['nama' => $baru]);
            if ($hapus) TrainingMateri::whereIn('id', array_map(fn ($m) => $m->id, $hapus))->update(['is_active' => DB::raw('false')]);
        });

        // materi wajib berkurang -> hitung ulang status mitra yg masih training
        $tc = app(TrainingController::class);
        $ids = DB::table('mitra')->whereIn('training_status', ['pending', 'in_progress'])
            ->whereExists(fn ($q) => $q->select(DB::raw(1))->from('training_checklist')->whereColumn('training_checklist.mitra_id', 'mitra.id'))
            ->pluck('id');
        $selesai = 0;
        foreach ($ids as $id) if (in_array($tc->hitungUlangStatus($id)['training_status'], ['completed', 'failed'], true)) $selesai++;

        $this->info('Selesai: ' . count($ubah) . ' nama materi diubah, ' . count($hapus) . " materi dihapus dari daftar. Status {$ids->count()} mitra yg masih training dihitung ulang ({$selesai} jadi selesai).");
        return 0;
    }
}
