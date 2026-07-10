<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('mitra', function (Blueprint $table) {
            $table->string('nomor_induk')->nullable()->after('nik');
            $table->json('data_tambahan')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mitra', function (Blueprint $table) {
            $table->dropColumn(['nomor_induk', 'data_tambahan']);
        });
    }
};
