<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void {
        $cols = array_column(DB::select("SELECT column_name FROM information_schema.columns WHERE table_name='mitra'"), 'column_name');
        if (!in_array('sumber_tipe', $cols))
            DB::statement("ALTER TABLE mitra ADD COLUMN sumber_tipe VARCHAR(20) DEFAULT 'sendiri'");
        if (!in_array('sumber_detail', $cols))
            DB::statement("ALTER TABLE mitra ADD COLUMN sumber_detail VARCHAR(100)");
        if (!in_array('lembaga_id', $cols))
            DB::statement("ALTER TABLE mitra ADD COLUMN lembaga_id BIGINT NULL");
        if (!in_array('referrer_mitra_id', $cols))
            DB::statement("ALTER TABLE mitra ADD COLUMN referrer_mitra_id BIGINT NULL");
    }
    public function down(): void {}
};
