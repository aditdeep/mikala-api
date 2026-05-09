<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('feedback', function (Blueprint $table) {
            $table->id();
            
            // Relations
            $table->foreignId('order_id')->constrained('orders')->onDelete('cascade');
            $table->foreignId('klien_id')->constrained('klien')->onDelete('cascade');
            $table->foreignId('mitra_id')->constrained('mitra')->onDelete('cascade');
            
            // Rating (1-5 stars)
            $table->integer('rating_kualitas')->default(5); // Service quality
            $table->integer('rating_profesionalisme')->default(5);
            $table->integer('rating_komunikasi')->default(5);
            $table->decimal('rating_average', 3, 2); // Auto-calculated
            
            // Review
            $table->text('komentar')->nullable();
            $table->text('saran')->nullable();
            
            // Status
            $table->boolean('is_published')->default(true);
            $table->boolean('is_featured')->default(false);
            
            // Response (optional, from CS or Management)
            $table->text('response')->nullable();
            $table->foreignId('responded_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('responded_at')->nullable();
            
            $table->timestamps();
            $table->softDeletes();
            
            $table->index(['mitra_id', 'is_published']);
            $table->index('klien_id');
            $table->index('rating_average');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feedback');
    }
};
