<?php

namespace App\Console\Commands;

use App\Models\CmsLayanan;
use App\Models\Lead;
use Illuminate\Console\Command;

/**
 * Isi jenis layanan (cms_layanan_id) Deal hasil migrasi:pj-lama berdasarkan tipe pekerjaan mitra,
 * karena sistem lama tidak menyimpan jenis layanan -> Deal migrasi tidak terhitung di tabel
 * Layanan CC. Tier (Junior/Medium/Senior) tidak bisa ditebak, jadi dibiarkan kosong (tampil di
 * baris "Tier belum ditentukan"), kecuali Babysitter New Born Care.
 *
 *   php artisan migrasi:pj-layanan              DRY RUN
 *   php artisan migrasi:pj-layanan --jalankan   eksekusi
 *
 * Hanya menyentuh Deal migrasi (catatan "[PJ-LAMA #...]") yg layanannya masih kosong.
 */
class MigrasiPjLayanan extends Command
{
    protected $signature = 'migrasi:pj-layanan {--jalankan}';
    protected $description = 'Isi jenis layanan Deal migrasi PJ dari tipe pekerjaan mitra (default dry-run)';

    // tipe pekerjaan mitra => [nama layanan CMS, tier_nama atau null]
    private const PETA = [
        'Perawat Lansia / Caregiver' => ['Caregiver', null],
        'Caregiver / Kaigo (Jepang)' => ['Caregiver', null],
        'Perawat Homecare'           => ['Perawat Medis', null],
        'Perawat Jiwa'               => ['Perawat Jiwa', null],
        'Babysitter'                 => ['Babysitter', null],
        'Babysitter New Born Care'   => ['Babysitter', 'Senior / Newborn Care - Bulanan'],
    ];

    public function handle()
    {
        $jalankan = (bool) $this->option('jalankan');
        $this->info($jalankan ? '*** MODE EKSEKUSI ***' : '*** DRY RUN (tambah --jalankan untuk eksekusi) ***');

        $layanan = CmsLayanan::all()->keyBy(fn ($l) => mb_strtolower(trim($l->nama)));
        $leads = Lead::with('mitra:id,nama_lengkap,tipe_pekerjaan')
            ->where('catatan', 'like', '[PJ-LAMA #%')
            ->whereNull('cms_layanan_id')
            ->get();
        $this->line($leads->count() . ' Deal migrasi tanpa layanan');

        $rekap = []; $tanpa = [];
        foreach ($leads as $lead) {
            $tipe = $lead->mitra?->tipe_pekerjaan;
            [$namaLayanan, $tier] = self::PETA[$tipe] ?? [null, null];
            $l = $namaLayanan ? ($layanan[mb_strtolower($namaLayanan)] ?? null) : null;
            if (!$l) {
                $tanpa[] = "#" . ($lead->nomor ?? $lead->id) . " {$lead->nama_pasien} (mitra: " . ($lead->mitra->nama_lengkap ?? '-') . ", tipe: " . ($tipe ?: 'kosong') . ")";
                continue;
            }
            $key = $l->nama . ($tier ? " · {$tier}" : ' · (tier belum ditentukan)');
            $rekap[$key] = ($rekap[$key] ?? 0) + 1;
            if ($jalankan) $lead->update(['cms_layanan_id' => $l->id, 'tier_nama' => $tier]);
        }

        $this->newLine();
        $this->table(['Layanan', 'Jumlah Deal'], collect($rekap)->map(fn ($n, $k) => [$k, $n])->values()->all());
        if ($tanpa) {
            $this->warn(count($tanpa) . ' Deal tidak bisa dipetakan (tipe pekerjaan mitra kosong / tanpa mitra) -- tetap di baris "Belum ada layanan", lengkapi manual di CC:');
            foreach ($tanpa as $t) $this->line("  - {$t}");
        }
        $this->info($jalankan ? 'Selesai.' : 'Jalankan ulang dengan --jalankan untuk eksekusi.');
        return 0;
    }
}
