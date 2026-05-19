<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        if (Schema::hasTable('mitra_kredit_potongan')) return;
        Schema::create('mitra_kredit_potongan', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('kredit_id');
            $table->unsignedBigInteger('mitra_id');
            $table->unsignedBigInteger('order_id');
            $table->decimal('jumlah_potongan', 12, 2);
            $table->decimal('sisa_setelah_potong', 12, 2);
            $table->text('keterangan')->nullable();
            $table->timestamps();
            $table->foreign('kredit_id')->references('id')->on('mitra_kredit_pelatihan')->cascadeOnDelete();
            $table->foreign('mitra_id')->references('id')->on('mitra')->cascadeOnDelete();
            $table->foreign('order_id')->references('id')->on('orders')->cascadeOnDelete();
        });
    }
    public function down(): void { Schema::dropIfExists('mitra_kredit_potongan'); }
};
