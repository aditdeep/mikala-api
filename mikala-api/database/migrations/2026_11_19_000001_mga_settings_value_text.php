<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// mga_settings dibuat manual di server (tanpa migration). Konten halaman Tentang Kami MGA
// (paragraf, daftar tim/nilai) disimpan di sini -> pastikan kolom value TEXT, bukan varchar(255).
return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('mga_settings') && DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE mga_settings ALTER COLUMN value TYPE text');
        }
    }

    public function down(): void {}
};
