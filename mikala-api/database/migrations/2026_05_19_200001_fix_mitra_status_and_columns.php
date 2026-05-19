<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void {
        // Fix status constraint
        DB::statement("ALTER TABLE mitra DROP CONSTRAINT IF EXISTS mitra_status_check");
        DB::statement("ALTER TABLE mitra ADD CONSTRAINT mitra_status_check CHECK (status IN (
            'pending','training','re_training','available','on_job','inactive','keluar','cuti','suspend'
        ))");

        // Tambah kolom kalau belum ada
        $cols = array_column(DB::select("SELECT column_name FROM information_schema.columns WHERE table_name='mitra'"), 'column_name');
        if (!in_array('status_rekrutmen',  $cols)) DB::statement("ALTER TABLE mitra ADD COLUMN status_rekrutmen VARCHAR(20) DEFAULT 'pending'");
        if (!in_array('payment_type',      $cols)) DB::statement("ALTER TABLE mitra ADD COLUMN payment_type VARCHAR(10) DEFAULT 'cash'");
        if (!in_array('price_rate',        $cols)) DB::statement("ALTER TABLE mitra ADD COLUMN price_rate DECIMAL(10,2) NULL");
        if (!in_array('catatan_rekrutmen', $cols)) DB::statement("ALTER TABLE mitra ADD COLUMN catatan_rekrutmen TEXT NULL");
        if (!in_array('verified_at',       $cols)) DB::statement("ALTER TABLE mitra ADD COLUMN verified_at TIMESTAMP NULL");
        if (!in_array('verified_by',       $cols)) DB::statement("ALTER TABLE mitra ADD COLUMN verified_by BIGINT NULL");
        if (!in_array('contract_agreed_at',$cols)) DB::statement("ALTER TABLE mitra ADD COLUMN contract_agreed_at TIMESTAMP NULL");

        // Tabel kredit pelatihan
        DB::statement("CREATE TABLE IF NOT EXISTS mitra_kredit_pelatihan (
            id BIGSERIAL PRIMARY KEY, mitra_id BIGINT NOT NULL,
            total_biaya DECIMAL(12,2) DEFAULT 0, total_terbayar DECIMAL(12,2) DEFAULT 0,
            sisa_tagihan DECIMAL(12,2) DEFAULT 0, cicilan_per_job DECIMAL(12,2) DEFAULT 0,
            status VARCHAR(20) DEFAULT 'active', keterangan TEXT, lunas_at TIMESTAMP,
            created_by BIGINT, created_at TIMESTAMP DEFAULT NOW(), updated_at TIMESTAMP DEFAULT NOW()
        )");

        // Tabel jadwal interview
        DB::statement("CREATE TABLE IF NOT EXISTS mitra_jadwal_interview (
            id BIGSERIAL PRIMARY KEY, mitra_id BIGINT NOT NULL,
            jadwal_at TIMESTAMP NOT NULL, lokasi VARCHAR(255), link_online VARCHAR(255),
            tipe VARCHAR(10) DEFAULT 'offline', status VARCHAR(20) DEFAULT 'scheduled',
            catatan TEXT, interviewer_id BIGINT, done_at TIMESTAMP,
            created_at TIMESTAMP DEFAULT NOW(), updated_at TIMESTAMP DEFAULT NOW()
        )");

        // Tabel kredit potongan
        DB::statement("CREATE TABLE IF NOT EXISTS mitra_kredit_potongan (
            id BIGSERIAL PRIMARY KEY, kredit_id BIGINT NOT NULL,
            mitra_id BIGINT NOT NULL, order_id BIGINT NOT NULL,
            jumlah_potongan DECIMAL(12,2) NOT NULL, sisa_setelah_potong DECIMAL(12,2) NOT NULL,
            keterangan TEXT, created_at TIMESTAMP DEFAULT NOW(), updated_at TIMESTAMP DEFAULT NOW()
        )");
    }
    public function down(): void {
        DB::statement("ALTER TABLE mitra DROP CONSTRAINT IF EXISTS mitra_status_check");
        DB::statement("ALTER TABLE mitra ADD CONSTRAINT mitra_status_check CHECK (status IN ('pending','training','re_training','available','on_job','inactive'))");
    }
};
