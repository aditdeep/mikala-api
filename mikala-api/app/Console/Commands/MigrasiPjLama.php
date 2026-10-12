<?php

namespace App\Console\Commands;

use App\Models\Lead;
use App\Models\Mitra;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Migrasi data Pengguna Jasa (PJ) dari sistem lama (sis.mikalaglobalmedika.com/pj.php) ke
 * Deal Customer Care (cc_leads). Sumber: storage/app/pj_lama.csv
 *
 *   php artisan migrasi:pj-lama               DRY RUN: laporan, tidak mengubah data
 *   php artisan migrasi:pj-lama --jalankan    eksekusi
 *
 * Pemetaan: Status PJ aktif -> Deal, tidak aktif -> Stop (stop_at = tanggal masuk, sesuai
 * keputusan: tidak ikut payroll). Gaji Pokok -> honor_mitra, Admin Fee -> biaya_admin,
 * Management Fee -> management_fee, Kondisi Pasien -> diagnosis_awal.
 *
 * Aturan anti gaji dobel: di sistem lama Deal yg sudah diganti mitra lain sering tetap "aktif".
 * Per pasangan PJ+pasien, cuma baris TERBARU yg dibiarkan aktif; baris aktif yg lebih lama dijadikan
 * Stop dgn stop_at = sehari sebelum baris penggantinya mulai.
 *
 * Dilewati (dilaporkan): PJ+pasien yang sudah ada di cc_leads sistem baru (diinput CC setelah go-live).
 * Ditandai utk dicek CC (tetap diimpor): mitra tidak ketemu, tanggal kosong, gaji tidak wajar (>= 20jt),
 * mitra aktif di > 1 pasien.
 * Idempotent: baris yg sudah diimpor (penanda "[PJ-LAMA #no]" di catatan) dilewati.
 */
class MigrasiPjLama extends Command
{
    protected $signature = 'migrasi:pj-lama {--jalankan}';
    protected $description = 'Migrasi PJ sistem lama ke Deal Customer Care (default dry-run)';

    private static function norm(?string $s): string { return CekMitraDuplikat::normNama($s); }

    private function nomorUnik(string $kolom, callable $gen): string
    {
        $n = $gen();
        // generator bawaan pakai count()+1 (tidak menghitung data terhapus) -> pastikan benar2 belum dipakai
        while (DB::table('cc_leads')->where($kolom, $n)->exists()) {
            $n = preg_replace_callback('/(\d+)$/', fn ($x) => str_pad((string) ((int) $x[1] + 1), strlen($x[1]), '0', STR_PAD_LEFT), $n);
        }
        return $n;
    }

