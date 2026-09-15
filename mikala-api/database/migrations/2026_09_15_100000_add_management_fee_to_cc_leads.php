<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Field Manajemen Fee (Tandai Deal) -- terpisah dari Honor Mitra (Gaji), tapi di
     * Kontrak 1 Pasal IX & Invoice Biaya Admin digabung jadi satu baris "Gaji + Management Fee".
     */
    public function up(): void
    {
        Schema::table('cc_leads', function (Blueprint $table) {
            if (!Schema::hasColumn('cc_leads', 'management_fee')) {
                $table->decimal('management_fee', 14, 2)->nullable()->after('honor_mitra');
            }
        });
    }

    public function down(): void
    {
        Schema::table('cc_leads', function (Blueprint $table) {
            if (Schema::hasColumn('cc_leads', 'management_fee')) $table->dropColumn('management_fee');
        });
    }
};
