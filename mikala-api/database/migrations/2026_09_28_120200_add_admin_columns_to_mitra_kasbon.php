<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel mitra_kasbon dibuat manual di luar migration Laravel (kayak training_checklist),
 * jadi kolomnya gak kelihatan di repo. Mitra sudah bisa ajukan kasbon dari app, tapi belum
 * ada tempat admin approve/reject -- ditambahkan di sini biar kolom yg dibutuhkan pasti ada
 * (approved_by/approved_at/catatan_admin), aman dijalankan berkali2.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('mitra_kasbon')) {
            // Harusnya sudah ada (dipakai routes/api.php utk pengajuan kasbon mitra),
            // tapi jaga2 kalau migration ini jalan duluan di environment baru.
            Schema::create('mitra_kasbon', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('mitra_id');
                $table->decimal('jumlah', 15, 2);
                $table->string('keperluan', 255)->nullable();
                $table->string('status', 20)->default('pending');
                $table->timestamp('paid_at')->nullable();
                $table->timestamps();
            });
        }

        Schema::table('mitra_kasbon', function (Blueprint $table) {
            if (!Schema::hasColumn('mitra_kasbon', 'approved_by')) {
                $table->unsignedBigInteger('approved_by')->nullable()->after('status');
            }
            if (!Schema::hasColumn('mitra_kasbon', 'approved_at')) {
                $table->timestamp('approved_at')->nullable()->after('approved_by');
            }
            if (!Schema::hasColumn('mitra_kasbon', 'catatan_admin')) {
                $table->string('catatan_admin', 500)->nullable()->after('approved_at');
            }
            if (!Schema::hasColumn('mitra_kasbon', 'paid_at')) {
                $table->timestamp('paid_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('mitra_kasbon', function (Blueprint $table) {
            foreach (['approved_by','approved_at','catatan_admin'] as $col) {
                if (Schema::hasColumn('mitra_kasbon', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
