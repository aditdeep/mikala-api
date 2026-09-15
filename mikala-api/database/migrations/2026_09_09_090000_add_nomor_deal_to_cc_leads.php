<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "E-Ticket Order Deal" (format T-LD.MGM.02.xxxxx), sesuai dokumen Tabel 2 - Leads.xlsx
 * sheet "Deal" (form pemesanan mitra), dibuat sekali saat leads ditandai Deal.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cc_leads', function (Blueprint $table) {
            if (!Schema::hasColumn('cc_leads', 'nomor_deal'))
                $table->string('nomor_deal')->nullable()->after('nik');
        });
    }

    public function down(): void
    {
        Schema::table('cc_leads', function (Blueprint $table) {
            if (Schema::hasColumn('cc_leads', 'nomor_deal')) $table->dropColumn('nomor_deal');
        });
    }
};
