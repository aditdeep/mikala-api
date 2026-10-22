<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Preferensi hijab mitra: Hijab / Tidak Hijab / Flexible (form Edit Mitra & CV). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mitra', function (Blueprint $table) {
            if (!Schema::hasColumn('mitra', 'hijab')) $table->string('hijab', 20)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('mitra', function (Blueprint $table) { $table->dropColumn('hijab'); });
    }
};
