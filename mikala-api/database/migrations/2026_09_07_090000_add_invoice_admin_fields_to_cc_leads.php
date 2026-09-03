<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Field invoice Biaya Admin utk step "Financial" (4th Step di customer care flow sistem.pdf):
 * pop up penagihan setelah leads Deal -> klik "Tagih Biaya Admin" -> auto-download invoice PDF
 * + trigger notif ke apk klien.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cc_leads', function (Blueprint $table) {
            if (!Schema::hasColumn('cc_leads', 'invoice_admin_nomor'))
                $table->string('invoice_admin_nomor')->nullable()->after('nomor_kontrak_klien_mitra');
            if (!Schema::hasColumn('cc_leads', 'invoice_admin_ditagih_at'))
                $table->timestamp('invoice_admin_ditagih_at')->nullable()->after('invoice_admin_nomor');
        });
    }

    public function down(): void
    {
        Schema::table('cc_leads', function (Blueprint $table) {
            foreach (['invoice_admin_nomor', 'invoice_admin_ditagih_at'] as $col) {
                if (Schema::hasColumn('cc_leads', $col)) $table->dropColumn($col);
            }
        });
    }
};
