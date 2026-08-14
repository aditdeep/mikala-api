<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        if (!Schema::hasTable('leads')) return;
        Schema::table('leads', function (Blueprint $table) {
            // NIK terpisah dari No. Order (nomor) -- dibuat saat lead ditandai Deal
            if (!Schema::hasColumn('leads', 'nik')) $table->string('nik')->nullable()->unique()->after('nomor');

            // Data Cust/PJ tambahan
            if (!Schema::hasColumn('leads', 'no_rumah')) $table->string('no_rumah')->nullable()->after('kontak');
            if (!Schema::hasColumn('leads', 'no_ktp_cust_pj')) $table->string('no_ktp_cust_pj')->nullable()->after('alamat_cust_pj');
            if (!Schema::hasColumn('leads', 'hubungan_dengan_pasien')) $table->string('hubungan_dengan_pasien')->nullable()->after('no_ktp_cust_pj');
            if (!Schema::hasColumn('leads', 'email_cust_pj')) $table->string('email_cust_pj')->nullable()->after('hubungan_dengan_pasien');

            // Data Klien / Pasien tambahan
            if (!Schema::hasColumn('leads', 'tanggal_lahir_klien')) $table->date('tanggal_lahir_klien')->nullable()->after('alamat_klien');
            if (!Schema::hasColumn('leads', 'no_wa_klien')) $table->string('no_wa_klien')->nullable()->after('tanggal_lahir_klien');
            if (!Schema::hasColumn('leads', 'tinggi_badan')) $table->string('tinggi_badan')->nullable()->after('no_wa_klien');
            if (!Schema::hasColumn('leads', 'berat_badan')) $table->string('berat_badan')->nullable()->after('tinggi_badan');
            if (!Schema::hasColumn('leads', 'jenis_kelamin_klien')) $table->string('jenis_kelamin_klien', 10)->nullable()->after('berat_badan');
            if (!Schema::hasColumn('leads', 'alamat_klien_2')) $table->text('alamat_klien_2')->nullable()->after('jenis_kelamin_klien');
            if (!Schema::hasColumn('leads', 'alat_medis')) $table->text('alat_medis')->nullable()->after('alat_pendukung'); // JSON array, sampai 5 slot
            if (!Schema::hasColumn('leads', 'deskripsi_diagnosa')) $table->text('deskripsi_diagnosa')->nullable()->after('diagnosis_awal');

            // Referensi
            if (!Schema::hasColumn('leads', 'referensi_tipe')) $table->string('referensi_tipe')->nullable()->after('sumber');
            if (!Schema::hasColumn('leads', 'referensi_sub')) $table->string('referensi_sub')->nullable()->after('referensi_tipe');
            if (!Schema::hasColumn('leads', 'referensi_klien_id')) $table->unsignedBigInteger('referensi_klien_id')->nullable()->after('referensi_sub');
            if (!Schema::hasColumn('leads', 'referensi_mitra_id')) $table->unsignedBigInteger('referensi_mitra_id')->nullable()->after('referensi_klien_id');
            if (!Schema::hasColumn('leads', 'nama_referensi')) $table->string('nama_referensi')->nullable()->after('referensi_mitra_id');
            if (!Schema::hasColumn('leads', 'kontak_referensi')) $table->string('kontak_referensi')->nullable()->after('nama_referensi');

            // Status: alasan/keterangan multi-slot (disimpan JSON array of string)
            if (!Schema::hasColumn('leads', 'alasan_status')) $table->text('alasan_status')->nullable()->after('alasan_batal');

            // Field tahap Deal: kondisi klinis (bebas teks dulu)
            if (!Schema::hasColumn('leads', 'kesadaran')) $table->string('kesadaran')->nullable()->after('alat_medis');
            if (!Schema::hasColumn('leads', 'komunikasi')) $table->string('komunikasi')->nullable()->after('kesadaran');
            if (!Schema::hasColumn('leads', 'kelemahan')) $table->string('kelemahan')->nullable()->after('komunikasi');
            if (!Schema::hasColumn('leads', 'mobilisasi')) $table->string('mobilisasi')->nullable()->after('kelemahan');

            // Negosiasi jasa
            if (!Schema::hasColumn('leads', 'jasa_diminta')) $table->string('jasa_diminta')->nullable()->after('mobilisasi');
            if (!Schema::hasColumn('leads', 'jasa_disarankan')) $table->string('jasa_disarankan')->nullable()->after('jasa_diminta');
            if (!Schema::hasColumn('leads', 'jasa_disetujui')) $table->string('jasa_disetujui')->nullable()->after('jasa_disarankan');
            if (!Schema::hasColumn('leads', 'pembantu')) $table->string('pembantu')->nullable()->after('jasa_disetujui');
            if (!Schema::hasColumn('leads', 'cara_mencuci_baju')) $table->string('cara_mencuci_baju')->nullable()->after('pembantu');

            // Data Mitra finansial (per Deal)
            if (!Schema::hasColumn('leads', 'mitra_nim')) $table->string('mitra_nim')->nullable()->after('mitra_id');
            if (!Schema::hasColumn('leads', 'biaya_admin')) $table->decimal('biaya_admin', 14, 2)->nullable()->after('mitra_nim');
            if (!Schema::hasColumn('leads', 'honor_mitra')) $table->decimal('honor_mitra', 14, 2)->nullable()->after('biaya_admin');
            if (!Schema::hasColumn('leads', 'uang_cuti_mitra')) $table->decimal('uang_cuti_mitra', 14, 2)->nullable()->after('honor_mitra');
        });

        // Status: tambah Gantung (3) -- kolom status sudah smallint, tidak perlu ubah tipe, cukup dokumentasi di model
    }

    public function down(): void {
        if (!Schema::hasTable('leads')) return;
        Schema::table('leads', function (Blueprint $table) {
            foreach ([
                'nik','no_rumah','no_ktp_cust_pj','hubungan_dengan_pasien','email_cust_pj',
                'tanggal_lahir_klien','no_wa_klien','tinggi_badan','berat_badan','jenis_kelamin_klien',
                'alamat_klien_2','alat_medis','deskripsi_diagnosa',
                'referensi_tipe','referensi_sub','referensi_klien_id','referensi_mitra_id','nama_referensi','kontak_referensi',
                'alasan_status','kesadaran','komunikasi','kelemahan','mobilisasi',
                'jasa_diminta','jasa_disarankan','jasa_disetujui','pembantu','cara_mencuci_baju',
                'mitra_nim','biaya_admin','honor_mitra','uang_cuti_mitra',
            ] as $col) {
                if (Schema::hasColumn('leads', $col)) $table->dropColumn($col);
            }
        });
    }
};
