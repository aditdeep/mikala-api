<?php

namespace App\Console\Commands;

use App\Models\Mitra;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Laporan mitra yang kemungkinan DOBEL (orang yang sama tercatat > 1 kali). READ-ONLY, tidak
 * mengubah data apa pun.
 *
 * Dobel kemungkinan besar dari 2x migrasi sistem lama (migrasi:mitra-lama lalu
 * migrasi:mitra-update) yang mencocokkan cuma via NIK / nama+tempat lahir persis, sehingga
 * orang yg NIK-nya kosong / beda tulis nama dibuat ulang.
 *
 * Dua mitra dianggap satu grup kalau salah satu sama:
 *   - NIK (min. 10 digit, bukan NIK placeholder migrasi "MIG-...")
 *   - nama (dinormalisasi) + tanggal lahir
 *   - no HP akun (dinormalisasi ke 62...)
 *   - email akun
 *   - nama (dinormalisasi) saja -> ditandai "nama saja" (bisa jadi orang beda yg namanya sama)
 *
 * Tiap baris menampilkan jumlah data terkait (Deal, payroll, training, dst) supaya kelihatan
 * salinan mana yg dipakai & mana yg kosong. Hasil juga disimpan ke storage/app/mitra_duplikat.csv
 *
 *   php artisan mitra:cek-duplikat
 */
class CekMitraDuplikat extends Command
{
    protected $signature = 'mitra:cek-duplikat';
    protected $description = 'Laporan mitra dobel (read-only) + jumlah data terkait tiap salinan';

    private array $parent = [];

    private function find($x) { while ($this->parent[$x] !== $x) { $x = $this->parent[$x] = $this->parent[$this->parent[$x]]; } return $x; }
    private function union($a, $b) { $a = $this->find($a); $b = $this->find($b); if ($a !== $b) $this->parent[max($a, $b)] = min($a, $b); }

    public static function normNama(?string $s): string
    {
        $s = mb_strtolower((string) $s);
        $s = preg_replace('/,.*$/u', '', $s);            // buang gelar di belakang koma (", S.Kep")
        $s = preg_replace('/[^a-z\s]/u', ' ', $s);
        return trim(preg_replace('/\s+/', ' ', $s));
    }

    public static function normHp(?string $s): string
    {
        $p = preg_replace('/\D/', '', (string) $s);
        if (str_starts_with($p, '0')) $p = '62' . substr($p, 1);
        elseif (str_starts_with($p, '8')) $p = '62' . $p;
        return strlen($p) >= 10 ? $p : '';
    }

