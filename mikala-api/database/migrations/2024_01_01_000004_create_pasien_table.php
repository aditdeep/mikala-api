<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pasien', function (Blueprint $table) {
            $table->id();
            $table->foreignId('klien_id')->constrained('klien')->onDelete('cascade');
            
            // Personal info
            $table->string('nama_lengkap');
            $table->string('nik')->unique()->nullable();
            $table->date('tanggal_lahir');
            $table->enum('jenis_kelamin', ['L', 'P']);
            $table->text('alamat');
            
            // Medical info
            $table->text('riwayat_penyakit')->nullable();
            $table->text('alergi')->nullable();
            $table->string('golongan_darah')->nullable();
            $table->text('obat_rutin')->nullable();
            $table->text('catatan_khusus')->nullable();
            
            // Emergency contact
            $table->string('kontak_darurat_nama')->nullable();
            $table->string('kontak_darurat_phone')->nullable();
            $table->string('kontak_darurat_relasi')->nullable();
            
            // Status
            $table->enum('status', ['active', 'inactive'])->default('active');
            
            $table->timestamps();
            $table->softDeletes();
            
            $table->index('klien_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pasien');
    }
};
