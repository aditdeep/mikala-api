<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Field invoice Biaya Transport utk Exchange Step ("pop up biaya transport, invoice transport"
 * di customer care flow sistem.pdf), mirip pola invoice_admin_nomor di cc_leads.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cc_leads_exchange', function (Blueprint $table) {
            if (!Schema::hasColumn('cc_leads_exchange', 'invoice_transport_nomor'))
                $table->string('invoice_transport_nomor')->nullable()->after('biaya_transport');
            if (!Schema::hasColumn('cc_leads_exchange', 'invoice_transport_ditagih_at'))
                $table->timestamp('invoice_transport_ditagih_at')->nullable()->after('invoice_transport_nomor');
        });
    }

    public function down(): void
    {
        Schema::table('cc_leads_exchange', function (Blueprint $table) {
            foreach (['invoice_transport_nomor', 'invoice_transport_ditagih_at'] as $col) {
                if (Schema::hasColumn('cc_leads_exchange', $col)) $table->dropColumn($col);
            }
        });
    }
};
