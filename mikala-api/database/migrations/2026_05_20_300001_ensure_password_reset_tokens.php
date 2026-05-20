<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void {
        // Pastikan tabel ada (Laravel default)
        $tables = array_column(DB::select("SELECT tablename FROM pg_tables WHERE schemaname='public'"), 'tablename');
        if (!in_array('password_reset_tokens', $tables)) {
            DB::statement("CREATE TABLE password_reset_tokens (
                email VARCHAR(255) NOT NULL,
                token VARCHAR(255) NOT NULL,
                created_at TIMESTAMP DEFAULT NOW(),
                PRIMARY KEY (email)
            )");
        }
    }
    public function down(): void {}
};
