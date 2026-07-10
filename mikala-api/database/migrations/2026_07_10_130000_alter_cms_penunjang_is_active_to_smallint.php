<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void {
        if (!Schema::hasTable('cms_penunjang')) return;
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE cms_penunjang ALTER COLUMN is_active DROP DEFAULT');
            DB::statement('ALTER TABLE cms_penunjang ALTER COLUMN is_active TYPE smallint USING (is_active::int)');
            DB::statement('ALTER TABLE cms_penunjang ALTER COLUMN is_active SET DEFAULT 1');
        }
    }
    public function down(): void {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE cms_penunjang ALTER COLUMN is_active DROP DEFAULT');
            DB::statement('ALTER TABLE cms_penunjang ALTER COLUMN is_active TYPE boolean USING (is_active::boolean)');
            DB::statement('ALTER TABLE cms_penunjang ALTER COLUMN is_active SET DEFAULT true');
        }
    }
};
