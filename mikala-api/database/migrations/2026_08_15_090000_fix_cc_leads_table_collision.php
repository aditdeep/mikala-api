<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * FIX: tabel CC Leads pipeline sempat dinamai 'leads', bentrok dengan tabel
 * marketing-leads lama yang sudah ada di produksi (lihat 2024_01_01_000012_create_leads_table.php,
 * App\Models\Leads / MarketingController). Akibatnya Schema::hasTable('leads') di migration
 * sebelumnya selalu true, jadi tabel CC Leads (nomor, status, mitra_id, dst) TIDAK PERNAH
 * benar-benar terbuat -- hanya menambahkan kolom nullable "menumpang" ke tabel leads lama.
 *
 * Migration ini:
 * 1. Membuat tabel 'cc_leads' & 'cc_leads_exchange' dari nol dengan skema lengkap final
 *    (guard hasTable supaya no-op di environment yang sudah punya cc_leads dari migration
 *    yang sudah diperbaiki, mis. instalasi baru).
 * 2. Membersihkan kolom-kolom CC yang telanjur "menumpang" di tabel 'leads' (marketing) lama.
 */
return new class extends Migration {
    public function up(): void {
        if (!Schema::hasTable('cc_leads')) {
            Schema::create('cc_leads', function (Blueprint $table) {
                $table->id();
                $table->string('nomor')->nullable()->unique(); // No. Order: T-LN.MGM.01.00001
                $table->string('nik')->nullable()->unique();   // NIK, dibuat saat Deal: V1.DD.MM.YY-001
                $table->unsignedBigInteger('cms_layanan_id')->nullable();
                $table->string('tier_nama')->nullable();
                $table->unsignedBigInteger('klien_id')->nullable();

                // Cust/PJ (penanggung jawab)
                $table->string('nama_leads')->nullable();
                $table->string('kontak')->nullable();
                $table->string('no_rumah')->nullable();
                $table->text('alamat_cust_pj')->nullable();
                $table->string('no_ktp_cust_pj')->nullable();
                $table->string('hubungan_dengan_pasien')->nullable();
                $table->string('email_cust_pj')->nullable();

                // Klien / Pasien
                $table->string('nama_pasien')->nullable();
                $table->text('alamat_klien')->nullable();
                $table->text('alamat_klien_2')->nullable();
                $table->date('tanggal_lahir_klien')->nullable();
                $table->string('no_wa_klien')->nullable();
                $table->string('tinggi_badan')->nullable();
                $table->string('berat_badan')->nullable();
                $table->string('jenis_kelamin_klien', 10)->nullable();
                $table->text('diagnosis_awal')->nullable();
                $table->text('deskripsi_diagnosa')->nullable();
                $table->text('alat_pendukung')->nullable();
                $table->text('alat_medis')->nullable(); // JSON array, sampai 5 slot

                // Referensi
                $table->string('sumber')->nullable();
                $table->string('referensi_tipe')->nullable();
                $table->string('referensi_sub')->nullable();
                $table->unsignedBigInteger('referensi_klien_id')->nullable();
                $table->unsignedBigInteger('referensi_mitra_id')->nullable();
                $table->string('nama_referensi')->nullable();
                $table->string('kontak_referensi')->nullable();

                $table->text('catatan')->nullable();

                // Mitra + finansial
                $table->unsignedBigInteger('mitra_id')->nullable();
                $table->string('mitra_nim')->nullable();
                $table->decimal('biaya_admin', 14, 2)->nullable();
                $table->decimal('honor_mitra', 14, 2)->nullable();
                $table->decimal('uang_cuti_mitra', 14, 2)->nullable();

                // Status & alasan
                $table->smallInteger('status')->default(0); // 0=proses,1=deal,2=batal,3=gantung
                $table->text('alasan_batal')->nullable();
                $table->text('alasan_status')->nullable(); // JSON array (mis. alasan Gantung)

                // Kondisi klinis (Deal)
                $table->string('kesadaran')->nullable();
                $table->string('komunikasi')->nullable();
                $table->string('kelemahan')->nullable();
                $table->string('mobilisasi')->nullable();

                // Negosiasi jasa (Deal)
                $table->string('jasa_diminta')->nullable();
                $table->string('jasa_disarankan')->nullable();
                $table->string('jasa_disetujui')->nullable();
                $table->string('pembantu')->nullable();
                $table->string('cara_mencuci_baju')->nullable();

                $table->timestamp('deal_at')->nullable();
                $table->timestamp('batal_at')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->index('cms_layanan_id');
                $table->index('klien_id');
                $table->index('mitra_id');
                $table->index('status');
                $table->index('referensi_klien_id');
                $table->index('referensi_mitra_id');
            });
        }

        if (!Schema::hasTable('cc_leads_exchange')) {
            Schema::create('cc_leads_exchange', function (Blueprint $table) {
                $table->id();
                $table->string('nomor')->nullable()->unique(); // NIM: V2.CG.03.26-001
                $table->unsignedBigInteger('lead_id'); // references cc_leads.id
                $table->unsignedBigInteger('mitra_lama_id')->nullable();
                $table->unsignedBigInteger('mitra_baru_id')->nullable();
                $table->text('alasan')->nullable();
                $table->timestamp('exchanged_at')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();

                $table->index('lead_id');
                $table->index('mitra_lama_id');
                $table->index('mitra_baru_id');
            });
        }

        // Bersihkan kolom CC yang telanjur "menumpang" di tabel marketing 'leads' lama.
        if (Schema::hasTable('leads')) {
            Schema::table('leads', function (Blueprint $table) {
                foreach ([
                    'alamat_klien', 'alamat_cust_pj', 'diagnosis_awal', 'alat_pendukung',
                    'nik', 'no_rumah', 'no_ktp_cust_pj', 'hubungan_dengan_pasien', 'email_cust_pj',
                    'tanggal_lahir_klien', 'no_wa_klien', 'tinggi_badan', 'berat_badan', 'jenis_kelamin_klien',
                    'alamat_klien_2', 'alat_medis', 'deskripsi_diagnosa',
                    'referensi_tipe', 'referensi_sub', 'referensi_klien_id', 'referensi_mitra_id',
                    'nama_referensi', 'kontak_referensi',
                    'alasan_status', 'kesadaran', 'komunikasi', 'kelemahan', 'mobilisasi',
                    'jasa_diminta', 'jasa_disarankan', 'jasa_disetujui', 'pembantu', 'cara_mencuci_baju',
                    'mitra_nim', 'biaya_admin', 'honor_mitra', 'uang_cuti_mitra',
                ] as $col) {
                    if (Schema::hasColumn('leads', $col)) $table->dropColumn($col);
                }
            });
        }
    }

    public function down(): void {
        Schema::dropIfExists('cc_leads_exchange');
        Schema::dropIfExists('cc_leads');
        // Kolom yang dibersihkan dari 'leads' (marketing) sengaja tidak dikembalikan --
        // kolom itu memang tidak seharusnya ada di tabel marketing leads.
    }
};
