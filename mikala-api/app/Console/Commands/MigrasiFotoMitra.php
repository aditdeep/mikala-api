<?php

namespace App\Console\Commands;

use App\Models\Mitra;
use Cloudinary\Cloudinary;
use Illuminate\Console\Command;

/**
 * Migrasi foto mitra dari sistem lama (URL di sis.mikalaglobalmedika.com/uploads/<file>) ke
 * Cloudinary (storage foto yang dipakai sistem baru), lalu isi kolom mitra.foto_url.
 *
 * Sumber data: storage/app/mitra_foto.csv -- CSV dipisah titik-koma (;), kolom-kolom persis
 * export "mitra (full)" dari sistem lama: id_mitra;nomor_induk_mitra;nama_mitra;nik;...;foto;...
 * (foto = nama file, mis. "689c0bb740c10.jpeg", diakses di <base-url>/uploads/<foto>).
 *
 * Cara pakai:
 *   php artisan migrasi:foto-mitra --dry-run   (preview, gak upload/simpen apa2)
 *   php artisan migrasi:foto-mitra             (jalankan beneran)
 *   php artisan migrasi:foto-mitra --force     (timpa foto_url yg sudah ada juga -- hati2)
 *
 * Aturan:
 * - Match mitra prioritas: 1) data_tambahan->nim_lama (paling presisi, hasil migrasi CSV
 *   sebelumnya), 2) NIK persis & unik, 3) nama + tempat lahir persis & unik.
 * - Mitra yang SUDAH punya foto_url TIDAK ditimpa (dianggap sudah diupload manual), kecuali
 *   pakai --force.
 * - Foto di-upload ke Cloudinary langsung dari URL sistem lama (Cloudinary yang fetch),
 *   folder "mikala/mitra-lama", biar gak gantung ke server sistem lama kalau nanti dimatikan.
 */
class MigrasiFotoMitra extends Command
{
    protected $signature = 'migrasi:foto-mitra {--dry-run} {--force} {--limit=0} {--base-url=https://sis.mikalaglobalmedika.com/uploads/}';
    protected $description = 'Download foto mitra dari sistem lama & upload ke Cloudinary, isi mitra.foto_url';

    public function handle()
    {
        $path = storage_path('app/mitra_foto.csv');
        if (!file_exists($path)) {
            $this->error("CSV tidak ada: $path");
            $this->line('Taruh file mitra_foto.csv (export "mitra (full)" dari sistem lama) di storage/app/ dulu ya.');
            return 1;
        }

        $dry = (bool) $this->option('dry-run');
        $force = (bool) $this->option('force');
        $limit = (int) $this->option('limit');
        $baseUrl = rtrim($this->option('base-url'), '/') . '/';

        $f = fopen($path, 'r');
        $header = fgetcsv($f, 0, ';');

        $cloudinary = $dry ? null : new Cloudinary(config('cloudinary.cloud_url'));

        $rownum = 0; $updated = 0; $skippedHasFoto = 0; $skippedNoFoto = 0; $notFound = 0; $err = 0;
        $ambigu = [];

        while (($r = fgetcsv($f, 0, ';')) !== false) {
            $rownum++;
            if ($limit > 0 && $rownum > $limit) break;
            $row = array_combine($header, $r);
            if (!$row) continue;

            $foto = trim((string) ($row['foto'] ?? ''));
            if ($foto === '' || $foto === '-') { $skippedNoFoto++; continue; }

            $nimLama = trim((string) ($row['nomor_induk_mitra'] ?? ''));
            $nik = trim((string) ($row['nik'] ?? ''));
            $nama = trim((string) ($row['nama_mitra'] ?? ''));
            $tempatLahir = null;
            if (!empty($row['tempat_tgl_lahir'])) {
                $parts = explode(',', $row['tempat_tgl_lahir'], 2);
                $tempatLahir = trim($parts[0]) ?: null;
            }

            // 1) match via data_tambahan->nim_lama (paling presisi)
            $mitra = null;
            if ($nimLama !== '') {
                $mitra = Mitra::whereRaw("data_tambahan->>'nim_lama' = ?", [$nimLama])->first();
            }
            // 2) fallback NIK unik
            if (!$mitra && $nik !== '') {
                $kandidat = Mitra::where('nik', $nik)->get();
                if ($kandidat->count() === 1) { $mitra = $kandidat->first(); }
                elseif ($kandidat->count() > 1) { $ambigu[] = "Row $rownum ($nama): NIK $nik cocok >1 mitra."; continue; }
            }
            // 3) fallback nama + tempat lahir
            if (!$mitra && $nama !== '') {
                $q = Mitra::whereRaw('LOWER(nama_lengkap) = ?', [mb_strtolower($nama)]);
                if ($tempatLahir) $q->whereRaw('LOWER(tempat_lahir) = ?', [mb_strtolower($tempatLahir)]);
                $kandidat = $q->get();
                if ($kandidat->count() === 1) { $mitra = $kandidat->first(); }
                elseif ($kandidat->count() > 1) { $ambigu[] = "Row $rownum ($nama): nama+tempat lahir cocok >1 mitra."; continue; }
            }

            if (!$mitra) { $notFound++; continue; }
            if (!$force && !blank($mitra->foto_url)) { $skippedHasFoto++; continue; }

            $sourceUrl = $baseUrl . $foto;

            if ($dry) {
                $this->line("[DRY] mitra #{$mitra->id} ({$nama}) <- $sourceUrl");
                $updated++;
                continue;
            }

            try {
                $result = $cloudinary->uploadApi()->upload($sourceUrl, [
                    'folder' => 'mikala/mitra-lama',
                    'resource_type' => 'image',
                ]);
                $mitra->update(['foto_url' => $result['secure_url']]);
                $updated++;
            } catch (\Throwable $e) {
                $err++;
                $this->error("Row $rownum ($nama, mitra #{$mitra->id}): " . substr($e->getMessage(), 0, 150));
            }
        }
        fclose($f);

        if ($ambigu) {
            $this->warn('--- Baris ambigu (dilewati, cek manual) ---');
            foreach ($ambigu as $line) $this->warn($line);
        }

        $this->info("Selesai. Ke-upload:$updated | Skip (sudah ada foto):$skippedHasFoto | Skip (foto lama kosong):$skippedNoFoto | Mitra tidak ketemu:$notFound | Ambigu:" . count($ambigu) . " | Error:$err" . ($dry ? ' (DRY RUN)' : ''));
        return 0;
    }
}
