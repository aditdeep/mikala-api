<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jurnal_keuangan', function (Blueprint $table) {
            $table->id();
            $table->string('kode_transaksi')->unique();
            
            // Transaction details
            $table->date('tanggal');
            $table->enum('tipe', ['debit', 'kredit']);
            $table->enum('kategori', [
                'pendapatan_layanan',
                'pendapatan_training',
                'pendapatan_lainnya',
                'biaya_payroll',
                'biaya_operasional',
                'biaya_training',
                'biaya_marketing',
                'biaya_lainnya',
                'piutang',
                'utang',
                'aset',
                'modal'
            ]);
            
            $table->decimal('jumlah', 15, 2);
            $table->text('deskripsi');
            
            // Related entities (optional)
            $table->string('related_type')->nullable(); // Tagihan, Payroll, Order, etc
            $table->unsignedBigInteger('related_id')->nullable();
            
            // Approval
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            $table->foreignId('approved_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('approved_at')->nullable();
            
            // Attachment
            $table->string('bukti_file')->nullable();
            
            $table->timestamps();
            $table->softDeletes();
            
            $table->index(['tanggal', 'tipe']);
            $table->index('kategori');
            $table->index(['related_type', 'related_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jurnal_keuangan');
    }
};
