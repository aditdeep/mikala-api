<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Rekening lembaga utk transfer fee per mitra
return new class extends Migration {
    public function up(): void
    {
        foreach (['rekening_bank', 'rekening_nomor', 'rekening_atas_nama'] as $kol) {
            DB::statement("ALTER TABLE lembaga ADD COLUMN IF NOT EXISTS {$kol} VARCHAR(100) NULL");
        }
    }

    public function down(): void
    {
        foreach (['rekening_bank', 'rekening_nomor', 'rekening_atas_nama'] as $kol) {
            DB::statement("ALTER TABLE lembaga DROP COLUMN IF EXISTS {$kol}");
        }
    }
};
