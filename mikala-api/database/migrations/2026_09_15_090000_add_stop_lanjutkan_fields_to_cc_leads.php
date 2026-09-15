<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Field pendukung fitur STOP (kontrak selesai pakai jasa) & Lanjutkan (order baru,
     * nomor+data sama) pada tab Deal/Exchange, serta TAMBAH (duplikat lead utk order baru,
     * data klien sama, nomor order baru) pada modal Log Exchange.
     */
    public function up(): void
    {
        Schema::table('cc_leads', function (Blueprint $table) {
            if (!Schema::hasColumn('cc_leads', 'stop_at')) {
                $table->timestamp('stop_at')->nullable()->after('deal_at');
            }
            if (!Schema::hasColumn('cc_leads', 'lead_asal_id')) {
                $table->unsignedBigInteger('lead_asal_id')->nullable()->after('klien_id');
                $table->index('lead_asal_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('cc_leads', function (Blueprint $table) {
            if (Schema::hasColumn('cc_leads', 'stop_at')) $table->dropColumn('stop_at');
            if (Schema::hasColumn('cc_leads', 'lead_asal_id')) $table->dropColumn('lead_asal_id');
        });
    }
};
