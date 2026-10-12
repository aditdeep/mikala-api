<?php

namespace App\Console\Commands;

use App\Models\Mitra;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Gabungkan mitra DOBEL hasil 2x migrasi sistem lama.
 *
 * Pola dobel (lihat mitra:cek-duplikat): salinan pertama dibuat migrasi:mitra-lama (NIM format
 * lama mis. "182/CG-MGM/2025", NIK "MIG200"), salinan kedua dibuat migrasi:mitra-update yang
 * gagal mengenali salinan pertama (NIM format baru, data_tambahan.nim_lama = "182/CG-MGM/2025",
 * no HP diberi akhiran "-2").
 *
 * Hanya menggabungkan yang PASTI orang sama: nama sama (dinormalisasi) DAN
 *   - NIM salinan A == nim_lama salinan B, atau
 *   - nim_lama A == nim_lama B,
 * plus pasangan manual lewat --tambah=KEEP:DUP (bisa diulang / dipisah koma).
 *
 * Yang dipertahankan = ID terkecil di grup (akun login pertama). Dari salinan dobel diambil:
 *   - NIM format baru (kalau yg dipertahankan masih format lama)
 *   - tanggal lahir asli (kalau yg dipertahankan masih placeholder 01-01 / tahun >= 2025)
 *   - NIK 16 digit yang lebih valid
 *   - kolom lain yg di salinan dipertahankan masih kosong
 *   - status/status_lulus dari salinan yg paling terakhir diubah; on_job kalau ada Deal aktif
 * Semua data terkait (semua kolom *mitra_id di DB, fee_log penerima mitra) dipindah ke salinan
 * yg dipertahankan. Salinan dobel + akunnya di-soft-delete (bisa dipulihkan).
 *
 *   php artisan mitra:gabung-duplikat                 (DRY RUN: cuma tampilkan rencana)
 *   php artisan mitra:gabung-duplikat --jalankan      (eksekusi; backup JSON dulu ke storage/app)
 */
class GabungMitraDuplikat extends Command
{
    protected $signature = 'mitra:gabung-duplikat {--jalankan} {--tambah=*}';
    protected $description = 'Gabungkan mitra dobel hasil migrasi (default dry-run)';

    private array $parent = [];

    private function find($x) { while ($this->parent[$x] !== $x) { $x = $this->parent[$x] = $this->parent[$this->parent[$x]]; } return $x; }
    private function union($a, $b) { $a = $this->find($a); $b = $this->find($b); if ($a !== $b) $this->parent[max($a, $b)] = min($a, $b); }

    private static function dt(Mitra $m): array
    {
        $d = $m->data_tambahan;
        if (is_string($d)) $d = json_decode($d, true);
        return is_array($d) ? $d : [];
    }

    private static function nimBaru(?string $nim): bool { return $nim && !str_contains($nim, '/'); }

    private static function tglPlaceholder($tgl): bool
    {
        if (!$tgl) return true;
        $s = substr((string) $tgl, 0, 10);
        return str_ends_with($s, '-01-01') || (int) substr($s, 0, 4) >= 2025;
    }

    private static function rankNik(?string $nik): int
    {
        $d = preg_replace('/\D/', '', (string) $nik);
        if (str_starts_with((string) $nik, 'MIG') || strlen($d) !== 16) return 0;
        return str_ends_with($d, '0000') ? 1 : 2; // ...0000 = kepotong presisi Excel
    }

