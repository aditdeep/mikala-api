<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * FIX: field usia/tempat_lahir/tinggi/berat/vaksin/agama/status_nikah/takut_hewan/
 * bisa_memasak/tipe_pekerjaan/suku sebelumnya di-encode jadi satu string di kolom
 * `pengalaman` (format "PELATIHAN: ...\n\nPENGALAMAN KERJA: ...\n\nDATA TAMBAHAN: Usia: X, ...")
 * lalu di-parse balik pakai regex di frontend saat Edit dibuka. Ini rapuh, dan lebih parah:
 * mitra yang self-register lewat mitra.mikalaglobalmedika.com/auth/register (MitraRegisterController)
 * ternyata TIDAK PERNAH menyimpan tinggi/berat/vaksin/tipe_pekerjaan sama sekali (field ada di
 * form publiknya, tapi controllernya diam-diam drop semua field itu) -- makanya field2 itu selalu
 * kosong/kembali ke default begitu mitra tsb dibuka di modal Edit Rekrutmen.
 *
 * Fix-nya: kasih kolom asli masing2, migrasi backfill dari blob lama (best-effort regex,
 * utk mitra yg didaftarkan lewat form internal Rekrutmen yg memang encode ke blob), dan
 * `pengalaman` dirapikan lagi supaya cuma isinya pengalaman kerja plain text.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mitra', function (Blueprint $table) {
            if (!Schema::hasColumn('mitra', 'tempat_lahir'))      $table->string('tempat_lahir')->nullable()->after('tanggal_lahir');
            if (!Schema::hasColumn('mitra', 'tinggi_badan'))      $table->string('tinggi_badan')->nullable()->after('pengalaman');
            if (!Schema::hasColumn('mitra', 'berat_badan'))       $table->string('berat_badan')->nullable()->after('tinggi_badan');
            if (!Schema::hasColumn('mitra', 'vaksin'))            $table->string('vaksin')->nullable()->after('berat_badan');
            if (!Schema::hasColumn('mitra', 'agama'))             $table->string('agama')->nullable()->after('vaksin');
            if (!Schema::hasColumn('mitra', 'status_nikah'))      $table->string('status_nikah')->nullable()->after('agama');
            if (!Schema::hasColumn('mitra', 'takut_hewan'))       $table->string('takut_hewan')->nullable()->after('status_nikah');
            if (!Schema::hasColumn('mitra', 'bisa_memasak'))      $table->string('bisa_memasak')->nullable()->after('takut_hewan');
            if (!Schema::hasColumn('mitra', 'tipe_pekerjaan'))    $table->string('tipe_pekerjaan')->nullable()->after('bisa_memasak');
            if (!Schema::hasColumn('mitra', 'suku'))              $table->string('suku')->nullable()->after('tipe_pekerjaan');
            if (!Schema::hasColumn('mitra', 'pengalaman_pelatihan')) $table->text('pengalaman_pelatihan')->nullable()->after('suku');
        });

        // Backfill dari blob lama (kalau ada). Aman dijalankan berkali-kali (idempotent secara
        // efektif karena field yg sudah keisi tidak akan di-parse ulang jadi beda).
        $rows = DB::table('mitra')->whereNotNull('pengalaman')->get(['id', 'pengalaman']);
        foreach ($rows as $row) {
            $raw = $row->pengalaman;
            if (!$raw || !str_contains($raw, 'DATA TAMBAHAN:')) continue;

            $grab = function (string $label) use ($raw) {
                if (preg_match('/'.preg_quote($label, '/').':\s*([^,]*)/', $raw, $m)) {
                    return trim($m[1]);
                }
                return null;
            };

            $pelatihan = null;
            if (preg_match('/PELATIHAN:\s*(.*?)\n\nPENGALAMAN KERJA:/s', $raw, $m)) $pelatihan = trim($m[1]);
            $kerja = null;
            if (preg_match('/PENGALAMAN KERJA:\s*(.*?)\n\nDATA TAMBAHAN:/s', $raw, $m)) $kerja = trim($m[1]);

            $tinggi = $grab('TB'); if ($tinggi) $tinggi = preg_replace('/cm$/i', '', $tinggi);
            $berat  = $grab('BB'); if ($berat) $berat = preg_replace('/kg$/i', '', $berat);
            $memasak = $grab('Memasak'); if ($memasak) $memasak = preg_replace('#/5$#', '', $memasak);

            DB::table('mitra')->where('id', $row->id)->update(array_filter([
                'tempat_lahir'         => $grab('Tempat Lahir'),
                'tinggi_badan'         => $tinggi,
                'berat_badan'          => $berat,
                'vaksin'               => $grab('Vaksin'),
                'agama'                => $grab('Agama'),
                'status_nikah'         => $grab('Status Nikah'),
                'takut_hewan'          => $grab('Takut Hewan'),
                'bisa_memasak'         => $memasak,
                'tipe_pekerjaan'       => $grab('Tipe Pekerjaan'),
                'suku'                 => $grab('Suku'),
                'pengalaman_pelatihan' => $pelatihan,
            ], fn($v) => !is_null($v) && $v !== ''));

            // Rapikan pengalaman jadi cuma pengalaman kerja plain text (kalau ketemu bagiannya)
            if ($kerja !== null) {
                DB::table('mitra')->where('id', $row->id)->update(['pengalaman' => $kerja]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('mitra', function (Blueprint $table) {
            foreach (['tempat_lahir','tinggi_badan','berat_badan','vaksin','agama','status_nikah','takut_hewan','bisa_memasak','tipe_pekerjaan','suku','pengalaman_pelatihan'] as $col) {
                if (Schema::hasColumn('mitra', $col)) $table->dropColumn($col);
            }
        });
    }
};