    public function handle()
    {
        $mitra = Mitra::with('user:id,name,email,phone,status')->get();
        $this->info('Total mitra (tidak termasuk yg sudah dihapus): ' . $mitra->count());

        foreach ($mitra as $m) $this->parent[$m->id] = $m->id;

        $keys = ['nik' => [], 'nama_tgl' => [], 'hp' => [], 'email' => [], 'nama' => []];
        foreach ($mitra as $m) {
            $nik = preg_replace('/\D/', '', (string) $m->nik);
            if (strlen($nik) >= 10 && !str_starts_with((string) $m->nik, 'MIG-')) $keys['nik'][$nik][] = $m->id;
            $nama = self::normNama($m->nama_lengkap ?: $m->user?->name);
            if ($nama !== '') {
                $keys['nama'][$nama][] = $m->id;
                if ($m->tanggal_lahir) $keys['nama_tgl'][$nama . '|' . substr((string) $m->tanggal_lahir, 0, 10)][] = $m->id;
            }
            $hp = self::normHp($m->user?->phone);
            if ($hp) $keys['hp'][$hp][] = $m->id;
            $email = strtolower(trim((string) $m->user?->email));
            if ($email && !str_contains($email, 'migrasi') && !str_ends_with($email, '.local')) $keys['email'][$email][] = $m->id;
        }

        $alasan = [];
        foreach ($keys as $jenis => $map) {
            foreach ($map as $ids) {
                if (count($ids) < 2) continue;
                foreach ($ids as $id) { $this->union($ids[0], $id); $alasan[$id][$jenis] = true; }
            }
        }

        $grup = [];
        foreach ($mitra as $m) $grup[$this->find($m->id)][] = $m;
        $grup = array_filter($grup, fn ($g) => count($g) > 1);

        if (!$grup) { $this->info('Tidak ada mitra dobel. 👍'); return 0; }

        $relasi = $this->tabelRelasi();
        $byId = $mitra->keyBy('id');
        $csv = fopen(storage_path('app/mitra_duplikat.csv'), 'w');
        fputcsv($csv, array_merge(['grup', 'alasan', 'mitra_id', 'nama', 'nim', 'nik', 'tgl_lahir', 'hp', 'email', 'status_rekrutmen', 'status', 'dibuat', 'nim_lama'], array_keys($relasi), ['total_data_terkait']));

        $no = 0;
        foreach ($grup as $anggota) {
            $no++;
            $jenisGrup = collect($anggota)->flatMap(fn ($m) => array_keys($alasan[$m->id] ?? []))->unique()->values()->all();
            $kuat = array_diff($jenisGrup, ['nama']);
            $label = $kuat ? implode('+', $kuat) : 'NAMA SAJA (cek manual, bisa beda orang)';
            $this->newLine();
            $this->line("<fg=yellow>Grup #{$no}</> — cocok via: {$label}");

            $rows = [];
            foreach ($anggota as $m) {
                $hitung = [];
                foreach ($relasi as $nama => [$tabel, $kolom]) {
                    $hitung[$nama] = DB::table($tabel)->where($kolom, $m->id)->count();
                }
                $total = array_sum($hitung);
                $dt = is_array($m->data_tambahan) ? $m->data_tambahan : json_decode((string) $m->data_tambahan, true);
                $nimLama = $dt['nim_lama'] ?? '';
                $base = [$m->id, $m->nama_lengkap ?: $m->user?->name, $m->nomor_induk, $m->nik, substr((string) $m->tanggal_lahir, 0, 10),
                    $m->user?->phone, $m->user?->email, $m->status_rekrutmen, $m->status, substr((string) $m->created_at, 0, 10), $nimLama];
                fputcsv($csv, array_merge([$no, $label], $base, array_values($hitung), [$total]));
                $ringkas = collect($hitung)->filter()->map(fn ($v, $k) => "$k:$v")->implode(' ');
                $rows[] = [$m->id, $base[1], $m->nomor_induk, $m->nik, $base[4], $m->user?->phone, $m->status_rekrutmen, $base[9], $ringkas ?: '-'];
            }
            $this->table(['ID', 'Nama', 'NIM', 'NIK', 'Tgl Lahir', 'HP', 'Rekrutmen', 'Dibuat', 'Data terkait'], $rows);
        }
        fclose($csv);

        $this->newLine();
        $this->info(count($grup) . ' grup dobel, total ' . array_sum(array_map('count', $grup)) . ' baris mitra.');
        $this->line('Detail lengkap: ' . storage_path('app/mitra_duplikat.csv'));
        $this->line('Belum ada data yang diubah. Kirim hasil ini ke developer untuk dipilih mana yang dipertahankan.');
        return 0;
    }

    /** Semua tabel yang punya kolom mitra_id (+ beberapa kolom referensi mitra lain). */
    private function tabelRelasi(): array
    {
        $hasil = [];
        try {
            $tabel = DB::select("SELECT table_name FROM information_schema.columns WHERE column_name = 'mitra_id' AND table_schema = current_schema() ORDER BY table_name");
            foreach ($tabel as $t) $hasil[$t->table_name] = [$t->table_name, 'mitra_id'];
        } catch (\Throwable $e) {
            foreach (['cc_leads', 'payroll', 'training_checklist', 'sertifikat_mitra', 'mitra_kasbon', 'mitra_kredit_pelatihan', 'mitra_jadwal_interview', 'cuti', 'orders'] as $t) {
                if (Schema::hasTable($t)) $hasil[$t] = [$t, 'mitra_id'];
            }
        }
        foreach ([['cc_leads', 'referensi_mitra_id'], ['mitra_referral', 'referrer_mitra_id']] as [$t, $k]) {
            if (Schema::hasTable($t) && Schema::hasColumn($t, $k)) $hasil["$t.$k"] = [$t, $k];
        }
        return $hasil;
    }
}