    public function handle()
    {
        $jalankan = (bool) $this->option('jalankan');
        $this->info($jalankan ? '*** MODE EKSEKUSI ***' : '*** DRY RUN (tidak ada data diubah). Tambah --jalankan untuk eksekusi ***');

        $mitra = Mitra::with('user')->get()->keyBy('id');
        foreach ($mitra as $id => $m) $this->parent[$id] = $id;

        // Pasangkan via NIM lama
        $byNim = []; $byLama = [];
        foreach ($mitra as $m) {
            $nama = CekMitraDuplikat::normNama($m->nama_lengkap ?: $m->user?->name);
            if ($m->nomor_induk) $byNim[$nama . '|' . $m->nomor_induk][] = $m->id;
            $lama = self::dt($m)['nim_lama'] ?? null;
            if ($lama) $byLama[$nama . '|' . $lama][] = $m->id;
        }
        foreach ($byLama as $key => $ids) {
            foreach ($ids as $id) $this->union($ids[0], $id);
            foreach ($byNim[$key] ?? [] as $id) $this->union($ids[0], $id);
        }
        foreach ((array) $this->option('tambah') as $opt) {
            foreach (explode(',', $opt) as $pair) {
                [$a, $b] = array_map('intval', explode(':', $pair) + [1 => 0]);
                if (!isset($mitra[$a], $mitra[$b])) { $this->warn("--tambah {$pair}: dilewati, ID tidak ada / sudah digabung sebelumnya"); continue; }
                $this->union($a, $b);
            }
        }

        $grup = [];
        foreach ($mitra as $id => $m) $grup[$this->find($id)][] = $id;
        $grup = array_filter($grup, fn ($g) => count($g) > 1);
        if (!$grup) { $this->info('Tidak ada mitra dobel yang memenuhi aturan.'); return 0; }

        $kolomRelasi = $this->kolomRelasi();
        $backup = [];
        $ok = 0; $gagal = 0;

        foreach ($grup as $ids) {
            sort($ids);
            $keep = $mitra[$ids[0]];
            $dups = array_map(fn ($id) => $mitra[$id], array_slice($ids, 1));
            $this->newLine();
            $this->line(sprintf('<fg=yellow>%s</> — pertahankan #%d (%s), gabungkan: %s',
                $keep->nama_lengkap, $keep->id, $keep->nomor_induk ?: '-', implode(', ', array_map(fn ($d) => "#{$d->id} ({$d->nomor_induk})", $dups))));

            [$ubahMitra, $ubahUser] = $this->rencanaField($keep, $dups);
            foreach (array_diff_key($ubahMitra, ['data_tambahan' => 1]) as $k => $v) $this->line("   mitra.{$k}: " . json_encode($keep->getRawOriginal($k)) . ' -> ' . json_encode($v));
            foreach ($ubahUser as $k => $v) $this->line("   user.{$k}: " . json_encode($keep->user?->$k) . ' -> ' . json_encode($v));
            foreach ($dups as $d) {
                $pindah = [];
                foreach ($kolomRelasi as [$t, $c]) {
                    $n = DB::table($t)->where($c, $d->id)->count();
                    if ($n) $pindah[] = "{$t}.{$c}:{$n}";
                }
                $nFee = Schema::hasTable('fee_log') ? DB::table('fee_log')->where('penerima_tipe', 'mitra')->where('penerima_id', $d->id)->count() : 0;
                if ($nFee) $pindah[] = "fee_log:{$nFee}";
                $this->line("   pindah data #{$d->id}: " . ($pindah ? implode(' ', $pindah) : '-') . " | hapus (soft) mitra #{$d->id} & user #{$d->user_id}");
            }

            if (!$jalankan) continue;

            $backup[] = [
                'keep' => ['mitra' => $keep->getAttributes(), 'user' => $keep->user?->getAttributes()],
                'dups' => array_map(fn ($d) => ['mitra' => $d->getAttributes(), 'user' => $d->user?->getAttributes()], $dups),
            ];
            try {
                DB::transaction(function () use ($keep, $dups, $ubahMitra, $ubahUser, $kolomRelasi) {
                    foreach ($dups as $d) {
                        // bebaskan kolom unik (nik / nomor_induk) dulu sebelum dipindah ke keep
                        DB::table('mitra')->where('id', $d->id)->update([
                            'nik' => 'DUP' . $d->id . '-' . substr((string) $d->nik, 0, 40),
                            'nomor_induk' => null,
                        ]);
                        foreach ($kolomRelasi as [$t, $c]) {
                            DB::table($t)->where($c, $d->id)->update([$c => $keep->id]);
                        }
                        if (Schema::hasTable('fee_log')) {
                            DB::table('fee_log')->where('penerima_tipe', 'mitra')->where('penerima_id', $d->id)->update(['penerima_id' => $keep->id]);
                        }
                    }
                    DB::table('mitra')->where('id', $keep->id)->update($ubahMitra + ['updated_at' => now()]);
                    if ($ubahUser && $keep->user_id) DB::table('users')->where('id', $keep->user_id)->update($ubahUser + ['updated_at' => now()]);

                    // NIM di Deal ikut NIM final
                    $nimFinal = $ubahMitra['nomor_induk'] ?? $keep->nomor_induk;
                    if ($nimFinal && Schema::hasColumn('cc_leads', 'mitra_nim')) {
                        DB::table('cc_leads')->where('mitra_id', $keep->id)->update(['mitra_nim' => $nimFinal]);
                    }

                    foreach ($dups as $d) {
                        DB::table('mitra')->where('id', $d->id)->update(['deleted_at' => now()]);
                        if ($d->user_id && $d->user_id !== $keep->user_id) {
                            DB::table('personal_access_tokens')->where('tokenable_type', User::class)->where('tokenable_id', $d->user_id)->delete();
                            DB::table('users')->where('id', $d->user_id)->update(['deleted_at' => now(), 'fcm_token' => null]);
                        }
                    }
                });
                $ok++;
                $this->info('   ✓ digabung');
            } catch (\Throwable $e) {
                $gagal++;
                $this->error('   ✗ GAGAL, grup ini dibatalkan (tidak ada yg berubah): ' . $e->getMessage());
            }
        }

        $this->newLine();
        if ($jalankan) {
            $file = storage_path('app/backup_gabung_mitra_' . now()->format('Ymd_His') . '.json');
            file_put_contents($file, json_encode($backup, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            $this->info("Selesai: {$ok} grup digabung, {$gagal} gagal. Backup data sebelum digabung: {$file}");
        } else {
            $this->info(count($grup) . ' grup akan digabung. Jalankan ulang dengan --jalankan untuk eksekusi.');
        }
        return 0;
    }

    /** @return array{0: array, 1: array} perubahan kolom mitra & user utk salinan yg dipertahankan */
    private function rencanaField(Mitra $keep, array $dups): array
    {
        $m = [];
        $raw = fn ($x, $k) => $x->getRawOriginal($k);
        $kosong = fn ($v) => $v === null || $v === '' || $v === '[]' || $v === '{}';

        foreach ($dups as $d) {
            $nimKeep = $m['nomor_induk'] ?? $keep->nomor_induk;
            if (!self::nimBaru($nimKeep) && self::nimBaru($d->nomor_induk)) $m['nomor_induk'] = $d->nomor_induk;

            $tglKeep = $m['tanggal_lahir'] ?? $raw($keep, 'tanggal_lahir');
            if (self::tglPlaceholder($tglKeep) && !self::tglPlaceholder($raw($d, 'tanggal_lahir'))) $m['tanggal_lahir'] = substr($raw($d, 'tanggal_lahir'), 0, 10);

            $nikKeep = $m['nik'] ?? $keep->nik;
            if (self::rankNik($d->nik) > self::rankNik($nikKeep)) $m['nik'] = $d->nik;

            foreach ($keep->getFillable() as $k) {
                if (in_array($k, ['user_id', 'nik', 'nomor_induk', 'tanggal_lahir', 'status', 'status_rekrutmen', 'data_tambahan', 'training_status'], true)) continue;
                if ($kosong($m[$k] ?? $raw($keep, $k)) && !$kosong($raw($d, $k))) $m[$k] = $raw($d, $k);
            }
        }

        // status rekrutmen: verified menang
        $semua = array_merge([$keep], $dups);
        if ($keep->status_rekrutmen !== 'verified' && collect($dups)->contains(fn ($d) => $d->status_rekrutmen === 'verified')) {
            $m['status_rekrutmen'] = 'verified';
        }
        // status kerja: dari salinan yg terakhir diubah; on_job kalau ada Deal aktif
        $terbaru = collect($semua)->sortByDesc(fn ($x) => (string) $raw($x, 'updated_at'))->first();
        if ($terbaru->id !== $keep->id) {
            foreach (['status', 'status_lulus', 'training_status'] as $k) {
                if (Schema::hasColumn('mitra', $k) && $raw($terbaru, $k) !== null && $raw($terbaru, $k) !== $raw($keep, $k)) $m[$k] = $raw($terbaru, $k);
            }
        }
        $adaDeal = DB::table('cc_leads')->whereIn('mitra_id', collect($semua)->pluck('id'))->where('status', \App\Models\Lead::STATUS_DEAL)->exists();
        if ($adaDeal && ($m['status'] ?? $keep->status) !== 'on_job') $m['status'] = 'on_job';

        // data_tambahan: gabung, catat semua NIM lama
        $dt = self::dt($keep);
        $lama = array_filter(array_merge([$dt['nim_lama'] ?? null], array_map(fn ($d) => self::dt($d)['nim_lama'] ?? null, $dups),
            [str_contains((string) $keep->nomor_induk, '/') ? $keep->nomor_induk : null]));
        foreach ($dups as $d) $dt = $dt + self::dt($d);
        if ($lama) { $dt['nim_lama'] = $dt['nim_lama'] ?? reset($lama); $dt['nim_lama_semua'] = array_values(array_unique($lama)); }
        $dt['digabung_dari'] = array_values(array_unique(array_merge($dt['digabung_dari'] ?? [], array_map(fn ($d) => $d->id, $dups))));
        $m['data_tambahan'] = json_encode($dt, JSON_UNESCAPED_UNICODE);

        // akun: no HP asli dari salinan dobel (akhiran "-2" dibuang) kalau punya keep masih NOHP-
        $u = [];
        $hpKeep = (string) $keep->user?->phone;
        if (str_starts_with($hpKeep, 'NOHP') || $hpKeep === '') {
            foreach ($dups as $d) {
                $hp = preg_replace('/-\d+$/', '', (string) $d->user?->phone);
                if ($hp && !str_starts_with($hp, 'NOHP') && !User::withTrashed()->where('phone', $hp)->exists()) { $u['phone'] = $hp; break; }
            }
        }
        return [$m, $u];
    }

    /** Semua kolom *mitra_id di DB (mitra_id, referensi_mitra_id, referrer_mitra_id, ...). */
    private function kolomRelasi(): array
    {
        if (DB::getDriverName() === 'sqlite') { // utk uji lokal
            $out = [];
            foreach (DB::select("SELECT name FROM sqlite_master WHERE type='table'") as $t) {
                foreach (DB::select("PRAGMA table_info('{$t->name}')") as $c) {
                    if (str_ends_with($c->name, 'mitra_id')) $out[] = [$t->name, $c->name];
                }
            }
            return $out;
        }
        $rows = DB::select("SELECT c.table_name, c.column_name FROM information_schema.columns c
            JOIN information_schema.tables t ON t.table_name = c.table_name AND t.table_schema = c.table_schema
            WHERE c.table_schema = current_schema() AND t.table_type = 'BASE TABLE' AND c.column_name LIKE '%mitra_id'
            ORDER BY c.table_name, c.column_name");
        return array_map(fn ($r) => [$r->table_name, $r->column_name], $rows);
    }
}
