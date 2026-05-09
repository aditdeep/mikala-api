<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number')->unique();
            
            // Relations
            $table->foreignId('klien_id')->constrained('klien')->onDelete('cascade');
            $table->foreignId('pasien_id')->constrained('pasien')->onDelete('cascade');
            $table->foreignId('mitra_id')->nullable()->constrained('mitra')->onDelete('set null');
            $table->foreignId('agen_id')->nullable()->constrained('agen')->onDelete('set null');
            
            // Service details
            $table->enum('tipe_layanan', [
                'homecare_harian',
                'homecare_live_in', 
                'medical_checkup',
                'konsultasi',
                'fisioterapi',
                'perawatan_luka',
                'vaksinasi',
                'lainnya'
            ]);
            $table->text('deskripsi_layanan')->nullable();
            
            // Schedule
            $table->date('tanggal_mulai');
            $table->date('tanggal_selesai')->nullable();
            $table->integer('durasi_hari')->default(1);
            $table->time('jam_mulai')->nullable();
            $table->time('jam_selesai')->nullable();
            
            // Pricing
            $table->decimal('harga_per_hari', 15, 2);
            $table->decimal('subtotal', 15, 2);
            $table->decimal('pajak', 15, 2)->default(0);
            $table->decimal('diskon', 15, 2)->default(0);
            $table->decimal('total', 15, 2);
            
            // Status
            $table->enum('status', [
                'pending',           // Waiting assignment
                'confirmed',         // Mitra assigned
                'in_progress',       // Service running
                'completed',         // Service done
                'cancelled',         // Cancelled
                'on_hold'           // Temporarily paused
            ])->default('pending');
            
            $table->text('catatan')->nullable();
            $table->text('cancel_reason')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            
            $table->timestamps();
            $table->softDeletes();
            
            $table->index(['klien_id', 'status']);
            $table->index(['mitra_id', 'status']);
            $table->index('tanggal_mulai');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
