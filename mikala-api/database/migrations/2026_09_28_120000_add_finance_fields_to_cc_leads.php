<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bagian dari sinkronisasi Menu Finance dgn data real Klien/Mitra (cc_leads), bukan lagi
 * tabel Order lama yg sudah gak kepake:
 * - rekom_fee: fee referral yg diisi manual pas Tandai Deal (sesuai Excel "Rekom Fee"),
 *   auto ke-log ke fee_log kalau ada referensi_mitra_id/referensi_klien_id.
 * - rekom_fee_fee_log_id: guard biar rekom fee gak ke-log dobel tiap kali Deal disave ulang.
 * - tagihan_admin_id: link ke tabel tagihan yg beneran (bukan cuma nomor invoice nempel di
 *   Lead), supaya Tagih Biaya Admin nongol & bisa di-track Lunas/Belum di Menu Finance.
 * - refund_amount/refund_at/refund_catatan: flow Refund pas Lead Stop/Batal (sesuai Excel
 *   kolom "Refaund"), belum ada sama sekali sebelumnya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cc_leads', function (Blueprint $table) {
            if (!Schema::hasColumn('cc_leads', 'rekom_fee')) {
                $table->decimal('rekom_fee', 15, 2)->nullable()->after('management_fee');
            }
            if (!Schema::hasColumn('cc_leads', 'rekom_fee_fee_log_id')) {
                $table->unsignedBigInteger('rekom_fee_fee_log_id')->nullable()->after('rekom_fee');
            }
            if (!Schema::hasColumn('cc_leads', 'tagihan_admin_id')) {
                $table->unsignedBigInteger('tagihan_admin_id')->nullable()->after('invoice_admin_ditagih_at');
            }
            if (!Schema::hasColumn('cc_leads', 'refund_amount')) {
                $table->decimal('refund_amount', 15, 2)->nullable()->after('tagihan_admin_id');
            }
            if (!Schema::hasColumn('cc_leads', 'refund_at')) {
                $table->timestamp('refund_at')->nullable()->after('refund_amount');
            }
            if (!Schema::hasColumn('cc_leads', 'refund_catatan')) {
                $table->text('refund_catatan')->nullable()->after('refund_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('cc_leads', function (Blueprint $table) {
            foreach (['rekom_fee','rekom_fee_fee_log_id','tagihan_admin_id','refund_amount','refund_at','refund_catatan'] as $col) {
                if (Schema::hasColumn('cc_leads', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
