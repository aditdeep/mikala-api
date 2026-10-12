<?php

namespace App\Console\Commands;

use App\Models\Mitra;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Perapian data mitra satuan (pelengkap mitra:gabung-duplikat).
 *
 *   php artisan mitra:rapikan --lihat=438,439            tampilkan isi mentah mitra + akunnya
 *   php artisan mitra:rapikan --hapus=3,4,5               DRY RUN hapus (soft) mitra + akunnya
 *   php artisan mitra:rapikan --hapus=3,4,5 --jalankan    eksekusi
 *   php artisan mitra:rapikan --perbaiki-geser=438,439 [--jalankan]
 *       perbaiki baris hasil import yg kolomnya bergeser 1 (nama di kolom nik, NIM lama di
 *       nama, "Perempuan" di no HP, "Kota, tgl bln thn" di alamat, pendidikan di
 *       pengalaman_pelatihan, dst). Kolom yg tidak bisa dipastikan dikosongkan.
 *
 * Hapus = soft-delete mitra & user (bisa dipulihkan), token login dicabut, no HP/email dibebaskan
 * (diberi awalan DEL{id}-) supaya bisa dipakai pendaftaran baru.
 */
class RapikanMitra extends Command
{
    protected $signature = 'mitra:rapikan {--lihat=} {--hapus=} {--perbaiki-geser=} {--jalankan}';
    protected $description = 'Lihat isi mentah / hapus (soft) mitra tertentu';

    private static function ids(?string $s): array
    {
        return array_values(array_filter(array_map('intval', explode(',', (string) $s))));
    }

