<?php

namespace App\Console\Commands;

use App\Models\Lead;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Kembalikan Lead (+ notifikasi & rating) yg terhapus oleh data:hapus-tes, dari file backup
 * JSON-nya. ID Lead dipertahankan sama persis.
 *
 *   php artisan data:pulihkan-tes storage/app/backup_hapus_tes_XXXX.json             DRY RUN
 *   php artisan data:pulihkan-tes storage/app/backup_hapus_tes_XXXX.json --jalankan
 */
class PulihkanDataTes extends Command
{
    protected $signature = 'data:pulihkan-tes {file} {--jalankan}';
    protected $description = 'Pulihkan Lead yg terhapus data:hapus-tes dari backup JSON (default dry-run)';

    public function handle()
    {
        $file = $this->argument('file');
        if (!is_file($file)) $file = storage_path('app/' . basename($file));
        if (!is_file($file)) { $this->error('File backup tidak ditemukan'); return 1; }
        $data = json_decode(file_get_contents($file), true);
        if (!is_array($data)) { $this->error('Isi file backup tidak valid'); return 1; }

        $jalankan = (bool) $this->option('jalankan');
        $this->info($jalankan ? '*** MODE EKSEKUSI ***' : '*** DRY RUN (tambah --jalankan untuk eksekusi) ***');

        foreach ($data as $item) {
            $l = $item['lead'];
            $ada = Lead::withTrashed()->where('id', $l['id'])->exists();
            $this->line("#{$l['id']} {$l['nomor']} | PJ {$l['nama_leads']} | pasien {$l['nama_pasien']} -> "
                . ($ada ? 'SUDAH ADA, dilewati' : 'akan dipulihkan (' . count($item['notifikasi']) . ' notifikasi, ' . count($item['feedback']) . ' rating)'));
        }
        if (!$jalankan) { $this->newLine(); $this->info('Jalankan ulang dengan --jalankan untuk eksekusi.'); return 0; }

        $n = 0;
        DB::transaction(function () use ($data, &$n) {
            foreach ($data as $item) {
                if (Lead::withTrashed()->where('id', $item['lead']['id'])->exists()) continue;
                DB::table('cc_leads')->insert($this->baris('cc_leads', $item['lead']));
                foreach ($item['notifikasi'] as $r) DB::table('notifikasi')->insert($this->baris('notifikasi', $r));
                foreach ($item['feedback'] as $r) DB::table('feedback')->insert($this->baris('feedback', $r));
                $n++;
            }
        });
        // ID dimasukkan manual -> sinkronkan sequence Postgres supaya insert berikutnya tidak bentrok
        if (DB::getDriverName() === 'pgsql') {
            foreach (['cc_leads', 'notifikasi', 'feedback'] as $t) {
                DB::statement("SELECT setval(pg_get_serial_sequence('{$t}', 'id'), (SELECT COALESCE(MAX(id), 1) FROM {$t}))");
            }
        }
        $this->info("Selesai: {$n} Lead dipulihkan.");
        return 0;
    }

    /**
     * toArray() mengubah tanggal jadi ISO-8601 UTC ("...T..Z") -> kembalikan ke zona waktu app
     * (format DB) supaya jam/tanggal tidak bergeser; hanya kolom yg memang ada di tabel.
     */
    private function baris(string $tabel, array $r): array
    {
        static $kolom = [];
        $kolom[$tabel] ??= \Illuminate\Support\Facades\Schema::getColumnListing($tabel);
        $out = [];
        foreach ($r as $k => $v) {
            if (!in_array($k, $kolom[$tabel], true)) continue;
            if (is_string($v) && preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(\.\d+)?Z$/', $v)) {
                $v = Carbon::parse($v)->setTimezone(config('app.timezone'))->format('Y-m-d H:i:s');
            }
            if (is_array($v)) $v = json_encode($v);
            if (is_bool($v) && $tabel === 'notifikasi') $v = (int) $v; // is_read dkk smallint di Postgres
            $out[$k] = $v;
        }
        return $out;
    }
}
