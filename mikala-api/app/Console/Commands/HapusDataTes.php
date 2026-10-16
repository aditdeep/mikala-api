<?php

namespace App\Console\Commands;

use App\Models\Feedback;
use App\Models\Lead;
use App\Models\Mitra;
use App\Models\Notifikasi;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Hapus data tes (Leads/Deal hasil uji coba apps klien) beserta notifikasi & rating terkait,
 * supaya tidak nyampah di production.
 *
 *   php artisan data:hapus-tes                       daftar Leads dari Apps Klien (cari ID-nya)
 *   php artisan data:hapus-tes --lead=12 --lead=13   DRY RUN: lihat apa saja yg akan terhapus
 *   php artisan data:hapus-tes --lead=12 --jalankan  eksekusi (backup JSON dulu ke storage/app)
 *
 * Pengaman: Lead yg sudah punya payroll / exchange / tagihan / kontrak dilewati (itu data
 * operasional, bukan sekadar tes) -- rapikan manual kalau memang mau dihapus.
 */
class HapusDataTes extends Command
{
    protected $signature = 'data:hapus-tes {--lead=* : ID Lead yg mau dihapus} {--jalankan}';
    protected $description = 'Hapus Leads tes + notifikasi & rating terkait (default dry-run)';

    public function handle()
    {
        $ids = array_filter(array_map('intval', (array) $this->option('lead')));

        if (!$ids) {
            $this->info('Leads dari Apps Klien (30 terbaru) -- pilih ID yg data tes, lalu jalankan dgn --lead=ID:');
            $rows = Lead::where('sumber', 'Apps Klien')->latest('id')->limit(30)->get()
                ->map(fn ($l) => [$l->id, $l->nomor, $l->nama_leads, $l->nama_pasien, $this->status($l), $l->created_at?->format('d/m/Y H:i')]);
            $this->table(['ID', 'Nomor', 'PJ', 'Pasien', 'Status', 'Dibuat'], $rows->all());
            return 0;
        }

        $jalankan = (bool) $this->option('jalankan');
        $this->info($jalankan ? '*** MODE EKSEKUSI ***' : '*** DRY RUN (tambah --jalankan untuk eksekusi) ***');

        $hapus = [];
        foreach ($ids as $id) {
            $l = Lead::withTrashed()->find($id);
            if (!$l) { $this->warn("#{$id}: tidak ditemukan"); continue; }

            $alasan = [];
            if ($l->payrolls()->exists()) $alasan[] = 'sudah ada payroll';
            if ($l->exchanges()->exists()) $alasan[] = 'sudah ada exchange';
            if ($l->tagihan_admin_id) $alasan[] = 'sudah ada tagihan';
            if ($l->nomor_kontrak_klien || $l->nomor_kontrak_mitra) $alasan[] = 'sudah ada nomor kontrak';
            if ($alasan) { $this->warn("#{$id} {$l->nomor} ({$l->nama_pasien}) DILEWATI: " . implode(', ', $alasan)); continue; }

            $notif = Notifikasi::whereIn('related_type', ['cc_lead', 'lead', 'App\\Models\\Lead'])->where('related_id', $l->id);
            $fb = Feedback::where('lead_id', $l->id);
            $this->line("#{$id} {$l->nomor} | PJ {$l->nama_leads} | pasien {$l->nama_pasien} | {$this->status($l)} | sumber " . ($l->sumber ?: '-')
                . " -> {$notif->count()} notifikasi, {$fb->count()} rating");
            $hapus[] = $l;
        }

        if (!$hapus) { $this->info('Tidak ada yg dihapus.'); return 0; }
        if (!$jalankan) { $this->newLine(); $this->info('Jalankan ulang dengan --jalankan untuk eksekusi.'); return 0; }

        $backup = [];
        DB::transaction(function () use ($hapus, &$backup) {
            foreach ($hapus as $l) {
                $notif = Notifikasi::whereIn('related_type', ['cc_lead', 'lead', 'App\\Models\\Lead'])->where('related_id', $l->id);
                $fb = Feedback::withTrashed()->where('lead_id', $l->id);
                $mitraIds = (clone $fb)->pluck('mitra_id')->unique();
                $backup[] = ['lead' => $l->toArray(), 'notifikasi' => (clone $notif)->get()->toArray(), 'feedback' => (clone $fb)->get()->toArray()];

                $notif->delete();
                $fb->forceDelete();
                $l->forceDelete();
                // rating mitra dihitung ulang tanpa rating tes
                Mitra::whereIn('id', $mitraIds)->get()->each->updateRating();
            }
        });

        $path = storage_path('app/backup_hapus_tes_' . now()->format('Ymd_His') . '.json');
        file_put_contents($path, json_encode($backup, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        @chmod($path, 0600);
        $this->info('Selesai: ' . count($hapus) . ' Lead dihapus. Backup: ' . $path);
        return 0;
    }

    private function status(Lead $l): string
    {
        return [0 => 'Proses', 1 => 'Deal', 2 => 'Batal', 3 => 'Gantung', 4 => 'Stop'][(int) $l->status] ?? (string) $l->status;
    }
}
