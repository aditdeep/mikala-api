<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        if (!Schema::hasTable('cms_layanan')) return;
        if (Schema::hasColumn('cms_layanan', 'manfaat')) return;
        Schema::table('cms_layanan', function (Blueprint $table) {
            $table->text('manfaat')->nullable();
        });
    }
    public function down(): void {
        if (Schema::hasTable('cms_layanan') && Schema::hasColumn('cms_layanan', 'manfaat')) {
            Schema::table('cms_layanan', function (Blueprint $table) {
                $table->dropColumn('manfaat');
            });
        }
    }
};
