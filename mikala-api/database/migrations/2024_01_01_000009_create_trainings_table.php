<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trainings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mitra_id')->constrained('mitra')->onDelete('cascade');
            
            // Training info
            $table->enum('tipe', ['initial', 're_training', 'upgrade'])->default('initial');
            $table->string('program_name');
            $table->text('deskripsi')->nullable();
            
            // Schedule
            $table->date('tanggal_mulai');
            $table->date('tanggal_selesai')->nullable();
            $table->integer('durasi_hari')->default(7);
            
            // Pricing (optional, if training has cost)
            $table->decimal('biaya', 15, 2)->default(0);
            
            // Progress tracking (JSON checklist)
            $table->json('checklist')->nullable(); // e.g. [{item: "Teori Keperawatan", done: true}, ...]
            $table->integer('checklist_completed')->default(0);
            $table->integer('checklist_total')->default(0);
            
            // Result
            $table->enum('status', [
                'pending',
                'in_progress',
                'completed',
                'failed',
                'cancelled'
            ])->default('pending');
            
            $table->integer('score')->nullable();
            $table->text('feedback')->nullable();
            $table->string('sertifikat_file')->nullable();
            
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('trainer_id')->nullable()->constrained('users')->onDelete('set null');
            
            $table->timestamps();
            $table->softDeletes();
            
            $table->index(['mitra_id', 'status']);
            $table->index('tipe');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trainings');
    }
};
