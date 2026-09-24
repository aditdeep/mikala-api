<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Field baru "Agama" pada Data Klien (Pasien) di form Leads/Customer Care -- dropdown:
 * Islam, Katholik, Kristen, Hindu, Budha, Konghucu, Lainnya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cc_leads', function (Blueprint $table) {
            if (!Schema::hasColumn('cc_leads', 'agama_klien')) {
                $table->string('agama_klien')->nullable()->after('jenis_kelamin_klien');
            }
        });
    }

    public function down(): void
    {
        Schema::table('cc_leads', function (Blueprint $table) {
            if (Schema::hasColumn('cc_leads', 'agama_klien')) $table->dropColumn('agama_klien');
        });
    }
};
