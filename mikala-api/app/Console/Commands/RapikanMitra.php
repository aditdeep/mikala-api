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
 *
 * Hapus = soft-delete mitra & user (bisa dipulihkan), token login dicabut, no HP/email dibebaskan
 * (diberi awalan DEL{id}-) supaya bisa dipakai pendaftaran baru.
 */
class RapikanMitra extends Command
{
    protected $signature = 'mitra:rapikan {--lihat=} {--hapus=} {--jalankan}';
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

        $hapus = self::ids($this->option('hapus'));
        if (!$hapus) return 0;
        $jalankan = (bool) $this->option('jalankan');
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
}
