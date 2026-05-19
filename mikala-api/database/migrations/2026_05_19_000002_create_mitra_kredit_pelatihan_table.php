<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        if (Schema::hasTable('mitra_kredit_pelatihan')) return;
        Schema::create('mitra_kredit_pelatihan', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('mitra_id');
            $table->decimal('total_biaya', 12, 2)->default(0);
            $table->decimal('total_terbayar', 12, 2)->default(0);
            $table->decimal('sisa_tagihan', 12, 2)->default(0);
            $table->decimal('cicilan_per_job', 12, 2)->default(0);
            $table->enum('status', ['active','lunas','ditangguhkan'])->default('active');
            $table->text('keterangan')->nullable();
            $table->timestamp('lunas_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->foreign('mitra_id')->references('id')->on('mitra')->cascadeOnDelete();
        });
    }
    public function down(): void { Schema::dropIfExists('mitra_kredit_pelatihan'); }
};
