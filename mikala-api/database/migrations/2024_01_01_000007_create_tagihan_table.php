<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tagihan', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_number')->unique();
            
            // Relations
            $table->foreignId('klien_id')->constrained('klien')->onDelete('cascade');
            $table->foreignId('order_id')->nullable()->constrained('orders')->onDelete('set null');
            
            // Billing details
            $table->date('tanggal_invoice');
            $table->date('tanggal_jatuh_tempo');
            
            $table->decimal('subtotal', 15, 2);
            $table->decimal('pajak', 15, 2)->default(0);
            $table->decimal('diskon', 15, 2)->default(0);
            $table->decimal('total', 15, 2);
            $table->decimal('jumlah_bayar', 15, 2)->default(0);
            $table->decimal('sisa', 15, 2);
            
            // Payment info
            $table->enum('status', [
                'unpaid',
                'partial',
                'paid',
                'overdue',
                'cancelled'
            ])->default('unpaid');
            
            $table->enum('metode_pembayaran', ['cash', 'transfer', 'card', 'corporate'])->nullable();
            $table->string('bukti_transfer')->nullable();
            $table->text('catatan')->nullable();
            
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('overdue_notified_at')->nullable();
            
            $table->timestamps();
            $table->softDeletes();
            
            $table->index(['klien_id', 'status']);
            $table->index('tanggal_jatuh_tempo');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tagihan');
    }
};
