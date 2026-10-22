<?php

namespace App\Console\Commands;

use App\Http\Controllers\Internal\TrainingController;
use App\Models\TrainingMateri;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Pindahkan materi Dasar nomor IV s/d XI (imunisasi, tumbuh kembang, perawatan bayi, dst) ke
 * kategori baru "BS" (Baby Sitter). Setelah ini:
 *   CG  -> Dasar saja | BS -> Dasar + BS | PHC -> Dasar + PHC   (lihat Mitra::kategoriTraining)
 *
 *   php artisan training:pisah-bs              DRY RUN: tampilkan materi yg akan dipindah
 *   php artisan training:pisah-bs --jalankan   eksekusi + hitung ulang status mitra yg masih training
 *
 * Sub-materi (parent_kode IV..XI) ikut dipindah. Checklist yg sudah ada tidak dihapus.
 */
class PisahMateriBabysitter extends Command
{
    protected $signature = 'training:pisah-bs {--jalankan}';
    protected $description = 'Pindahkan materi Dasar IV-XI ke kategori BS (Baby Sitter) (default dry-run)';

    private const KODE = ['IV', 'V', 'VI', 'VII', 'VIII', 'IX', 'X', 'XI'];

    public function handle()
    {
        $jalankan = (bool) $this->option('jalankan');
        $this->info($jalankan ? '*** MODE EKSEKUSI ***' : '*** DRY RUN (tambah --jalankan untuk eksekusi) ***');

        $norm = fn ($k) => strtoupper(trim(rtrim(trim((string) $k), '.')));
        $semua = TrainingMateri::where('kategori', 'Dasar')->orderBy('urutan')->get();
        $pindah = $semua->filter(fn ($m) => in_array($norm($m->kode), self::KODE, true) || in_array($norm($m->parent_kode), self::KODE, true));

        if ($pindah->isEmpty()) {
            $sudah = TrainingMateri::where('kategori', 'BS')->count();
            $this->warn($sudah ? "Tidak ada yg perlu dipindah -- kategori BS sudah berisi {$sudah} materi." : 'Materi Dasar IV-XI tidak ditemukan. Cek kode materi di tabel training_materi.');
            return 0;
        }

        $this->table(['ID', 'Kode', 'Induk', 'Nama materi', 'Sudah diceklis'], $pindah->map(fn ($m) => [
            $m->id, $m->kode, $m->parent_kode ?: '-', mb_strimwidth($m->nama, 0, 60, '…'),
            DB::table('training_checklist')->where('materi_id', $m->id)->count() . ' mitra',
        ])->values()->all());
        $this->line($pindah->count() . ' materi akan dipindah dari "Dasar" ke "BS". Sisa materi Dasar: ' . ($semua->count() - $pindah->count()));

        $hilang = array_diff(self::KODE, $pindah->map(fn ($m) => $norm($m->kode))->all());
        if ($hilang) $this->warn('Kode tidak ditemukan sbg materi utama: ' . implode(', ', $hilang) . ' -- cek apakah memang begitu.');

        if (!$jalankan) { $this->newLine(); $this->info('Jalankan ulang dengan --jalankan untuk eksekusi.'); return 0; }

        DB::transaction(fn () => TrainingMateri::whereIn('id', $pindah->pluck('id'))->update(['kategori' => 'BS']));

        // Jumlah materi per tipe berubah -> status mitra yg masih training dihitung ulang.
        // Mitra yg sudah selesai (lulus/sertifikat) tidak diubah.
        $tc = app(TrainingController::class);
        $ids = DB::table('mitra')->whereIn('training_status', ['pending', 'in_progress'])
            ->whereExists(fn ($q) => $q->select(DB::raw(1))->from('training_checklist')->whereColumn('training_checklist.mitra_id', 'mitra.id'))
            ->pluck('id');
        $jadiSelesai = 0;
        foreach ($ids as $id) {
            $hasil = $tc->hitungUlangStatus($id);
            if (in_array($hasil['training_status'], ['completed', 'failed'], true)) $jadiSelesai++;
        }
        $this->info("Selesai: {$pindah->count()} materi dipindah ke BS. Status {$ids->count()} mitra yg masih training dihitung ulang ({$jadiSelesai} jadi selesai karena materi wajibnya berkurang).");
        return 0;
    }
}
