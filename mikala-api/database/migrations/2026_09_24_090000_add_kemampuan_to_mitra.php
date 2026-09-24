<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Field baru "Kemampuan" -- list bebas (mis. "Merawat luka", "Injeksi", "Menyetir") yang diisi
 * mitra sendiri saat daftar, ditampilkan di kartu CV bagian "Kemampuan Khusus" (sidebar kiri).
 * Disimpan sbg JSON array of string (text column, di-encode/decode json_encode/json_decode di
 * controller -- konsisten dgn kolom list lain di CMS seperti hero_slides/alasan_list dsb).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mitra', function (Blueprint $table) {
            if (!Schema::hasColumn('mitra', 'kemampuan')) {
                $table->text('kemampuan')->nullable()->after('pengalaman_pelatihan');
            }
        });
    }

    public function down(): void
    {
        Schema::table('mitra', function (Blueprint $table) {
            if (Schema::hasColumn('mitra', 'kemampuan')) $table->dropColumn('kemampuan');
        });
    }
};
