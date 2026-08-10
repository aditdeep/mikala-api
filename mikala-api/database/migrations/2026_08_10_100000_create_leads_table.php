<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        if (Schema::hasTable('leads')) return;
        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->string('nomor')->nullable()->unique(); // kode NIK: V1.01.03.25-001
            $table->unsignedBigInteger('cms_layanan_id')->nullable();
            $table->string('tier_nama')->nullable(); // Junior / Medium / Senior / Terapi A dst
            $table->unsignedBigInteger('klien_id')->nullable(); // Cust/PJ (penanggung jawab) - tabel klien existing
            $table->string('nama_leads')->nullable(); // nama kontak leads (bila belum jadi klien terdaftar)
            $table->string('kontak')->nullable(); // no telp/WA
            $table->string('nama_pasien')->nullable(); // nama pasien (terminologi baru: "Klien" di dokumen)
            $table->string('sumber')->nullable(); // sumber leads: WA, Instagram, Telepon, dst
            $table->text('catatan')->nullable();
            $table->unsignedBigInteger('mitra_id')->nullable(); // mitra yang ditugaskan saat Deal
            $table->smallInteger('status')->default(0); // 0=baru/proses, 1=deal, 2=batal(loss)
            $table->text('alasan_batal')->nullable();
            $table->timestamp('deal_at')->nullable();
            $table->timestamp('batal_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('cms_layanan_id');
            $table->index('klien_id');
            $table->index('mitra_id');
            $table->index('status');
        });
    }
    public function down(): void {
        Schema::dropIfExists('leads');
    }
};
