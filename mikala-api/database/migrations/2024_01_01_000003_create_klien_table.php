<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('klien', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            
            // Personal/Company info
            $table->string('nama_lengkap');
            $table->enum('tipe', ['individu', 'keluarga', 'rumah_sakit', 'panti_jompo', 'klinik'])->default('individu');
            $table->string('nik')->nullable(); // For individu
            $table->string('nama_perusahaan')->nullable(); // For institusi
            $table->string('npwp')->nullable();
            
            // Contact
            $table->text('alamat');
            $table->string('kota');
            $table->string('provinsi');
            $table->string('phone_secondary')->nullable();
            
            // Billing info
            $table->enum('billing_method', ['cash', 'transfer', 'corporate'])->default('cash');
            $table->string('bank_name')->nullable();
            $table->string('bank_account')->nullable();
            $table->string('bank_account_name')->nullable();
            
            // Status
            $table->enum('status', ['active', 'inactive', 'suspended'])->default('active');
            $table->boolean('is_verified')->default(false);
            
            // Stats
            $table->integer('total_pasien')->default(0);
            $table->integer('total_orders')->default(0);
            $table->decimal('total_tagihan', 15, 2)->default(0);
            
            $table->timestamps();
            $table->softDeletes();
            
            $table->index(['tipe', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('klien');
    }
};
