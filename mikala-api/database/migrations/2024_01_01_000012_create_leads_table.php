<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            
            // Contact info
            $table->string('nama');
            $table->string('email')->nullable();
            $table->string('phone');
            $table->string('kota')->nullable();
            
            // Source
            $table->enum('source', [
                'website_mgm',
                'website_mga',
                'whatsapp',
                'instagram',
                'facebook',
                'referral',
                'agen',
                'lainnya'
            ])->default('website_mgm');
            
            // Interest
            $table->enum('tipe_layanan', [
                'homecare',
                'medical_checkup',
                'training',
                'partnership',
                'lainnya'
            ])->nullable();
            
            $table->text('pesan')->nullable();
            
            // Status
            $table->enum('status', [
                'new',
                'contacted',
                'qualified',
                'proposal_sent',
                'negotiation',
                'deal',
                'lost'
            ])->default('new');
            
            $table->text('notes')->nullable();
            
            // Assignment
            $table->foreignId('assigned_to')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('assigned_at')->nullable();
            
            // Conversion
            $table->foreignId('converted_to_klien_id')->nullable()->constrained('klien')->onDelete('set null');
            $table->timestamp('converted_at')->nullable();
            
            $table->timestamps();
            $table->softDeletes();
            
            $table->index(['status', 'assigned_to']);
            $table->index('source');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leads');
    }
};
