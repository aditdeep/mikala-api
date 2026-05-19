<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void {
        // Drop constraint lama dan buat yang baru dengan nilai lengkap
        DB::statement("ALTER TABLE mitra DROP CONSTRAINT IF EXISTS mitra_status_check");
        DB::statement("ALTER TABLE mitra ADD CONSTRAINT mitra_status_check CHECK (status IN (
            'pending', 'training', 're_training', 'available', 'on_job',
            'inactive', 'keluar', 'cuti', 'suspend'
        ))");

        // Fix kolom status_rekrutmen kalau belum ada
        $cols = DB::select("SELECT column_name FROM information_schema.columns WHERE table_name='mitra' AND column_name='status_rekrutmen'");
        if (empty($cols)) {
            DB::statement("ALTER TABLE mitra ADD COLUMN status_rekrutmen VARCHAR(20) DEFAULT 'pending'");
        }

        // Fix kolom payment_type kalau belum ada
        $cols2 = DB::select("SELECT column_name FROM information_schema.columns WHERE table_name='mitra' AND column_name='payment_type'");
        if (empty($cols2)) {
            DB::statement("ALTER TABLE mitra ADD COLUMN payment_type VARCHAR(10) DEFAULT 'cash'");
        }

        // Fix kolom price_rate
        $cols3 = DB::select("SELECT column_name FROM information_schema.columns WHERE table_name='mitra' AND column_name='price_rate'");
        if (empty($cols3)) {
            DB::statement("ALTER TABLE mitra ADD COLUMN price_rate DECIMAL(10,2) NULL");
        }

        // Fix kolom verified_at
        $cols4 = DB::select("SELECT column_name FROM information_schema.columns WHERE table_name='mitra' AND column_name='verified_at'");
        if (empty($cols4)) {
            DB::statement("ALTER TABLE mitra ADD COLUMN verified_at TIMESTAMP NULL");
        }

        // Fix kolom catatan_rekrutmen
        $cols5 = DB::select("SELECT column_name FROM information_schema.columns WHERE table_name='mitra' AND column_name='catatan_rekrutmen'");
        if (empty($cols5)) {
            DB::statement("ALTER TABLE mitra ADD COLUMN catatan_rekrutmen TEXT NULL");
        }
    }

    public function down(): void {
        DB::statement("ALTER TABLE mitra DROP CONSTRAINT IF EXISTS mitra_status_check");
        DB::statement("ALTER TABLE mitra ADD CONSTRAINT mitra_status_check CHECK (status IN (
            'pending', 'training', 're_training', 'available', 'on_job', 'inactive'
        ))");
    }
};
