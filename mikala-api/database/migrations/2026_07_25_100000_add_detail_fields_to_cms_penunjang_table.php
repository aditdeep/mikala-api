<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        if (!Schema::hasTable('cms_penunjang')) return;
        Schema::table('cms_penunjang', function (Blueprint $table) {
            if (!Schema::hasColumn('cms_penunjang', 'deskripsi_panjang')) {
                $table->text('deskripsi_panjang')->nullable();
            }
            if (!Schema::hasColumn('cms_penunjang', 'manfaat')) {
                $table->text('manfaat')->nullable();
            }
            if (!Schema::hasColumn('cms_penunjang', 'meta_title')) {
                $table->string('meta_title')->nullable();
            }
            if (!Schema::hasColumn('cms_penunjang', 'meta_description')) {
                $table->string('meta_description')->nullable();
            }
        });
    }

    public function down(): void {
        if (!Schema::hasTable('cms_penunjang')) return;
        Schema::table('cms_penunjang', function (Blueprint $table) {
            foreach (['deskripsi_panjang', 'manfaat', 'meta_title', 'meta_description'] as $col) {
                if (Schema::hasColumn('cms_penunjang', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
