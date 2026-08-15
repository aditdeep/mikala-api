<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        if (!Schema::hasTable('cc_leads')) return;
        Schema::table('cc_leads', function (Blueprint $table) {
            if (!Schema::hasColumn('cc_leads', 'alamat_klien')) $table->text('alamat_klien')->nullable()->after('nama_pasien');
            if (!Schema::hasColumn('cc_leads', 'alamat_cust_pj')) $table->text('alamat_cust_pj')->nullable()->after('nama_leads');
            if (!Schema::hasColumn('cc_leads', 'diagnosis_awal')) $table->text('diagnosis_awal')->nullable()->after('alamat_klien');
            if (!Schema::hasColumn('cc_leads', 'alat_pendukung')) $table->text('alat_pendukung')->nullable()->after('diagnosis_awal');
        });
    }
    public function down(): void {
        if (!Schema::hasTable('cc_leads')) return;
        Schema::table('cc_leads', function (Blueprint $table) {
            foreach (['alamat_klien','alamat_cust_pj','diagnosis_awal','alat_pendukung'] as $col) {
                if (Schema::hasColumn('cc_leads', $col)) $table->dropColumn($col);
            }
        });
    }
};
