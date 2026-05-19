<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        if (Schema::hasTable('mitra_jadwal_interview')) return;
        Schema::create('mitra_jadwal_interview', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('mitra_id');
            $table->dateTime('jadwal_at');
            $table->string('lokasi')->nullable();
            $table->string('link_online')->nullable();
            $table->enum('tipe', ['offline','online'])->default('offline');
            $table->enum('status', ['scheduled','done','cancelled','rescheduled'])->default('scheduled');
            $table->text('catatan')->nullable();
            $table->unsignedBigInteger('interviewer_id')->nullable();
            $table->timestamp('done_at')->nullable();
            $table->timestamps();
            $table->foreign('mitra_id')->references('id')->on('mitra')->cascadeOnDelete();
        });
    }
    public function down(): void { Schema::dropIfExists('mitra_jadwal_interview'); }
};
