<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mitra', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            
            // Personal info
            $table->string('nik')->unique();
            $table->string('nama_lengkap');
            $table->date('tanggal_lahir');
            $table->enum('jenis_kelamin', ['L', 'P']);
            $table->text('alamat');
            $table->string('kota');
            $table->string('provinsi');
            
            // Professional info
            $table->string('pendidikan_terakhir');
            $table->string('sertifikasi')->nullable();
            $table->text('pengalaman')->nullable();
            
            // Documents
            $table->string('ktp_file')->nullable();
            $table->string('sertifikat_file')->nullable();
            $table->string('cv_file')->nullable();
            
            // Status
            $table->enum('status', ['available', 'on_job', 'training', 're_training', 'inactive'])->default('training');
            $table->boolean('is_verified')->default(false);
            
            // Training status
            $table->enum('training_status', ['pending', 'in_progress', 'completed', 'failed'])->default('pending');
            $table->integer('training_score')->nullable();
            $table->date('training_completed_at')->nullable();
            
            // Rating
            $table->decimal('rating', 3, 2)->default(0.00);
            $table->integer('total_reviews')->default(0);
            $table->integer('total_jobs')->default(0);
            
            $table->timestamps();
            $table->softDeletes();
            
            $table->index(['status', 'is_verified']);
            $table->index('training_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mitra');
    }
};
