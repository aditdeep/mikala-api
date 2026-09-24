<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * FIX: fitur "Ceklis Semua" (bulk checklist per kategori) di Training sekarang ngirim nilai
 * rata-rata manual yang boleh desimal (mis. 4.5), bukan cuma bintang 1-5 bulat kayak ceklis
 * satuan. Kalau kolom `rating` di tabel training_checklist masih integer, insert desimal bisa
 * gagal (Postgres nolak "invalid input syntax for type integer"). Migration ini gak ada di repo
 * sebelumnya (tabel training_checklist dibuat manual di luar migration Laravel), jadi di sini
 * cuma ALTER TYPE kalau kolomnya kedetek masih bukan numeric/decimal -- aman dijalankan berkali2.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('training_checklist') || !Schema::hasColumn('training_checklist', 'rating')) {
            return;
        }
        $col = DB::selectOne("
            SELECT data_type FROM information_schema.columns
            WHERE table_name = 'training_checklist' AND column_name = 'rating'
        ");
        if ($col && !in_array($col->data_type, ['numeric', 'double precision', 'real'])) {
            DB::statement("ALTER TABLE training_checklist ALTER COLUMN rating TYPE numeric(3,1) USING rating::numeric(3,1)");
        }
    }

    public function down(): void
    {
        // Sengaja tidak di-revert ke integer -- data desimal yg sudah kesimpan bakal ke-truncate.
    }
};
