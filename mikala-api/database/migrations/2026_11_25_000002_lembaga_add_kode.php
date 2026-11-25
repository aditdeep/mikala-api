<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Kode unik lembaga (LMB-0001) supaya lembaga bernama sama tetap bisa dibedakan
return new class extends Migration {
    public function up(): void
    {
        DB::statement("ALTER TABLE lembaga ADD COLUMN IF NOT EXISTS kode VARCHAR(20) NULL");
        DB::statement("UPDATE lembaga SET kode = 'LMB-' || LPAD(id::text, 4, '0') WHERE kode IS NULL");
        DB::statement("CREATE UNIQUE INDEX IF NOT EXISTS lembaga_kode_unique ON lembaga (kode)");
    }

    public function down(): void
    {
        DB::statement("DROP INDEX IF EXISTS lembaga_kode_unique");
        DB::statement("ALTER TABLE lembaga DROP COLUMN IF EXISTS kode");
    }
};
