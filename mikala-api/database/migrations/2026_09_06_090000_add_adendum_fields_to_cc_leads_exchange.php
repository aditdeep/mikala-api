<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Field tambahan utk generate PDF Adendum - Exchange, sesuai dokumen "Adendum - Exchange.docx"
 * (tabel "Mitra yang Ditugaskan: Sebelum / Sesudah" -- biaya jasa, uang cuti, surat tugas,
 * masing-masing snapshot sebelum & sesudah exchange) + biaya transport penggantian mitra.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cc_leads_exchange', function (Blueprint $table) {
            if (!Schema::hasColumn('cc_leads_exchange', 'nomor_adendum'))
                $table->string('nomor_adendum')->nullable()->after('nomor');
            if (!Schema::hasColumn('cc_leads_exchange', 'biaya_jasa_lama'))
                $table->decimal('biaya_jasa_lama', 12, 2)->nullable()->after('alasan');
            if (!Schema::hasColumn('cc_leads_exchange', 'biaya_jasa_baru'))
                $table->decimal('biaya_jasa_baru', 12, 2)->nullable()->after('biaya_jasa_lama');
            if (!Schema::hasColumn('cc_leads_exchange', 'uang_cuti_lama'))
                $table->decimal('uang_cuti_lama', 12, 2)->nullable()->after('biaya_jasa_baru');
            if (!Schema::hasColumn('cc_leads_exchange', 'uang_cuti_baru'))
                $table->decimal('uang_cuti_baru', 12, 2)->nullable()->after('uang_cuti_lama');
            if (!Schema::hasColumn('cc_leads_exchange', 'surat_tugas_lama'))
                $table->string('surat_tugas_lama')->nullable()->after('uang_cuti_baru');
            if (!Schema::hasColumn('cc_leads_exchange', 'surat_tugas_baru'))
                $table->string('surat_tugas_baru')->nullable()->after('surat_tugas_lama');
            if (!Schema::hasColumn('cc_leads_exchange', 'biaya_transport'))
                $table->decimal('biaya_transport', 12, 2)->nullable()->default(0)->after('surat_tugas_baru');
        });
    }

    public function down(): void
    {
        Schema::table('cc_leads_exchange', function (Blueprint $table) {
            foreach (['nomor_adendum','biaya_jasa_lama','biaya_jasa_baru','uang_cuti_lama','uang_cuti_baru','surat_tugas_lama','surat_tugas_baru','biaya_transport'] as $col) {
                if (Schema::hasColumn('cc_leads_exchange', $col)) $table->dropColumn($col);
            }
        });
    }
};
