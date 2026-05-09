<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifikasi', function (Blueprint $table) {
            $table->id();
            
            // Recipient (polymorphic or user_id)
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            
            // Content
            $table->string('title');
            $table->text('message');
            $table->enum('type', [
                'info',
                'warning',
                'success',
                'billing',
                'order',
                'training',
                'feedback',
                'system'
            ])->default('info');
            
            // Related entity (optional)
            $table->string('related_type')->nullable(); // Order, Tagihan, Mitra, etc
            $table->unsignedBigInteger('related_id')->nullable();
            
            // Delivery
            $table->boolean('is_read')->default(false);
            $table->boolean('is_sent_push')->default(false);
            $table->boolean('is_sent_email')->default(false);
            
            // Action URL (optional deep link)
            $table->string('action_url')->nullable();
            
            $table->timestamp('read_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            
            $table->timestamps();
            
            $table->index(['user_id', 'is_read']);
            $table->index(['related_type', 'related_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifikasi');
    }
};
