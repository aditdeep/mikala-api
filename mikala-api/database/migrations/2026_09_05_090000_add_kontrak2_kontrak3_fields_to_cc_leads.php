<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Field nomor kontrak utk Kontrak 2 (Perjanjian Penempatan MGM-Mitra) dan Kontrak 3
 * (Perjanjian Kerja Mitra-Klien), sesuai dokumen "Kontrak 2 ... fix acc Yani.docx" dan
 * "Kontrak 3 ... fix acc yani.docx". Melanjutkan pola nomor_kontrak_klien (Kontrak 1.1/1.2).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cc_leads', function (Blueprint $table) {
            if (!Schema::hasColumn('cc_leads', 'nomor_kontrak_mitra'))
                $table->string('nomor_kontrak_mitra')->nullable()->after('nomor_kontrak_klien');
            if (!Schema::hasColumn('cc_leads', 'nomor_kontrak_klien_mitra'))
                $table->string('nomor_kontrak_klien_mitra')->nullable()->after('nomor_kontrak_mitra');
        });
    }

    public function down(): void
    {
        Schema::table('cc_leads', function (Blueprint $table) {
            foreach (['nomor_kontrak_mitra', 'nomor_kontrak_klien_mitra'] as $col) {
                if (Schema::hasColumn('cc_leads', $col)) $table->dropColumn($col);
            }
        });
    }
};
