<?php

namespace App\Console\Commands;

use App\Models\Mitra;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Update data mitra yang sudah ada di sistem baru dengan data dari export sistem lama
 * (storage/app/mitra_lama_update.csv), TANPA menimpa data yang sudah pernah dikoreksi
 * manual di sistem baru, dan TANPA duplikat mitra.
 *
 * Cara pakai:
 *   php artisan migrasi:mitra-update --dry-run   (lihat dulu apa yang akan terjadi)
 *   php artisan migrasi:mitra-update             (jalankan beneran)
 *
 * Aturan:
 * - Setiap baris CSV dicocokkan ke mitra yang sudah ada di DB, urutan prioritas:
 *     1) NIK sama persis (dan NIK itu unik di CSV & di DB, biar ga salah tempel)
 *     2) Nama + Tempat Lahir sama persis (case-insensitive), kalau NIK ga match/kosong
 *   Kalau tidak ada yang cocok -> dianggap mitra baru, dibuatkan user+mitra baru.
 * - Kalau mitra ketemu (matched): field yang di sistem baru MASIH KOSONG akan diisi dari
 *   data lama. Field yang SUDAH ADA ISINYA di sistem baru TIDAK disentuh (anggap sudah
 *   dikoreksi manual oleh admin).
 * - NIM (nomor_induk): sesuai instruksi, format NIM lama (mis. "467/PHC-MGM/2026") TIDAK
 *   dipakai lagi. Kalau nomor_induk mitra masih kosong ATAU masih format lama (mengandung
 *   karakter "/"), maka di-generate ulang pakai format baru Mitra::generateNim() (mis.
 *   CG.03.26-001). Kalau sudah format baru (hasil migrasi sebelumnya / auto-generate di
 *   sistem baru), dibiarkan, tidak di-generate ulang.
 * - Idempotent: NIM lama disimpan di data_tambahan->nim_lama, dipakai untuk skip baris yang
 *   sudah pernah diproses kalau command dijalankan ulang.
 * - Type Mitra lama ("Perawat"/"Caregiver"/"Caregiver Senior") dipetakan ke Tipe Pekerjaan
 *   di sistem baru cuma untuk menentukan kode NIM & mengisi tipe_pekerjaan KALAU field itu
 *   masih kosong; kalau mitra sudah punya tipe_pekerjaan sendiri, itu yg dipakai (tidak
 *   ditimpa).
 */
class MigrasiMitraUpdate extends Command
{
    protected $signature = 'migrasi:mitra-update {--dry-run} {--limit=0}';
    protected $description = 'Update-only import data mitra dari export sistem lama (CSV) ke sistem baru, isi NIM format baru';

    // Pemetaan Type Mitra (sistem lama) -> Tipe Pekerjaan (sistem baru, lihat Mitra::TIPE_KODE_MAP)
    const TYPE_MAP = [
        'Perawat'          => 'Perawat Homecare',
        'Caregiver'        => 'Perawat Lansia / Caregiver',
        'Caregiver Senior' => 'Perawat Lansia / Caregiver',
    ];

