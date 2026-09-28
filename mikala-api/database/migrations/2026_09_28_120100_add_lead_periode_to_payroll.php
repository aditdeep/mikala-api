<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Payroll sebelumnya cuma bisa nempel ke Order (tabel lama yg udah gak kepake sejak sistem
 * pindah ke CC Leads/Deal) -- makanya generatePayroll() gak pernah hasilin apa2 di praktiknya.
 * lead_id: link payroll ke cc_leads (Deal yg beneran aktif).
 * periode_label: '15' atau '30', karena gajian jalan 2x/bulan (sesuai pola Excel Laporan
 * Keuangan -- kolom Gaji Tgl 15 / Gaji Tgl 30), bukan cuma 1x/bulan kayak sebelumnya.
 * order_id dibiarkan ada (nullable) buat kompatibilitas data lama, tapi generatePayroll()
 * yg baru gak bakal ngisi kolom itu lagi.
 *
 * KETEMU BUG TAMBAHAN sambil investigasi: FinanceController@generatePayroll/adjustPayroll
 * udah lama nulis kolom hari_cuti, rate_cuti, uang_cuti, potongan_kasbon, potongan_kredit,
 * adjustment, catatan_adjustment -- tapi kolom2 itu TIDAK PERNAH ADA di migration manapun
 * (create_payroll_table cuma punya gaji_pokok/bonus/potongan/transport/total) DAN juga
 * tidak ada di Payroll::$fillable. Jadi selama ini data itu diam2 kebuang (mass-assignment
 * whitelist Laravel), potongan kasbon & kredit gak pernah beneran ke-apply ke payroll.
 * Ditambahkan sekaligus di sini biar konsisten.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payroll', function (Blueprint $table) {
            if (!Schema::hasColumn('payroll', 'lead_id')) {
                $table->unsignedBigInteger('lead_id')->nullable()->after('order_id');
            }
            if (!Schema::hasColumn('payroll', 'periode_label')) {
                $table->string('periode_label', 2)->nullable()->after('periode_selesai');
            }
            if (!Schema::hasColumn('payroll', 'hari_cuti')) {
                $table->integer('hari_cuti')->default(0)->after('gaji_pokok');
            }
            if (!Schema::hasColumn('payroll', 'rate_cuti')) {
                $table->decimal('rate_cuti', 15, 2)->default(0)->after('hari_cuti');
            }
            if (!Schema::hasColumn('payroll', 'uang_cuti')) {
                $table->decimal('uang_cuti', 15, 2)->default(0)->after('rate_cuti');
            }
            if (!Schema::hasColumn('payroll', 'potongan_kasbon')) {
                $table->decimal('potongan_kasbon', 15, 2)->default(0)->after('potongan');
            }
            if (!Schema::hasColumn('payroll', 'potongan_kredit')) {
                $table->decimal('potongan_kredit', 15, 2)->default(0)->after('potongan_kasbon');
            }
            if (!Schema::hasColumn('payroll', 'adjustment')) {
                $table->decimal('adjustment', 15, 2)->default(0)->after('potongan_kredit');
            }
            if (!Schema::hasColumn('payroll', 'catatan_adjustment')) {
                $table->string('catatan_adjustment', 500)->nullable()->after('adjustment');
            }
        });

        // status enum lama cuma punya pending/approved/paid/rejected -- generatePayroll() sekarang
        // set status 'draft' dulu sebelum di-approve. Postgres enum via string check constraint
        // Laravel biasanya pakai string column biasa utk enum kalau driver bukan mysql, tapi utk
        // amannya kita convert ke varchar biar 'draft' & status apapun kedepan gak ke-reject DB.
        if (Schema::hasColumn('payroll', 'status')) {
            $col = DB::selectOne("
                SELECT data_type FROM information_schema.columns
                WHERE table_name = 'payroll' AND column_name = 'status'
            ");
            if ($col && $col->data_type === 'character varying') {
                // sudah varchar, aman
            } elseif ($col) {
                try {
                    DB::statement("ALTER TABLE payroll ALTER COLUMN status TYPE varchar(20) USING status::text");
                } catch (\Exception $e) {
                    // biarkan, kalau gagal berarti sudah kompatibel atau driver beda
                }
            }
        }
    }

    public function down(): void
    {
        Schema::table('payroll', function (Blueprint $table) {
            foreach ([
                'lead_id','periode_label','hari_cuti','rate_cuti','uang_cuti',
                'potongan_kasbon','potongan_kredit','adjustment','catatan_adjustment',
            ] as $col) {
                if (Schema::hasColumn('payroll', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
