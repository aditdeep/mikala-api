<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class MigrasiMitraLama extends Command
{
    protected $signature = 'migrasi:mitra-lama {--dry-run} {--limit=0}';
    protected $description = 'Migrasi data mitra lama dari CSV ke MGM (users + mitra)';

    public function handle()
    {
        $path = storage_path('app/mitra_lama.csv');
        if (!file_exists($path)) { $this->error("CSV tidak ada: $path"); return 1; }

        $dry = $this->option('dry-run');
        $limit = (int) $this->option('limit');

        $f = fopen($path, 'r');
        $header = fgetcsv($f);

        // cek email & nomor_induk yang sudah dipakai (idempotent + unik)
        $existingEmails = DB::table('users')->pluck('email')->map(fn($e)=>strtolower($e))->toArray();
        $existingIdLama = DB::table('mitra')->whereNotNull('data_tambahan')
            ->pluck('data_tambahan')->map(function($d){ $j=json_decode($d,true); return $j['id_lama']??null; })
            ->filter()->toArray();
        $seenNik = DB::table('mitra')->whereNotNull('nik')->pluck('nik')->toArray();

        $ok=0; $skip=0; $err=0; $rownum=0;
        $emailPakai = array_flip($existingEmails);
        $existingPhones = DB::table('users')->whereNotNull('phone')->pluck('phone')->toArray();
        $phonePakai = array_flip($existingPhones);

        while (($r = fgetcsv($f)) !== false) {
            $rownum++;
            if ($limit > 0 && $rownum > $limit) break;

            $idLama   = trim($r[0]);
            $nomorInduk = trim($r[1]);
            $nama     = trim($r[2]);
            $nik      = trim($r[3]);
            $gender   = trim($r[4]);
            $noHp     = trim($r[5]);
            $typeMitra= trim($r[6]);
            $usia     = trim($r[7]);
            $gajiPokok= trim($r[8]);
            $tunjangan= trim($r[9]);
            $tglLahirRaw = trim($r[10]);
            $asal     = trim($r[11]);
            $statusLama = trim($r[20]);
            $foto     = trim($r[21]);
            $pendNonFormal = trim($r[22]);
            $pengalaman = trim($r[23]);

            // idempotent: skip kalau id_lama sudah ke-import
            if (in_array($idLama, $existingIdLama)) { $skip++; continue; }

            // email dari nomor_induk (sanitize), fallback id_lama
            $base = $nomorInduk !== '' ? Str::slug($nomorInduk) : ('mitra-'.$idLama);
            $email = $base.'@mitra.mikalaglobalmedika.com';
            $suffix = 2;
            while (isset($emailPakai[strtolower($email)])) {
                $email = $base.'-'.$suffix.'@mitra.mikalaglobalmedika.com';
                $suffix++;
            }
            $emailPakai[strtolower($email)] = true;

            // password: no_hp valid, else default
            $hpValid = ($noHp !== '' && $noHp !== '-' && strlen($noHp) >= 8);
            $passPlain = $hpValid ? $noHp : 'mikala123';
$phoneBase = $hpValid ? $noHp : ('NOHP-' . ($nomorInduk ?: $idLama));
            $phoneFinal = $phoneBase;
            $psuffix = 2;
            while (isset($phonePakai[$phoneFinal])) {
                $phoneFinal = $phoneBase . '-' . $psuffix;
                $psuffix++;
            }
            $phonePakai[$phoneFinal] = true;

            // gender map
            $jk = stripos($gender,'Laki') !== false ? 'L' : (stripos($gender,'Perempuan') !== false ? 'P' : null);

            // status map
            $statusMap = [
                'On Job' => 'on_job', 'Available' => 'available',
                'Not Available' => 'inactive', 'Cuti' => 'inactive',
            ];
            $statusMitra = $statusMap[$statusLama] ?? 'inactive';

            // nik duplikat -> null + simpan asli
            $nikFinal = $nik;
            $nikAsli = null;
            if ($nik === '' || in_array($nik, $seenNik)) {
                $nikAsli = $nik;
                $nikFinal = 'MIG' . $idLama;
            }
            $seenNik[] = $nikFinal;

            // tanggal lahir: coba parse tahun dari tglLahirRaw, else null
            $tglLahir = null;
            if (preg_match('/(\d{4})/', $tglLahirRaw, $m)) {
                $tglLahir = $m[1].'-01-01'; // fallback: tahun-01-01
            }

            // data tambahan
            $dataTambahan = [
                'id_lama' => $idLama,
                'usia' => $usia ?: null,
                'tunjangan' => $tunjangan ?: null,
                'suku' => trim($r[12]) ?: null,
                'tinggi_badan' => trim($r[13]) ?: null,
                'berat_badan' => trim($r[14]) ?: null,
                'agama' => trim($r[15]) ?: null,
                'status_pernikahan' => trim($r[16]) ?: null,
                'terhadap_anjing' => trim($r[17]) ?: null,
                'memasak' => trim($r[18]) ?: null,
                'mabuk_kendaraan' => trim($r[24]) ?: null,
                'tempat_tgl_lahir_asli' => $tglLahirRaw ?: null,
                'asal_asli' => $asal ?: null,
                'foto_lama' => $foto ?: null,
                'pendidikan_non_formal' => $pendNonFormal ?: null,
            ];
            if ($nikAsli !== null) $dataTambahan['nik_asli_duplikat'] = $nikAsli;

            if ($dry) {
                $this->line("[DRY] $nama | email:$email | pass:$passPlain | jk:$jk | status:$statusMitra | nik:".($nikFinal??'NULL'));
                $ok++;
                continue;
            }

            try {
                DB::beginTransaction();
                $userId = DB::table('users')->insertGetId([
                    'name' => $nama,
                    'email' => $email,
                    'phone' => $phoneFinal,
                    'password' => Hash::make($passPlain),
                    'role' => 'mitra',
                    'status' => 'active',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                DB::table('mitra')->insert([
                    'user_id' => $userId,
                    'nomor_induk' => $nomorInduk ?: null,
                    'nik' => $nikFinal,
                    'nama_lengkap' => $nama,
                    'tanggal_lahir' => $tglLahir ?: '1990-01-01',
                    'jenis_kelamin' => $jk ?: 'L',
                    'alamat' => $asal ?: '-',
                    'kota' => $asal ?: '-',
                    'provinsi' => '-',
                    'pendidikan_terakhir' => trim($r[19]) ?: '-',
                    'pengalaman' => $pengalaman ?: null,
                    'status' => $statusMitra,
                    'jabatan' => $typeMitra ?: null,
                    'gaji_bulanan' => is_numeric($gajiPokok) ? $gajiPokok : null,
                    'foto_url' => null,
                    'data_tambahan' => json_encode($dataTambahan),
                    'is_verified' => DB::raw('false'),
                    'training_status' => 'pending',
                    'rating' => 0,
                    'total_reviews' => 0,
                    'total_jobs' => 0,
                    'status_rekrutmen' => 'pending',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                DB::commit();
                $ok++;
            } catch (\Throwable $e) {
                DB::rollBack();
                $err++;
                $this->error("Row $rownum ($nama): ".substr($e->getMessage(),0,120));
            }
        }
        fclose($f);

        $this->info("Selesai. OK:$ok | SKIP:$skip | ERROR:$err".($dry?' (DRY RUN)':''));
        return 0;
    }
}
