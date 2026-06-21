<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Tambah kolom jabatan & gaji_bulanan ke mitra
        Schema::table('mitra', function (Blueprint $table) {
            if (!Schema::hasColumn('mitra', 'jabatan')) {
                $table->string('jabatan')->nullable()->after('price_rate');
            }
            if (!Schema::hasColumn('mitra', 'gaji_bulanan')) {
                $table->decimal('gaji_bulanan', 12, 2)->nullable()->after('jabatan');
            }
        });

        // 2. Tabel config gaji per jabatan (editable admin, nambah jabatan bebas)
        if (!Schema::hasTable('gaji_jabatan_config')) {
            Schema::create('gaji_jabatan_config', function (Blueprint $table) {
                $table->id();
                $table->string('jabatan')->unique();          // caregiver, perawat, dst
                $table->string('label');                       // "Caregiver", "Perawat Senior"
                $table->decimal('gaji_default', 12, 2)->default(0);  // gaji bulanan default
                $table->decimal('rate_harian_default', 12, 2)->default(0); // tarif harian default
                $table->boolean('aktif')->default(true);
                $table->integer('urutan')->default(0);
                $table->timestamps();
            });
        }

        // 3. Seed data awal (angka bisa diubah admin nanti)
        $now = now();
        $seed = [
            ['jabatan' => 'caregiver',      'label' => 'Caregiver',       'gaji_default' => 3000000, 'rate_harian_default' => 150000, 'urutan' => 1],
            ['jabatan' => 'perawat',        'label' => 'Perawat',         'gaji_default' => 5000000, 'rate_harian_default' => 250000, 'urutan' => 2],
            ['jabatan' => 'perawat_senior', 'label' => 'Perawat Senior',  'gaji_default' => 7000000, 'rate_harian_default' => 350000, 'urutan' => 3],
            ['jabatan' => 'babysitter',     'label' => 'Babysitter',      'gaji_default' => 3000000, 'rate_harian_default' => 150000, 'urutan' => 4],
        ];
        foreach ($seed as $row) {
            $exists = DB::table('gaji_jabatan_config')->where('jabatan', $row['jabatan'])->exists();
            if (!$exists) {
                DB::table('gaji_jabatan_config')->insert(array_merge($row, [
                    'aktif' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]));
            }
        }
    }

    public function down(): void
    {
        Schema::table('mitra', function (Blueprint $table) {
            if (Schema::hasColumn('mitra', 'jabatan'))      $table->dropColumn('jabatan');
            if (Schema::hasColumn('mitra', 'gaji_bulanan')) $table->dropColumn('gaji_bulanan');
        });
        Schema::dropIfExists('gaji_jabatan_config');
    }
};