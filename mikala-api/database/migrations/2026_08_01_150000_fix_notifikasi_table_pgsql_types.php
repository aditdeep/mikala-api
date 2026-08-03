<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void {
        if (!Schema::hasTable('notifikasi')) return;
        if (DB::getDriverName() !== 'pgsql') return;

        // Boolean -> smallint (Postgres PDO driver di server ini mengirim boolean
        // sebagai integer literal, menyebabkan "datatype mismatch" saat insert).
        foreach (['is_read', 'is_sent_push', 'is_sent_email'] as $col) {
            if (!Schema::hasColumn('notifikasi', $col)) continue;
            DB::statement("ALTER TABLE notifikasi ALTER COLUMN {$col} DROP DEFAULT");
            DB::statement("ALTER TABLE notifikasi ALTER COLUMN {$col} TYPE smallint USING ({$col}::int)");
            DB::statement("ALTER TABLE notifikasi ALTER COLUMN {$col} SET DEFAULT 0");
        }

        // Lepas CHECK constraint enum kolom 'type'. Banyak bagian aplikasi mengirim
        // kategori notifikasi (welcome, payroll, cuti, order_created, order_assigned,
        // order_status_changed, sertifikat, dll) di luar daftar enum awal
        // (info/warning/success/billing/order/training/feedback/system), sehingga
        // insert selalu gagal untuk kategori-kategori tersebut.
        DB::statement(<<<SQL
            DO \$\$
            DECLARE
                con record;
            BEGIN
                FOR con IN
                    SELECT conname FROM pg_constraint
                    WHERE conrelid = 'notifikasi'::regclass
                      AND contype = 'c'
                      AND pg_get_constraintdef(oid) ILIKE '%type%'
                LOOP
                    EXECUTE format('ALTER TABLE notifikasi DROP CONSTRAINT %I', con.conname);
                END LOOP;
            END \$\$;
        SQL);

        DB::statement("ALTER TABLE notifikasi ALTER COLUMN type TYPE varchar(50)");
        DB::statement("ALTER TABLE notifikasi ALTER COLUMN type SET DEFAULT 'info'");
    }

    public function down(): void {
        if (DB::getDriverName() !== 'pgsql') return;
        foreach (['is_read', 'is_sent_push', 'is_sent_email'] as $col) {
            DB::statement("ALTER TABLE notifikasi ALTER COLUMN {$col} DROP DEFAULT");
            DB::statement("ALTER TABLE notifikasi ALTER COLUMN {$col} TYPE boolean USING ({$col}::boolean)");
            DB::statement("ALTER TABLE notifikasi ALTER COLUMN {$col} SET DEFAULT false");
        }
    }
};
