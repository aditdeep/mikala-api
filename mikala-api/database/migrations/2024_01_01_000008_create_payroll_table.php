<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll', function (Blueprint $table) {
            $table->id();
            $table->string('payroll_number')->unique();
            
            // Relations
            $table->foreignId('mitra_id')->constrained('mitra')->onDelete('cascade');
            $table->foreignId('order_id')->nullable()->constrained('orders')->onDelete('set null');
            
            // Period
            $table->date('periode_mulai');
            $table->date('periode_selesai');
            $table->integer('jumlah_hari_kerja');
            
            // Calculation
            $table->decimal('tarif_per_hari', 15, 2);
            $table->decimal('gaji_pokok', 15, 2);
            $table->decimal('bonus', 15, 2)->default(0);
            $table->decimal('potongan', 15, 2)->default(0);
            $table->decimal('transport', 15, 2)->default(0);
            $table->decimal('total', 15, 2);
            
            // Payment
            $table->enum('status', [
                'pending',
                'approved',
                'paid',
                'rejected'
            ])->default('pending');
            
            $table->enum('metode_pembayaran', ['transfer', 'cash', 'e-wallet'])->nullable();
            $table->string('bank_name')->nullable();
            $table->string('bank_account')->nullable();
            $table->string('bukti_transfer')->nullable();
            
            $table->text('catatan')->nullable();
            
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('paid_at')->nullable();
            
            $table->timestamps();
            $table->softDeletes();
            
            $table->index(['mitra_id', 'status']);
            $table->index(['periode_mulai', 'periode_selesai']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll');
    }
};
