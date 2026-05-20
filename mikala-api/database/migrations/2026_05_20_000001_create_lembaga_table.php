<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void {
        DB::statement("CREATE TABLE IF NOT EXISTS lembaga (
            id BIGSERIAL PRIMARY KEY,
            nama VARCHAR(255) NOT NULL,
            tipe VARCHAR(50) DEFAULT 'lpk',
            kontak_nama VARCHAR(255),
            kontak_hp VARCHAR(50),
            kontak_email VARCHAR(255),
            alamat TEXT,
            kota VARCHAR(100),
            provinsi VARCHAR(100),
            fee_per_mitra DECIMAL(12,2) DEFAULT 0,
            status VARCHAR(20) DEFAULT 'aktif',
            catatan TEXT,
            created_by BIGINT,
            created_at TIMESTAMP DEFAULT NOW(),
            updated_at TIMESTAMP DEFAULT NOW()
        )");

        DB::statement("CREATE TABLE IF NOT EXISTS mitra_referral (
            id BIGSERIAL PRIMARY KEY,
            mitra_id BIGINT NOT NULL,
            sumber_tipe VARCHAR(20) NOT NULL,
            sumber_detail VARCHAR(100),
            lembaga_id BIGINT,
            referrer_mitra_id BIGINT,
            lead_source VARCHAR(100),
            fee_amount DECIMAL(12,2) DEFAULT 0,
            fee_status VARCHAR(20) DEFAULT 'pending',
            fee_paid_at TIMESTAMP,
            fee_paid_by BIGINT,
            catatan TEXT,
            created_at TIMESTAMP DEFAULT NOW(),
            updated_at TIMESTAMP DEFAULT NOW()
        )");

        DB::statement("CREATE TABLE IF NOT EXISTS fee_log (
            id BIGSERIAL PRIMARY KEY,
            referral_id BIGINT NOT NULL,
            penerima_tipe VARCHAR(20) NOT NULL,
            penerima_id BIGINT NOT NULL,
            jumlah DECIMAL(12,2) NOT NULL,
            status VARCHAR(20) DEFAULT 'pending',
            keterangan TEXT,
            paid_at TIMESTAMP,
            paid_by BIGINT,
            created_at TIMESTAMP DEFAULT NOW(),
            updated_at TIMESTAMP DEFAULT NOW()
        )");
    }
    public function down(): void {
        DB::statement("DROP TABLE IF EXISTS fee_log");
        DB::statement("DROP TABLE IF EXISTS mitra_referral");
        DB::statement("DROP TABLE IF EXISTS lembaga");
    }
};
