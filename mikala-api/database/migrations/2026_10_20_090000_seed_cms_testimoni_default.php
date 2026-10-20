<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 4 testimoni contoh yg dulu ditulis langsung di kode homepage MGM (tampil kalau tabel kosong)
 * dipindah ke database supaya bisa diedit/dihapus dari CMS. Hanya diisi kalau tabel masih kosong.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('cms_testimoni') || DB::table('cms_testimoni')->exists()) return;
        $now = now();
        $rows = [
            ['Anna', 'Pelayanan Penunjang', 'Layanan ramah dan bermanfaat bagi masyarakat luas. Semoga terus berkembang!'],
            ['Kartika', 'Perawat Medis', 'Sangat puas, sesuai harapan. Pertahankan pelayanan yang sudah sangat baik!'],
            ['Andi', 'Caregiver', 'Sangat bagus, bisa update kondisi kesehatan saya. Mudah-mudahan berkah ya!'],
            ['John', 'Perawat Jiwa', 'Program berjalan baik dan timeline sesuai yang direncanakan. Recommended!'],
        ];
        foreach ($rows as $i => [$nama, $layanan, $komentar]) {
            DB::table('cms_testimoni')->insert([
                'nama' => $nama, 'layanan' => $layanan, 'rating' => 5, 'komentar' => $komentar,
                'status' => 'approved', 'created_at' => $now->copy()->subMinutes($i), 'updated_at' => $now,
            ]);
        }
    }

    public function down(): void {}
};