    public function handle()
    {
        $path = storage_path('app/mitra_lama_update.csv');
        if (!file_exists($path)) {
            $this->error("CSV tidak ada: $path");
            $this->line('Taruh file mitra_lama_update.csv di folder storage/app/ dulu ya.');
            return 1;
        }

        $dry = (bool) $this->option('dry-run');
        $limit = (int) $this->option('limit');

        $f = fopen($path, 'r');
        $header = fgetcsv($f);

        $existingNimLama = DB::table('mitra')->whereNotNull('data_tambahan')
            ->pluck('data_tambahan', 'id')
            ->map(function ($d) { $j = json_decode($d, true); return $j['nim_lama'] ?? null; })
            ->filter();
        $sudahDiimpor = $existingNimLama->flip(); // nim_lama => mitra id, utk skip cepat

        $rownum = 0; $matched = 0; $created = 0; $skip = 0; $err = 0;
        $ambigu = []; // baris yg NIK/nama-nya cocok >1 mitra, dilaporkan tapi tidak diproses

        while (($r = fgetcsv($f)) !== false) {
            $rownum++;
            if ($limit > 0 && $rownum > $limit) break;
            $row = array_combine($header, $r);
            if (!$row || empty($row['nim_lama'])) { continue; }

            $nimLama = trim($row['nim_lama']);
            if (isset($sudahDiimpor[$nimLama])) { $skip++; continue; } // idempotent

            $nama = trim($row['nama']);
            $nik = trim($row['nik']);
            $tempatTglLahir = trim($row['tempat_tgl_lahir']);
            [$tempatLahir, $tglLahir] = $this->parseTempatTglLahir($tempatTglLahir);

            // 1) cari kandidat via NIK (kalau ada & unik)
            $mitra = null;
            if ($nik !== '') {
                $kandidat = Mitra::where('nik', $nik)->get();
                if ($kandidat->count() === 1) {
                    $mitra = $kandidat->first();
                } elseif ($kandidat->count() > 1) {
                    $ambigu[] = "Row $rownum ($nama): NIK $nik cocok ke >1 mitra, dilewati -- cek manual.";
                    continue;
                }
            }

            // 2) fallback: nama + tempat lahir (case-insensitive)
            if (!$mitra && $nama !== '') {
                $q = Mitra::whereRaw('LOWER(nama_lengkap) = ?', [mb_strtolower($nama)]);
                if ($tempatLahir) {
                    $q->whereRaw('LOWER(tempat_lahir) = ?', [mb_strtolower($tempatLahir)]);
                }
                $kandidat = $q->get();
                if ($kandidat->count() === 1) {
                    $mitra = $kandidat->first();
                } elseif ($kandidat->count() > 1) {
                    $ambigu[] = "Row $rownum ($nama): nama+tempat lahir cocok ke >1 mitra, dilewati -- cek manual.";
                    continue;
                }
            }

            $tipePekerjaanLama = self::TYPE_MAP[trim((string) $row['type_mitra'])] ?? null;

            if ($mitra) {
                // ---- UPDATE, cuma isi field yang masih kosong ----
                $update = [];
                $fillIfEmpty = [
                    'nik'                  => $nik ?: null,
                    'jenis_kelamin'        => $this->mapGender($row['gender']),
                    'tempat_lahir'         => $tempatLahir,
                    'tanggal_lahir'        => $tglLahir,
                    'alamat'               => trim((string) $row['asal']) ?: null,
                    'kota'                 => trim((string) $row['asal']) ?: null,
                    'suku'                 => trim((string) $row['suku']) ?: null,
                    'tinggi_badan'         => $this->numOrNull($row['tb']),
                    'berat_badan'          => $this->numOrNull($row['bb']),
                    'agama'                => trim((string) $row['agama']) ?: null,
                    'status_nikah'         => trim((string) $row['status_pernikahan']) ?: null,
                    'takut_hewan'          => trim((string) $row['takut_anjing']) ?: null,
                    'bisa_memasak'         => trim((string) $row['memasak']) ?: null,
                    'pendidikan_terakhir'  => trim((string) $row['pendidikan_formal']) ?: null,
                    'pengalaman_pelatihan' => trim((string) $row['pendidikan_non_formal']) ?: null,
                    'pengalaman'           => trim((string) $row['pengalaman']) ?: null,
                    'tipe_pekerjaan'       => $tipePekerjaanLama,
                    'gaji_bulanan'         => $this->numOrNull($row['gaji_pokok']),
                ];
                foreach ($fillIfEmpty as $field => $val) {
                    if ($val === null) continue;
                    if (blank($mitra->{$field} ?? null)) {
                        $update[$field] = $val;
                    }
                }

                // NIM: cuma di-generate ulang kalau masih kosong atau masih format lama (ada '/')
                $nimSaatIni = $mitra->nomor_induk;
                if (blank($nimSaatIni) || str_contains($nimSaatIni, '/')) {
                    $tipeUntukNim = $update['tipe_pekerjaan'] ?? $mitra->tipe_pekerjaan;
                    $update['nomor_induk'] = Mitra::generateNim($tipeUntukNim);
                }

                // data_tambahan: merge, jangan timpa key lain yg sudah ada
                $dataTambahanLama = json_decode($mitra->data_tambahan ?: '{}', true) ?: [];
                $dataTambahanLama['nim_lama'] = $nimLama;
                $dataTambahanLama['usia_lama'] = trim((string) $row['usia']) ?: null;
                $dataTambahanLama['tunjangan_lama'] = trim((string) $row['tunjangan']) ?: null;
                $dataTambahanLama['status_lama'] = trim((string) $row['status_lama']) ?: null;
                $dataTambahanLama['mabuk_kendaraan_lama'] = trim((string) $row['mabuk_kendaraan']) ?: null;
                $update['data_tambahan'] = json_encode($dataTambahanLama);

                if ($dry) {
                    $this->line("[DRY][UPDATE] mitra #{$mitra->id} ($nama): " . json_encode(array_keys($update)));
                } else {
                    try {
                        $mitra->update($update);
                    } catch (\Throwable $e) {
                        $err++;
                        $this->error("Row $rownum ($nama): ".substr($e->getMessage(), 0, 150));
                        continue;
                    }
                }
                $matched++;
            } else {
                // ---- BARU: bikin user + mitra baru ----
                $noHp = trim((string) $row['no_hp']);
                $hpValid = ($noHp !== '' && $noHp !== '-' && strlen($noHp) >= 8);
                $passPlain = $hpValid ? $noHp : 'mikala123';

                if ($dry) {
                    $this->line("[DRY][CREATE] $nama (nim_lama:$nimLama)");
                    $created++;
                    continue;
                }

                try {
                    DB::beginTransaction();
                    $email = ($nik !== '' ? $nik : 'mitra-'.\Illuminate\Support\Str::slug($nimLama)).'@mitra.mikalaglobalmedika.com';
                    $suffix = 2;
                    while (DB::table('users')->where('email', $email)->exists()) {
                        $email = ($nik !== '' ? $nik : \Illuminate\Support\Str::slug($nimLama)).'-'.$suffix.'@mitra.mikalaglobalmedika.com';
                        $suffix++;
                    }
                    $phone = $hpValid ? $noHp : ('NOHP-'.\Illuminate\Support\Str::slug($nimLama));
                    $suffixP = 2;
                    $phoneFinal = $phone;
                    while (DB::table('users')->where('phone', $phoneFinal)->exists()) {
                        $phoneFinal = $phone.'-'.$suffixP; $suffixP++;
                    }

                    $userId = DB::table('users')->insertGetId([
                        'name' => $nama, 'email' => $email, 'phone' => $phoneFinal,
                        'password' => \Illuminate\Support\Facades\Hash::make($passPlain),
                        'role' => 'mitra', 'status' => 'active',
                        'created_at' => now(), 'updated_at' => now(),
                    ]);

                    $nimBaru = Mitra::generateNim($tipePekerjaanLama);
                    // NIK kosong di data lama -> kolom nik NOT NULL di DB, jadi kasih placeholder
                    // unik berbasis NIM lama, asli (kosong) dicatat di data_tambahan.nik_kosong_lama
                    $nikFinal = $nik ?: ('MIG-' . \Illuminate\Support\Str::slug($nimLama));
                    $dataTambahan = [
                        'nim_lama' => $nimLama,
                        'usia_lama' => trim((string) $row['usia']) ?: null,
                        'tunjangan_lama' => trim((string) $row['tunjangan']) ?: null,
                        'status_lama' => trim((string) $row['status_lama']) ?: null,
                        'mabuk_kendaraan_lama' => trim((string) $row['mabuk_kendaraan']) ?: null,
                        'nik_kosong_lama' => $nik === '' ? true : null,
                    ];

                    Mitra::create([
                        'user_id' => $userId,
                        'nomor_induk' => $nimBaru,
                        'nik' => $nikFinal,
                        'nama_lengkap' => $nama,
                        'tanggal_lahir' => $tglLahir ?: '1990-01-01',
                        'jenis_kelamin' => $this->mapGender($row['gender']) ?: 'L',
                        'tempat_lahir' => $tempatLahir,
                        'alamat' => trim((string) $row['asal']) ?: '-',
                        'kota' => trim((string) $row['asal']) ?: '-',
                        'provinsi' => '-',
                        'suku' => trim((string) $row['suku']) ?: null,
                        'tinggi_badan' => $this->numOrNull($row['tb']),
                        'berat_badan' => $this->numOrNull($row['bb']),
                        'agama' => trim((string) $row['agama']) ?: null,
                        'status_nikah' => trim((string) $row['status_pernikahan']) ?: null,
                        'takut_hewan' => trim((string) $row['takut_anjing']) ?: null,
                        'bisa_memasak' => trim((string) $row['memasak']) ?: null,
                        'pendidikan_terakhir' => trim((string) $row['pendidikan_formal']) ?: '-',
                        'pengalaman_pelatihan' => trim((string) $row['pendidikan_non_formal']) ?: null,
                        'pengalaman' => trim((string) $row['pengalaman']) ?: null,
                        'tipe_pekerjaan' => $tipePekerjaanLama,
                        'gaji_bulanan' => $this->numOrNull($row['gaji_pokok']),
                        'status' => 'inactive',
                        'is_verified' => DB::raw('false'), // hindari "integer vs boolean" type mismatch di Postgres
                        'status_rekrutmen' => 'pending',
                        'training_status' => 'pending',
                        'rating' => 0, 'total_reviews' => 0, 'total_jobs' => 0,
                        'data_tambahan' => json_encode($dataTambahan),
                    ]);
                    DB::commit();
                    $created++;
                } catch (\Throwable $e) {
                    DB::rollBack();
                    $err++;
                    $this->error("Row $rownum ($nama): ".substr($e->getMessage(), 0, 150));
                }
            }
        }
        fclose($f);

        if ($ambigu) {
            $this->warn('--- Baris ambigu (dilewati, cek manual) ---');
            foreach ($ambigu as $line) { $this->warn($line); }
        }

        $this->info("Selesai. Matched/updated:$matched | Baru dibuat:$created | Skip (sudah pernah diimpor):$skip | Ambigu:".count($ambigu)." | Error:$err".($dry ? ' (DRY RUN, tidak ada yg disimpan)' : ''));
        return 0;
    }

    private function mapGender($g): ?string
    {
        $g = trim((string) $g);
        if (stripos($g, 'Laki') !== false) return 'L';
        if (stripos($g, 'Perempuan') !== false) return 'P';
        return null;
    }

    private function numOrNull($v)
    {
        $v = trim((string) $v);
        return ($v !== '' && is_numeric($v)) ? $v : null;
    }

    /** "Batang, 8 Juli 2002" -> ['Batang', '2002-07-08'] (best-effort, fallback tahun-01-01 kalau bulan gagal parse) */
    private function parseTempatTglLahir(?string $raw): array
    {
        $raw = trim((string) $raw);
        if ($raw === '') return [null, null];
        $parts = explode(',', $raw, 2);
        $tempat = trim($parts[0]) ?: null;
        $tglRaw = isset($parts[1]) ? trim($parts[1]) : '';
        $tgl = null;
        if ($tglRaw !== '') {
            try {
                $tgl = \Carbon\Carbon::parse($tglRaw)->format('Y-m-d');
            } catch (\Throwable $e) {
                if (preg_match('/(\d{4})/', $tglRaw, $m)) {
                    $tgl = $m[1].'-01-01';
                }
            }
        }
        return [$tempat, $tgl];
    }
}