    public function handle()
    {
        $jalankan = (bool) $this->option('jalankan');
        $this->info($jalankan ? '*** MODE EKSEKUSI ***' : '*** DRY RUN (tidak ada data diubah). Tambah --jalankan untuk eksekusi ***');

        $path = storage_path('app/pj_lama.csv');
        if (!file_exists($path)) { $this->error("CSV tidak ada: {$path}"); return 1; }
        $fh = fopen($path, 'r');
        $hdr = fgetcsv($fh);
        $rows = [];
        while (($r = fgetcsv($fh)) !== false) if (count($r) === count($hdr)) $rows[] = array_combine($hdr, $r);
        fclose($fh);
        $this->line(count($rows) . ' baris PJ di CSV');

        // Mitra by nama (exact dinormalisasi; fallback: 1 kandidat yg namanya mengandung / dikandung)
        $mitraAll = Mitra::select('id', 'nama_lengkap', 'nomor_induk', 'status', 'user_id')->get();
        $byNama = $mitraAll->groupBy(fn ($m) => self::norm($m->nama_lengkap));
        $cariMitra = function (string $nama) use ($byNama, $mitraAll) {
            $n = self::norm($nama);
            if ($n === '') return [null, 'nama mitra kosong'];
            if (isset($byNama[$n])) return $byNama[$n]->count() === 1 ? [$byNama[$n]->first(), null] : [null, 'nama mitra kembar di sistem baru'];
            $kand = $mitraAll->filter(function ($m) use ($n) {
                $mn = self::norm($m->nama_lengkap);
                return $mn !== '' && strlen($mn) >= 5 && (str_contains($mn, $n) || str_contains($n, $mn));
            });
            if ($kand->count() === 1) return [$kand->first(), 'cocok sebagian: ' . $kand->first()->nama_lengkap];
            return [null, $kand->count() ? 'beberapa kandidat mitra' : 'mitra tidak ditemukan'];
        };

        // Tentukan status akhir per baris
        foreach ($rows as $i => &$r) {
            $r['_deal'] = preg_match('/^\d{4}-\d{2}-\d{2}$/', $r['tanggal']) && !str_starts_with($r['tanggal'], '0000') ? $r['tanggal'] : null;
            $r['_aktif'] = strtolower(trim($r['status'])) === 'aktif';
            $r['_stop'] = $r['_aktif'] ? null : $r['_deal'];
            $r['_catatan'] = [];
        }
        unset($r);
        $pasangan = [];
        foreach ($rows as $i => $r) $pasangan[self::norm($r['nama_pj']) . '|' . self::norm($r['nama_pasien'])][] = $i;
        foreach ($pasangan as $idx) {
            $aktif = array_values(array_filter($idx, fn ($i) => $rows[$i]['_aktif'] && $rows[$i]['_deal']));
            if (count($aktif) < 2) continue;
            usort($aktif, fn ($a, $b) => strcmp($rows[$a]['_deal'], $rows[$b]['_deal']));
            for ($k = 0; $k < count($aktif) - 1; $k++) {
                $i = $aktif[$k]; $next = $rows[$aktif[$k + 1]];
                if ($next['_deal'] === $rows[$i]['_deal']) {
                    // mulai di tanggal yg sama -> kemungkinan 2 mitra shift utk 1 pasien, biarkan aktif
                    $rows[$i]['_catatan'][] = "2 mitra aktif di pasien yg sama sejak tanggal sama ({$next['nama_mitra']}) -- shift? cek CC";
                    continue;
                }
                $rows[$i]['_aktif'] = false;
                $rows[$i]['_stop'] = max($rows[$i]['_deal'], Carbon::parse($next['_deal'])->subDay()->toDateString());
                $rows[$i]['_catatan'][] = "dinonaktifkan: pasien sama sudah ditangani {$next['nama_mitra']} sejak {$next['_deal']}";
            }
        }

        // Existing di sistem baru
        $existing = DB::table('cc_leads')->whereNull('deleted_at')->select('id', 'nama_leads', 'nama_pasien', 'catatan')->get();
        $sudahAda = []; $sudahImpor = [];
        foreach ($existing as $e) {
            $sudahAda[self::norm($e->nama_leads) . '|' . self::norm($e->nama_pasien)] = $e->id;
            if (preg_match('/\[PJ-LAMA #(\d+)\]/', (string) $e->catatan, $mm)) $sudahImpor[$mm[1]] = $e->id;
        }

        $rencana = []; $lewat = []; $flag = [];
        foreach ($rows as $r) {
            $key = self::norm($r['nama_pj']) . '|' . self::norm($r['nama_pasien']);
            if (isset($sudahImpor[$r['no']])) { $lewat[] = "#{$r['no']} sudah pernah diimpor (lead #{$sudahImpor[$r['no']]})"; continue; }
            if (isset($sudahAda[$key])) { $lewat[] = "#{$r['no']} {$r['nama_pj']} / {$r['nama_pasien']}: sudah ada di sistem baru (lead #{$sudahAda[$key]})"; continue; }
            [$mitra, $ket] = $cariMitra($r['nama_mitra']);
            $f = $r['_catatan'];
            if (!$mitra) $f[] = "MITRA: {$ket} ('{$r['nama_mitra']}') -> diimpor tanpa mitra, pilih mitra di CC";
            elseif ($ket) $f[] = "mitra {$ket}";
            if (!$r['_deal']) $f[] = 'TANGGAL kosong/0000 -> isi tanggal mulai di CC (tanpa tanggal tidak ikut payroll)';
            if ((float) $r['gaji_pokok'] >= 20000000) $f[] = 'GAJI tidak wajar: Rp ' . number_format((float) $r['gaji_pokok'], 0, ',', '.');
            $rencana[] = $r + ['_mitra' => $mitra];
            if ($f) $flag[] = "#{$r['no']} {$r['nama_pasien']} (" . ($mitra->nama_lengkap ?? $r['nama_mitra']) . '): ' . implode('; ', $f);
        }

        // Mitra aktif di >1 pasien (setelah aturan di atas)
        $perMitra = [];
        foreach ($rencana as $r) if ($r['_aktif'] && $r['_mitra']) $perMitra[$r['_mitra']->id][] = $r;
        foreach ($perMitra as $list) {
            if (count($list) < 2) continue;
            $flag[] = 'MITRA AKTIF DI ' . count($list) . ' PASIEN: ' . $list[0]['_mitra']->nama_lengkap . ' -> '
                . implode(', ', array_map(fn ($x) => "{$x['nama_pasien']} (#{$x['no']}, mulai {$x['_deal']})", $list)) . ' -- cek di CC, Stop yg sudah selesai';
        }

        $nAktif = count(array_filter($rencana, fn ($r) => $r['_aktif']));
        $this->newLine();
        $this->info('Akan diimpor: ' . count($rencana) . " (Deal aktif: {$nAktif}, Stop: " . (count($rencana) - $nAktif) . '), dilewati: ' . count($lewat));
        $mitraOnJob = collect($perMitra)->keys()->filter(fn ($id) => !in_array($mitraAll->firstWhere('id', $id)?->status, ['on_job', 'inactive', 'keluar'], true));
        $this->line("Status mitra yg akan diubah ke on_job (punya Deal aktif): {$mitraOnJob->count()}");
        if ($lewat) { $this->newLine(); $this->line('<fg=yellow>Dilewati:</>'); foreach ($lewat as $l) $this->line("  - {$l}"); }
        if ($flag) { $this->newLine(); $this->line('<fg=yellow>Perlu dicek CC:</>'); foreach ($flag as $l) $this->line("  - {$l}"); }

        if (!$jalankan) { $this->newLine(); $this->info('Jalankan ulang dengan --jalankan untuk eksekusi.'); return 0; }

        $ok = 0;
        DB::transaction(function () use ($rencana, $mitraOnJob, &$ok) {
            foreach ($rencana as $r) {
                $catatan = "[PJ-LAMA #{$r['no']}] Migrasi dari sistem lama" . ($r['_catatan'] ? ' — ' . implode('; ', $r['_catatan']) : '');
                Lead::create([
                    'nomor'                  => $this->nomorUnik('nomor', fn () => Lead::generateNomor()),
                    'nik'                    => $this->nomorUnik('nik', fn () => Lead::generateNik()),
                    'nomor_deal'             => $this->nomorUnik('nomor_deal', fn () => Lead::generateNomorDeal()),
                    'nama_leads'             => $r['nama_pj'],
                    'alamat_cust_pj'         => $r['alamat_pj'] ?: null,
                    'kontak'                 => $r['telp_pj'] ?: null,
                    'nama_pasien'            => $r['nama_pasien'],
                    'alamat_klien'           => $r['alamat_pasien'] ?: null,
                    'hubungan_dengan_pasien' => $r['hubungan'] ?: null,
                    'diagnosis_awal'         => $r['kondisi'] ?: null,
                    'honor_mitra'            => (float) $r['gaji_pokok'] ?: null,
                    'biaya_admin'            => (float) $r['admin_fee'] ?: null,
                    'management_fee'         => (float) $r['management_fee'] ?: null,
                    'mitra_id'               => $r['_mitra']?->id,
                    'mitra_nim'              => $r['_mitra']?->nomor_induk,
                    'sumber'                 => 'Migrasi Sistem Lama',
                    'status'                 => $r['_aktif'] ? Lead::STATUS_DEAL : Lead::STATUS_STOP,
                    'deal_at'                => $r['_deal'],
                    'stop_at'                => $r['_aktif'] ? null : $r['_stop'],
                    'catatan'                => $catatan,
                ]);
                $ok++;
            }
            if ($mitraOnJob->isNotEmpty()) Mitra::whereIn('id', $mitraOnJob->values())->update(['status' => 'on_job']);
        });
        $this->info("Selesai: {$ok} Deal diimpor, {$mitraOnJob->count()} mitra jadi on_job.");
        return 0;
    }
}
