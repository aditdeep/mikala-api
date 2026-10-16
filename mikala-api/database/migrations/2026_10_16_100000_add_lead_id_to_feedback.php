<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rating dari apps Klien sekarang per Deal (cc_leads), bukan per Order lama -> order_id boleh
 * kosong, tambah lead_id.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('feedback', function (Blueprint $table) {
            $table->unsignedBigInteger('order_id')->nullable()->change();
            if (!Schema::hasColumn('feedback', 'lead_id')) {
                $table->foreignId('lead_id')->nullable()->after('order_id')->constrained('cc_leads')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('feedback', function (Blueprint $table) {
            $table->dropConstrainedForeignId('lead_id');
        });
    }
};
