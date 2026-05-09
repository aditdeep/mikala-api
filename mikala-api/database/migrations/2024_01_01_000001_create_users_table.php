<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('phone')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            
            // Multi-role: manajemen, customer_care, training_center, rekrutmen, finance, marketing, mitra, klien, agen
            $table->enum('role', [
                'manajemen', 
                'customer_care', 
                'training_center', 
                'rekrutmen', 
                'finance', 
                'marketing', 
                'mitra', 
                'klien', 
                'agen'
            ]);
            
            // Profile reference (polymorphic-like approach)
            $table->string('profile_type')->nullable(); // Mitra, Klien, Agen
            $table->unsignedBigInteger('profile_id')->nullable();
            
            $table->enum('status', ['active', 'inactive', 'suspended'])->default('active');
            $table->string('fcm_token')->nullable(); // For push notifications
            
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();
            
            $table->index(['email', 'role']);
            $table->index(['profile_type', 'profile_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
