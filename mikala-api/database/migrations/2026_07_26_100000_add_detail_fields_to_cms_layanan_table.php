<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        if (!Schema::hasTable('cms_layanan')) return;
        Schema::table('cms_layanan', function (Blueprint $table) {
            if (!Schema::hasColumn('cms_layanan', 'deskripsi_panjang')) {
                $table->text('deskripsi_panjang')->nullable();
            }
            if (!Schema::hasColumn('cms_layanan', 'manfaat')) {
                $table->text('manfaat')->nullable();
            }
            if (!Schema::hasColumn('cms_layanan', 'icon')) {
                $table->string('icon')->nullable();
            }
            if (!Schema::hasColumn('cms_layanan', 'meta_title')) {
                $table->string('meta_title')->nullable();
            }
            if (!Schema::hasColumn('cms_layanan', 'meta_description')) {
                $table->string('meta_description')->nullable();
            }
        });
    }

    public function down(): void {
        if (!Schema::hasTable('cms_layanan')) return;
        Schema::table('cms_layanan', function (Blueprint $table) {
            foreach (['deskripsi_panjang', 'manfaat', 'icon', 'meta_title', 'meta_description'] as $col) {
                if (Schema::hasColumn('cms_layanan', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
