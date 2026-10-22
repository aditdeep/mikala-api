<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Tahun lulus pendidikan terakhir & tahun pelatihan/pendidikan non-formal (form & CV mitra). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mitra', function (Blueprint $table) {
            if (!Schema::hasColumn('mitra', 'tahun_lulus')) $table->string('tahun_lulus', 10)->nullable();
            if (!Schema::hasColumn('mitra', 'tahun_pelatihan')) $table->string('tahun_pelatihan', 30)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('mitra', function (Blueprint $table) {
            $table->dropColumn(['tahun_lulus', 'tahun_pelatihan']);
        });
    }
};
