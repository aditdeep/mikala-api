<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        if (Schema::hasTable('cms_penunjang')) return;
        Schema::create('cms_penunjang', function (Blueprint $table) {
            $table->id();
            $table->string('nama');
            $table->string('tipe')->nullable();
            $table->text('deskripsi')->nullable();
            $table->string('gambar')->nullable();
            $table->integer('urutan')->default(0);
            $table->string('wa_link')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }
    public function down(): void {
        Schema::dropIfExists('cms_penunjang');
    }
};