    public function handle()
    {
        foreach (self::ids($this->option('lihat')) as $id) {
            $m = Mitra::withTrashed()->find($id);
            if (!$m) { $this->warn("Mitra #{$id} tidak ada"); continue; }
            $this->newLine();
            $this->line("<fg=yellow>Mitra #{$id}</>");
            $this->table(['kolom', 'isi'], collect($m->getAttributes())->map(fn ($v, $k) => [$k, is_scalar($v) || $v === null ? (string) $v : json_encode($v)])->values()->all());
            $u = $m->user_id ? User::withTrashed()->find($m->user_id) : null;
            if ($u) {
                $this->line("<fg=yellow>User #{$u->id}</>");
                $this->table(['kolom', 'isi'], collect($u->getAttributes())->except(['password', 'remember_token'])->map(fn ($v, $k) => [$k, (string) $v])->values()->all());
            }
        }

        $jalankan = (bool) $this->option('jalankan');
        foreach (self::ids($this->option('perbaiki-geser')) as $id) $this->perbaikiGeser($id, $jalankan);

        $hapus = self::ids($this->option('hapus'));
        if (!$hapus) return 0;
        $this->info($jalankan ? '*** MODE EKSEKUSI ***' : '*** DRY RUN (tambah --jalankan untuk eksekusi) ***');

        foreach ($hapus as $id) {
            $m = Mitra::find($id);
            if (!$m) { $this->warn("Mitra #{$id} tidak ada / sudah dihapus"); continue; }
            $terkait = [];
            foreach (DB::select("SELECT c.table_name, c.column_name FROM information_schema.columns c
                    JOIN information_schema.tables t ON t.table_name = c.table_name AND t.table_schema = c.table_schema
                    WHERE c.table_schema = current_schema() AND t.table_type = 'BASE TABLE' AND c.column_name LIKE '%mitra_id'") as $r) {
                $n = DB::table($r->table_name)->where($r->column_name, $id)->count();
                if ($n) $terkait[] = "{$r->table_name}.{$r->column_name}:{$n}";
            }
            $this->line("Hapus mitra #{$id} {$m->nama_lengkap} (user #{$m->user_id} {$m->user?->email}) | data terkait tetap disimpan: " . ($terkait ? implode(' ', $terkait) : '-'));
            if (!$jalankan) continue;

            DB::transaction(function () use ($m) {
                DB::table('mitra')->where('id', $m->id)->update(['deleted_at' => now(), 'nik' => 'DEL' . $m->id . '-' . $m->nik]);
                if ($m->user_id) {
                    $u = DB::table('users')->where('id', $m->user_id)->first();
                    DB::table('personal_access_tokens')->where('tokenable_type', User::class)->where('tokenable_id', $m->user_id)->delete();
                    DB::table('users')->where('id', $m->user_id)->update([
                        'deleted_at' => now(), 'fcm_token' => null,
                        'phone' => 'DEL' . $m->user_id . '-' . $u->phone, 'email' => 'del' . $m->user_id . '-' . $u->email,
                    ]);
                }
            });
            $this->info("   ✓ dihapus");
        }
        return 0;
    }

    private function perbaikiGeser(int $id, bool $jalankan): void
    {
        $m = Mitra::find($id);
        if (!$m) { $this->warn("Mitra #{$id} tidak ada"); return; }
        $u = $m->user_id ? User::find($m->user_id) : null;
        $nama = trim((string) $m->nik);
        $nimLama = trim((string) $m->nama_lengkap);
        if (!preg_match('#^\d+/[A-Z]+-MGM/\d{4}$#', $nimLama) || $nama === '' || preg_match('/\d{6,}/', $nama)) {
            $this->warn("Mitra #{$id}: pola kolom bergeser tidak cocok, dilewati (cek manual)");
            return;
        }
        $bulan = ['januari'=>1,'februari'=>2,'maret'=>3,'april'=>4,'mei'=>5,'juni'=>6,'juli'=>7,'agustus'=>8,'september'=>9,'oktober'=>10,'november'=>11,'desember'=>12];
        $tempat = null; $tgl = null;
        if (preg_match('/^\s*([^,]+),\s*(\d{1,2})\s+([a-z]+)\s+(\d{4})/i', (string) $m->alamat, $t) && isset($bulan[strtolower($t[3])])) {
            $tempat = trim($t[1]);
            $tgl = sprintf('%04d-%02d-%02d', $t[4], $bulan[strtolower($t[3])], $t[2]);
        }
        $hp = strtolower(preg_replace('/-\d{1,2}$/', '', (string) $u?->phone));
        $jk = str_starts_with($hp, 'perempuan') ? 'P' : (str_starts_with($hp, 'laki') ? 'L' : $m->jenis_kelamin);
        $kodeLama = preg_match('#/([A-Z]+)-MGM/#', $nimLama, $k) ? $k[1] : null;
        $tipe = ['CG' => 'Perawat Lansia / Caregiver', 'PHC' => 'Perawat Homecare'][$kodeLama] ?? null;
        $slug = strtolower(preg_replace('/[^A-Za-z0-9]+/', '', str_replace('/', '', $nimLama)));
        $dt = is_array($m->data_tambahan) ? $m->data_tambahan : (json_decode((string) $m->data_tambahan, true) ?: []);
        $dt = ['nim_lama' => $nimLama, 'diperbaiki_dari_kolom_bergeser' => now()->toDateString()] + array_diff_key($dt, ['nim_lama' => 1, 'tunjangan_lama' => 1]);

        $ubah = [
            'nama_lengkap'         => $nama,
            'nik'                  => 'MIG-' . $slug,
            'tempat_lahir'         => $tempat,
            'tanggal_lahir'        => $tgl ?? $m->getRawOriginal('tanggal_lahir'),
            'jenis_kelamin'        => $jk,
            'alamat'               => '-', // kolom NOT NULL; alamat asli tidak ada di data
            'kota'                 => $tempat ?? ($m->suku ?: '-'), // NOT NULL
            'pendidikan_terakhir'  => $m->pengalaman_pelatihan ?: '-', // NOT NULL
            'pengalaman_pelatihan' => $m->pengalaman ?: null,
            'pengalaman'           => null,
            'bisa_memasak'         => in_array($m->pendidikan_terakhir, ['Bisa', 'Tidak Bisa'], true) ? $m->pendidikan_terakhir : null,
            'tinggi_badan'         => is_numeric($m->berat_badan) && $m->berat_badan > 100 ? $m->berat_badan : null,
            'berat_badan'          => null,
            'agama'                => null,
            'suku'                 => null,
            'gaji_bulanan'         => null,
            'tipe_pekerjaan'       => $tipe,
            'nomor_induk'          => $tipe ? Mitra::generateNim($tipe) : $m->nomor_induk,
            'data_tambahan'        => json_encode($dt, JSON_UNESCAPED_UNICODE),
        ];
        $ubahUser = $u ? [
            'name'  => $nama,
            'email' => 'mitra-' . $slug . '@mitra.mikalaglobalmedika.com',
            'phone' => 'NOHP-' . $slug,
        ] : [];

        $this->newLine();
        $this->line("<fg=yellow>Perbaiki mitra #{$id}</> ({$nama})");
        foreach ($ubah as $k => $v) if ($k !== 'data_tambahan' && (string) $m->getRawOriginal($k) !== (string) $v) $this->line("   mitra.{$k}: " . json_encode($m->getRawOriginal($k)) . ' -> ' . json_encode($v));
        foreach ($ubahUser as $k => $v) $this->line("   user.{$k}: " . json_encode($u->$k) . ' -> ' . json_encode($v));
        if (!$jalankan) return;

        DB::transaction(function () use ($m, $u, $ubah, $ubahUser) {
            DB::table('mitra')->where('id', $m->id)->update($ubah + ['updated_at' => now()]);
            if ($u) DB::table('users')->where('id', $u->id)->update($ubahUser + ['updated_at' => now()]);
        });
        $this->info('   ✓ diperbaiki');
    }
}
