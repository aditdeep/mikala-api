<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agen', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            
            // Institution info
            $table->string('nama_institusi');
            $table->string('tipe_institusi'); // RS, Panti, Klinik, dll
            $table->string('npwp')->nullable();
            
            // Contact person
            $table->string('contact_person_name');
            $table->string('contact_person_jabatan');
            $table->string('contact_person_phone');
            $table->string('contact_person_email');
            
            // Address
            $table->text('alamat');
            $table->string('kota');
            $table->string('provinsi');
            
            // Commission
            $table->decimal('komisi_persen', 5, 2)->default(0); // e.g. 10.50%
            $table->text('notes')->nullable();
            
            // Status
            $table->enum('status', ['active', 'inactive', 'suspended'])->default('active');
            
            // Stats
            $table->integer('total_referrals')->default(0);
            $table->decimal('total_komisi', 15, 2)->default(0);
            
            $table->timestamps();
            $table->softDeletes();
            
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agen');
    }
};
