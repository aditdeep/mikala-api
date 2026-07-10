<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('cms_artikel', function (Blueprint $table) {
            $table->timestamp('published_at')->nullable()->after('status');
        });
        \DB::statement("UPDATE cms_artikel SET published_at = created_at WHERE status = 'published' AND published_at IS NULL");
    }
    public function down(): void {
        Schema::table('cms_artikel', fn(Blueprint $t) => $t->dropColumn('published_at'));
    }
};
