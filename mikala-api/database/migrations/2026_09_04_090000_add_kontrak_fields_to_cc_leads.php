<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Field tambahan utk generate PDF Kontrak 1.1 (MGM-Klien bulanan) / 1.2 (harian) di step Deal,
 * sesuai dokumen "customer care flow sistem.pdf" + kontrak fix acc Yani (revisi terbaru).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cc_leads', function (Blueprint $table) {
            if (!Schema::hasColumn('cc_leads', 'biaya_transport'))
                $table->decimal('biaya_transport', 12, 2)->nullable()->default(0)->after('uang_cuti_mitra');
            if (!Schema::hasColumn('cc_leads', 'nomor_kontrak_klien'))
                $table->string('nomor_kontrak_klien')->nullable()->after('nomor');
            if (!Schema::hasColumn('cc_leads', 'catatan_revisi_kontrak'))
                $table->text('catatan_revisi_kontrak')->nullable()->after('catatan');
        });
    }

    public function down(): void
    {
        Schema::table('cc_leads', function (Blueprint $table) {
            foreach (['biaya_transport', 'nomor_kontrak_klien', 'catatan_revisi_kontrak'] as $col) {
                if (Schema::hasColumn('cc_leads', $col)) $table->dropColumn($col);
            }
        });
    }
};
