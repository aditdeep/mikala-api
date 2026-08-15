<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        if (Schema::hasTable('cc_leads_exchange')) return;
        Schema::create('cc_leads_exchange', function (Blueprint $table) {
            $table->id();
            $table->string('nomor')->nullable()->unique(); // kode NIM: V2.CG.03.26-001
            $table->unsignedBigInteger('lead_id'); // references cc_leads.id
            $table->unsignedBigInteger('mitra_lama_id')->nullable();
            $table->unsignedBigInteger('mitra_baru_id')->nullable();
            $table->text('alasan')->nullable();
            $table->timestamp('exchanged_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->index('lead_id');
            $table->index('mitra_lama_id');
            $table->index('mitra_baru_id');
        });
    }
    public function down(): void {
        Schema::dropIfExists('cc_leads_exchange');
    }
};
