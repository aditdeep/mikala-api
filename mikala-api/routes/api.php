<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Internal\DashboardController;
use App\Http\Controllers\Internal\RekrutmenController;
use App\Http\Controllers\Internal\TrainingController;
use App\Http\Controllers\Internal\CustomerCareController;
use App\Http\Controllers\Internal\CmsController;
use App\Http\Controllers\UploadController;
use App\Http\Controllers\Internal\FinanceController;
use App\Http\Controllers\Internal\MarketingController;
use App\Http\Controllers\Internal\SettingController;
use App\Http\Controllers\Mitra\MitraProfileController;
use App\Http\Controllers\Mitra\MitraJobController;
use App\Http\Controllers\Mitra\MitraPayrollController;
use App\Http\Controllers\Klien\KlienProfileController;
use App\Http\Controllers\Klien\KlienLayananController;
use App\Http\Controllers\Klien\KlienBillingController;
use App\Http\Controllers\Public\MGMController;
use App\Http\Controllers\Public\MGAController;
use App\Http\Controllers\NotifikasiController;

/*
|--------------------------------------------------------------------------
| API Routes - Mikala Management System
|--------------------------------------------------------------------------
*/

// ============================================================================
// PUBLIC ROUTES (No Authentication)
// ============================================================================

Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('/reset-password', [AuthController::class, 'resetPassword']);
});

// Public Website MGM & MGA
Route::prefix('public/mgm')->group(function () {
    Route::get('/layanan', [MGMController::class, 'layanan']);
    Route::get('/about', [MGMController::class, 'about']);
    Route::post('/leads', [MGMController::class, 'submitLeads']);
});

Route::prefix('public/mga')->group(function () {
    Route::get('/programs', [MGAController::class, 'programPelatihan']);
    Route::post('/register', [MGAController::class, 'daftarPelatihan']);
});

// ============================================================================
// PROTECTED ROUTES (Require Authentication)
// ============================================================================


// TEMPORARY - Seed CMS
Route::get('/seed-cms', function() {
    try {
        \App\Models\CmsArtikel::firstOrCreate(['slug'=>'kurang-aktivitas-fisik-sebabkan-obesitas'],['judul'=>'Kurang Aktivitas Fisik Sebabkan Obesitas','konten'=>'','excerpt'=>'','thumbnail'=>'https://www.mikalaglobalmedika.com/wp-content/uploads/2023/04/IMG-20230712-WA0009-750x536-1.jpg','kategori'=>'Artikel','status'=>'published','created_at'=>'2023-04-24 08:27:01','updated_at'=>'2023-04-24 08:27:01']);
        \App\Models\CmsArtikel::firstOrCreate(['slug'=>'kukuhkan-32-dewan-pengawas-poltekkes-menkes-titip-3-pesan-penting'],['judul'=>'Kukuhkan 32 Dewan Pengawas Poltekkes, Menkes Titip 3 Pesan Penting','konten'=>'','excerpt'=>'','thumbnail'=>'https://www.mikalaglobalmedika.com/wp-content/uploads/2023/04/53045456988_551dfb4dbc_k-2-750x536-1.jpeg','kategori'=>'Artikel','status'=>'published','created_at'=>'2023-04-24 13:04:34','updated_at'=>'2023-04-24 13:04:34']);
        \App\Models\CmsArtikel::firstOrCreate(['slug'=>'hindari-tidur-jelang-magrib-pakar-naturopati-jelaskan-alasannya'],['judul'=>'Hindari Tidur Jelang Magrib, Pakar Naturopati Jelaskan Alasannya','konten'=>'','excerpt'=>'','thumbnail'=>'https://www.mikalaglobalmedika.com/wp-content/uploads/2023/07/838969_16-12-2020_17-32-34.jpeg','kategori'=>'Artikel','status'=>'published','created_at'=>'2023-07-17 23:39:38','updated_at'=>'2023-07-17 23:39:38']);
        \App\Models\CmsArtikel::firstOrCreate(['slug'=>'mikala-global-hadirkan-layanan-perawat-ke-rumah-lebih-personal-dan-nyaman'],['judul'=>'Mikala Global Hadirkan Layanan Perawat ke Rumah, Lebih Personal dan Nyaman','konten'=>'','excerpt'=>'','thumbnail'=>'https://www.mikalaglobalmedika.com/wp-content/uploads/2024/09/img-MGM-artikel-web.png','kategori'=>'Artikel','status'=>'published','created_at'=>'2024-09-25 13:35:07','updated_at'=>'2024-09-25 13:35:07']);
        \App\Models\CmsArtikel::firstOrCreate(['slug'=>'bagaimana-cara-meningkatkan-kesehatan-mental-secara-alami'],['judul'=>'Bagaimana Cara meningkatkan Kesehatan Mental Secara Alami','konten'=>'','excerpt'=>'','thumbnail'=>'https://www.mikalaglobalmedika.com/wp-content/uploads/2024/10/ilsutrasi-kshtn-mental.jpg','kategori'=>'Artikel','status'=>'published','created_at'=>'2024-10-24 19:36:00','updated_at'=>'2024-10-24 19:36:00']);
        \App\Models\CmsArtikel::firstOrCreate(['slug'=>'8-cara-terhindar-dari-influenza-saat-musim-hujan'],['judul'=>'8 Cara Terhindar dari Influenza Saat Musim Hujan','konten'=>'','excerpt'=>'','thumbnail'=>'https://www.mikalaglobalmedika.com/wp-content/uploads/2024/11/puncak-musim-hujan-4169069059.webp','kategori'=>'Artikel','status'=>'published','created_at'=>'2024-11-07 11:08:16','updated_at'=>'2024-11-07 11:08:16']);
        \App\Models\CmsArtikel::firstOrCreate(['slug'=>'stres-gejala-penyebab-dan-pencegahan'],['judul'=>'Stres - Gejala, Penyebab, dan Pencegahan','konten'=>'','excerpt'=>'','thumbnail'=>'https://www.mikalaglobalmedika.com/wp-content/uploads/2024/11/stress_gjl.jpg','kategori'=>'Artikel','status'=>'published','created_at'=>'2024-11-08 12:56:04','updated_at'=>'2024-11-08 12:56:04']);
        \App\Models\CmsArtikel::firstOrCreate(['slug'=>'warga-62-keluhkan-gejala-batuk-pilek-dokter-paru-ungkap-kemungkinan-pemicu'],['judul'=>'Warga +62 Keluhkan Gejala Batuk-Pilek, Dokter Paru Ungkap Kemungkinan Pemicu','konten'=>'','excerpt'=>'','thumbnail'=>'https://www.mikalaglobalmedika.com/wp-content/uploads/2025/10/1c574ef1-efbb-4e97-89a5-6629580c1bdb_jpg.webp','kategori'=>'Artikel','status'=>'published','created_at'=>'2025-10-08 11:47:27','updated_at'=>'2025-10-08 11:47:27']);
        \App\Models\CmsArtikel::firstOrCreate(['slug'=>'indonesia-masih-hadapi-tantangan-hiv-aids-dan-ancaman-triple-storm-virus-musiman'],['judul'=>'Indonesia Masih Hadapi Tantangan HIV-AIDS dan Ancaman “Triple Storm” Virus Musiman','konten'=>'','excerpt'=>'','thumbnail'=>'https://www.mikalaglobalmedika.com/wp-content/uploads/2025/11/Diki_ttg_HIV.png','kategori'=>'Artikel','status'=>'published','created_at'=>'2025-11-10 16:57:40','updated_at'=>'2025-11-10 16:57:40']);
        \App\Models\CmsArtikel::firstOrCreate(['slug'=>'kesadaran-infeksi-menular-seksual-ims-pentingnya-edukasi-dan-pencegahan-sejak-dini'],['judul'=>'Kesadaran Infeksi Menular Seksual (IMS): Pentingnya Edukasi dan Pencegahan Sejak Dini','konten'=>'','excerpt'=>'','thumbnail'=>'https://www.mikalaglobalmedika.com/wp-content/uploads/2026/04/imag-ims.png','kategori'=>'Artikel','status'=>'published','created_at'=>'2026-04-01 17:53:04','updated_at'=>'2026-04-01 17:53:04']);
        \App\Models\CmsArtikel::firstOrCreate(['slug'=>'kenali-kanker-tulang-sejak-dini-solusi-perawatan-terbaik-di-rumah'],['judul'=>'Kenali Kanker Tulang Sejak Dini & Solusi Perawatan Terbaik di Rumah','konten'=>'','excerpt'=>'','thumbnail'=>'https://www.mikalaglobalmedika.com/wp-content/uploads/2026/04/WhatsApp-Image-2026-04-04-at-23.30.50.jpeg','kategori'=>'Artikel','status'=>'published','created_at'=>'2026-04-05 09:14:09','updated_at'=>'2026-04-05 09:14:09']);
        \App\Models\CmsArtikel::firstOrCreate(['slug'=>'perusahaan-sehat-melahirkan-produk-sehat-ini-adalah-kunci-kualitas-dan-kepercayaan-pelanggan'],['judul'=>'Perusahaan Sehat Melahirkan Produk Sehat, Ini Adalah Kunci Kualitas dan Kepercayaan Pelanggan','konten'=>'','excerpt'=>'','thumbnail'=>'https://www.mikalaglobalmedika.com/wp-content/uploads/2026/04/WhatsApp-Image-2026-04-04-at-23.33.59.jpeg','kategori'=>'Artikel','status'=>'published','created_at'=>'2026-04-05 09:45:12','updated_at'=>'2026-04-05 09:45:12']);
        \App\Models\CmsArtikel::firstOrCreate(['slug'=>'hiduplah-setiap-detik-tanpa-rasa-ragu'],['judul'=>'Hiduplah Setiap Detik Tanpa Rasa Ragu!','konten'=>'','excerpt'=>'','thumbnail'=>'https://www.mikalaglobalmedika.com/wp-content/uploads/2026/04/IMG20250718124926_01.jpg-1.jpeg','kategori'=>'Artikel','status'=>'published','created_at'=>'2026-04-05 09:54:05','updated_at'=>'2026-04-05 09:54:05']);
        \App\Models\CmsArtikel::firstOrCreate(['slug'=>'tokoh-kesehatan-ibnu-sina-avicenna-inspirasi-dunia-medis-sepanjang-masa'],['judul'=>'Tokoh Kesehatan: Ibnu Sina (Avicenna), Inspirasi Dunia Medis Sepanjang Masa','konten'=>'','excerpt'=>'','thumbnail'=>'https://www.mikalaglobalmedika.com/wp-content/uploads/2026/04/tokoh-kesehatan.png','kategori'=>'Artikel','status'=>'published','created_at'=>'2026-04-06 00:29:44','updated_at'=>'2026-04-06 00:29:44']);
        \App\Models\CmsArtikel::firstOrCreate(['slug'=>'cara-mengurangi-gejala-ibs-sindrom-iritasi-usus-dengan-pola-hidup-sehat'],['judul'=>'Cara Mengurangi Gejala IBS (Sindrom Iritasi Usus) dengan Pola Hidup Sehat','konten'=>'','excerpt'=>'','thumbnail'=>'https://www.mikalaglobalmedika.com/wp-content/uploads/2026/04/Gejala-IBS.png','kategori'=>'Artikel','status'=>'published','created_at'=>'2026-04-07 07:31:05','updated_at'=>'2026-04-07 07:31:05']);
        \App\Models\CmsArtikel::firstOrCreate(['slug'=>'tips-cegah-kanker-sejak-dini-ala-dr-zaidul-akbar'],['judul'=>'Tips Cegah Kanker Sejak Dini ala dr Zaidul Akbar','konten'=>'','excerpt'=>'','thumbnail'=>'https://www.mikalaglobalmedika.com/wp-content/uploads/2026/04/cegah-kanker.png','kategori'=>'Artikel','status'=>'published','created_at'=>'2026-04-08 07:35:38','updated_at'=>'2026-04-08 07:35:38']);
        \App\Models\CmsArtikel::firstOrCreate(['slug'=>'bekerjasama-dengan-perusahaan-jepang-resmi-di-indonesia-untuk-layanan-yang-lebih-terpercaya'],['judul'=>'Bekerjasama dengan Perusahaan Jepang Resmi di Indonesia untuk Layanan yang Lebih Terpercaya','konten'=>'','excerpt'=>'','thumbnail'=>'https://www.mikalaglobalmedika.com/wp-content/uploads/2026/04/kerjasama-dg-fuji.jpg','kategori'=>'Artikel','status'=>'published','created_at'=>'2026-04-09 12:48:02','updated_at'=>'2026-04-09 12:48:02']);
        \App\Models\CmsArtikel::firstOrCreate(['slug'=>'hal-yang-paling-penting-adalah-menikmati-hidupmu-dan-menjadi-bahagia-apapun-yang-terjadi'],['judul'=>'Hal yang Paling Penting adalah Menikmati Hidupmu dan Menjadi Bahagia Apapun yang Terjadi','konten'=>'','excerpt'=>'','thumbnail'=>'https://www.mikalaglobalmedika.com/wp-content/uploads/2026/04/menikmati-hidup.jpg','kategori'=>'Artikel','status'=>'published','created_at'=>'2026-04-10 07:51:07','updated_at'=>'2026-04-10 07:51:07']);
        \App\Models\CmsArtikel::firstOrCreate(['slug'=>'cara-membuat-pot-dari-sampah-sekitar-untuk-rumah-yang-lebih-hijau'],['judul'=>'Cara Membuat Pot dari Sampah Sekitar untuk Rumah yang Lebih Hijau','konten'=>'','excerpt'=>'','thumbnail'=>'https://www.mikalaglobalmedika.com/wp-content/uploads/2026/04/mambuat-pot_mgm.png','kategori'=>'Artikel','status'=>'published','created_at'=>'2026-04-11 11:46:45','updated_at'=>'2026-04-11 11:46:45']);
        \App\Models\CmsArtikel::firstOrCreate(['slug'=>'energi-positif-atau-negatif-dapat-menular-hoax-atau-fakta'],['judul'=>'Energi Positif atau Negatif Dapat Menular, Hoax atau Fakta?','konten'=>'','excerpt'=>'','thumbnail'=>'https://www.mikalaglobalmedika.com/wp-content/uploads/2026/04/energi-positif_mgm.png','kategori'=>'Artikel','status'=>'published','created_at'=>'2026-04-12 07:02:35','updated_at'=>'2026-04-12 07:02:35']);
        \App\Models\CmsArtikel::firstOrCreate(['slug'=>'bahaya-diabetes-dimulai-sejak-anak-usia-dini-dan-cara-mencegahnya'],['judul'=>'Bahaya Diabetes Dimulai Sejak Anak Usia Dini dan Cara Mencegahnya','konten'=>'','excerpt'=>'','thumbnail'=>'https://www.mikalaglobalmedika.com/wp-content/uploads/2026/04/bahaya-diabet_mgm.png','kategori'=>'Artikel','status'=>'published','created_at'=>'2026-04-13 07:04:16','updated_at'=>'2026-04-13 07:04:16']);
        \App\Models\CmsArtikel::firstOrCreate(['slug'=>'layanan-mikala-global-medika-solusi-lengkap-untuk-perawatan-kesehatan-di-rumah'],['judul'=>'Layanan Mikala Global Medika Solusi Lengkap untuk Perawatan Kesehatan di Rumah','konten'=>'','excerpt'=>'','thumbnail'=>'https://www.mikalaglobalmedika.com/wp-content/uploads/2026/04/layanan-mikala.png','kategori'=>'Artikel','status'=>'published','created_at'=>'2026-04-14 07:08:09','updated_at'=>'2026-04-14 07:08:09']);
        \App\Models\CmsArtikel::firstOrCreate(['slug'=>'semua-impian-kita-bisa-menjadi-kenyataan-jika-kita-memiliki-keberanian-untuk-mengejarnya'],['judul'=>'Semua Impian Kita Bisa Menjadi Kenyataan Jika Kita Memiliki Keberanian untuk Mengejarnya','konten'=>'','excerpt'=>'','thumbnail'=>'https://www.mikalaglobalmedika.com/wp-content/uploads/2026/04/semua-impian_mgm.png','kategori'=>'Artikel','status'=>'published','created_at'=>'2026-04-15 07:10:57','updated_at'=>'2026-04-15 07:10:57']);
        \App\Models\CmsArtikel::firstOrCreate(['slug'=>'tokoh-kesehatan-hippocrates-bapak-kedokteran-yang-mengubah-dunia-medis'],['judul'=>'Tokoh Kesehatan Hippocrates Bapak Kedokteran yang Mengubah Dunia Medis','konten'=>'','excerpt'=>'','thumbnail'=>'https://www.mikalaglobalmedika.com/wp-content/uploads/2026/04/Tokoh-Ruben.png','kategori'=>'Artikel','status'=>'published','created_at'=>'2026-04-18 12:36:49','updated_at'=>'2026-04-18 12:36:49']);
        \App\Models\CmsArtikel::firstOrCreate(['slug'=>'meredakan-dan-membantu-penyembuhan-anemia-secara-bertahap-dan-aman'],['judul'=>'Meredakan dan Membantu Penyembuhan Anemia Secara Bertahap dan Aman','konten'=>'','excerpt'=>'','thumbnail'=>'https://www.mikalaglobalmedika.com/wp-content/uploads/2026/04/meredakan-rev.jpg','kategori'=>'Artikel','status'=>'published','created_at'=>'2026-04-19 12:39:04','updated_at'=>'2026-04-19 12:39:04']);
        \App\Models\CmsArtikel::firstOrCreate(['slug'=>'kisah-semut-yang-bersiap-sebelum-musim-hujan'],['judul'=>'Kisah Semut yang Bersiap Sebelum Musim Hujan','konten'=>'','excerpt'=>'','thumbnail'=>'https://www.mikalaglobalmedika.com/wp-content/uploads/2026/04/Kisah-semut.png','kategori'=>'Artikel','status'=>'published','created_at'=>'2026-04-22 07:08:54','updated_at'=>'2026-04-22 07:08:54']);
        \App\Models\CmsArtikel::firstOrCreate(['slug'=>'perbedaan-perawat-homecare-junior-dan-senior-di-mikala-global-medika'],['judul'=>'Perbedaan Perawat Homecare Junior dan Senior di Mikala Global Medika','konten'=>'','excerpt'=>'','thumbnail'=>'https://www.mikalaglobalmedika.com/wp-content/uploads/2026/04/perbedaan-perawat.jpg','kategori'=>'Artikel','status'=>'published','created_at'=>'2026-04-21 07:40:49','updated_at'=>'2026-04-21 07:40:49']);
        \App\Models\CmsArtikel::firstOrCreate(['slug'=>'diy-mengubah-sampah-plastik-menjadi-bunga-yang-indah'],['judul'=>'DIY Mengubah Sampah Plastik Menjadi Bunga yang Indah','konten'=>'','excerpt'=>'','thumbnail'=>'https://www.mikalaglobalmedika.com/wp-content/uploads/2026/04/DIY-mengubah.jpg','kategori'=>'Artikel','status'=>'published','created_at'=>'2026-04-23 07:45:28','updated_at'=>'2026-04-23 07:45:28']);
        \App\Models\CmsArtikel::firstOrCreate(['slug'=>'jenis-jenis-makanan-yang-dapat-menjadi-imun-booster-bagi-tubuh'],['judul'=>'Jenis-Jenis Makanan yang Dapat Menjadi Imun Booster bagi Tubuh','konten'=>'','excerpt'=>'','thumbnail'=>'https://www.mikalaglobalmedika.com/wp-content/uploads/2026/04/jenis-makanan.png','kategori'=>'Artikel','status'=>'published','created_at'=>'2026-04-25 13:34:08','updated_at'=>'2026-04-25 13:34:08']);
        \App\Models\CmsArtikel::firstOrCreate(['slug'=>'cara-menjaga-imun-tubuh-agar-tetap-stabil-dan-tidak-mudah-turun'],['judul'=>'Cara Menjaga Imun Tubuh Agar Tetap Stabil dan Tidak Mudah Turun','konten'=>'','excerpt'=>'','thumbnail'=>'https://www.mikalaglobalmedika.com/wp-content/uploads/2026/04/menjaga-imun.png','kategori'=>'Artikel','status'=>'published','created_at'=>'2026-04-26 11:07:39','updated_at'=>'2026-04-26 11:07:39']);
        \App\Models\CmsArtikel::firstOrCreate(['slug'=>'perbedaan-caregiver-dan-perawat-homecare-junior-yang-perlu-kamu-pahami'],['judul'=>'Perbedaan Caregiver dan Perawat Homecare Junior yang Perlu Kamu Pahami','konten'=>'','excerpt'=>'','thumbnail'=>'https://www.mikalaglobalmedika.com/wp-content/uploads/2026/04/cg-dan-phc.png','kategori'=>'Artikel','status'=>'published','created_at'=>'2026-04-27 08:14:30','updated_at'=>'2026-04-27 08:14:30']);
        \App\Models\CmsArtikel::firstOrCreate(['slug'=>'waktumu-terbatas-jadi-jangan-hidup-mengikuti-jalan-orang-lain'],['judul'=>'Waktumu Terbatas Jadi Jangan Hidup Mengikuti Jalan Orang Lain','konten'=>'','excerpt'=>'','thumbnail'=>'https://www.mikalaglobalmedika.com/wp-content/uploads/2026/04/waktumu-terbatas.png','kategori'=>'Artikel','status'=>'published','created_at'=>'2026-04-28 08:18:13','updated_at'=>'2026-04-28 08:18:13']);
        \App\Models\CmsArtikel::firstOrCreate(['slug'=>'tokoh-kesehatan-edward-jenner-dan-awal-mula-vaksin-di-dunia'],['judul'=>'Tokoh Kesehatan Edward Jenner dan Awal Mula Vaksin di Dunia','konten'=>'','excerpt'=>'','thumbnail'=>'https://www.mikalaglobalmedika.com/wp-content/uploads/2026/04/tokoh-kesehatan-ed.png','kategori'=>'Artikel','status'=>'published','created_at'=>'2026-04-29 08:20:49','updated_at'=>'2026-04-29 08:20:49']);
        \App\Models\CmsArtikel::firstOrCreate(['slug'=>'kegiatan-yang-bisa-dilakukan-ibu-hamil-sebelum-kelahiran-secara-mandiri'],['judul'=>'Kegiatan yang Bisa Dilakukan Ibu Hamil Sebelum Kelahiran Secara Mandiri','konten'=>'','excerpt'=>'','thumbnail'=>'https://www.mikalaglobalmedika.com/wp-content/uploads/2026/05/kegiatan-bu-hamil.png','kategori'=>'Artikel','status'=>'published','created_at'=>'2026-05-02 18:35:29','updated_at'=>'2026-05-02 18:35:29']);
        \App\Models\CmsArtikel::firstOrCreate(['slug'=>'apa-itu-thalassemia-dan-makanan-yang-disarankan-serta-dibatasi-untuk-penderitanya'],['judul'=>'Apa Itu Thalassemia dan Makanan yang Disarankan serta Dibatasi untuk Penderitanya','konten'=>'','excerpt'=>'','thumbnail'=>'https://www.mikalaglobalmedika.com/wp-content/uploads/2026/05/thalassemia.png','kategori'=>'Artikel','status'=>'published','created_at'=>'2026-05-03 09:39:14','updated_at'=>'2026-05-03 09:39:14']);
        \App\Models\CmsArtikel::firstOrCreate(['slug'=>'perbedaan-perawat-jiwa-dan-caregiver-yang-perlu-dipahami-keluarga'],['judul'=>'Perbedaan Perawat Jiwa dan Caregiver yang Perlu Dipahami Keluarga','konten'=>'','excerpt'=>'','thumbnail'=>'https://www.mikalaglobalmedika.com/wp-content/uploads/2026/05/perbedaan-perawat.png','kategori'=>'Artikel','status'=>'published','created_at'=>'2026-05-04 08:41:57','updated_at'=>'2026-05-04 08:41:57']);
        \App\Models\CmsArtikel::firstOrCreate(['slug'=>'kegiatan-yang-cocok-dilakukan-bersama-lansia-agar-tetap-produktif'],['judul'=>'Kegiatan yang Cocok Dilakukan Bersama Lansia Agar Tetap Produktif','konten'=>'','excerpt'=>'','thumbnail'=>'https://www.mikalaglobalmedika.com/wp-content/uploads/2026/05/kegiatan-lansia.png','kategori'=>'Artikel','status'=>'published','created_at'=>'2026-05-05 08:45:15','updated_at'=>'2026-05-05 08:45:15']);
        \App\Models\CmsArtikel::firstOrCreate(['slug'=>'hidup-bukan-menemukan-diri-tapi-menciptakan-diri-sendiri'],['judul'=>'Hidup Bukan Menemukan Diri, Tapi Menciptakan Diri Sendiri','konten'=>'','excerpt'=>'','thumbnail'=>'https://www.mikalaglobalmedika.com/wp-content/uploads/2026/05/Hidup-bukan-sk.png','kategori'=>'Artikel','status'=>'published','created_at'=>'2026-05-06 08:48:05','updated_at'=>'2026-05-06 08:48:05']);
        \App\Models\CmsArtikel::firstOrCreate(['slug'=>'tips-pola-makan-dan-kegiatan-sehari-hari-untuk-meningkatkan-antibodi-tubuh'],['judul'=>'Tips Pola Makan dan Kegiatan Sehari-hari untuk Meningkatkan Antibodi Tubuh','konten'=>'','excerpt'=>'','thumbnail'=>'https://www.mikalaglobalmedika.com/wp-content/uploads/2026/05/pola-makan.png','kategori'=>'Artikel','status'=>'published','created_at'=>'2026-05-12 10:16:42','updated_at'=>'2026-05-12 10:16:42']);
        \App\Models\CmsArtikel::firstOrCreate(['slug'=>'apakah-penyakit-campak-menular-dan-bagaimana-cara-penanganannya'],['judul'=>'Apakah Penyakit Campak Menular dan Bagaimana Cara Penanganannya?','konten'=>'','excerpt'=>'','thumbnail'=>'https://www.mikalaglobalmedika.com/wp-content/uploads/2026/05/campak.png','kategori'=>'Artikel','status'=>'published','created_at'=>'2026-05-11 08:07:47','updated_at'=>'2026-05-11 08:07:47']);
        \App\Models\CmsArtikel::firstOrCreate(['slug'=>'layanan-dokter-visit-mikala-hadir-langsung-ke-rumah-bukan-sekadar-chat'],['judul'=>'Layanan Dokter Visit Mikala Hadir Langsung ke Rumah, Bukan Sekadar Chat','konten'=>'','excerpt'=>'','thumbnail'=>'https://www.mikalaglobalmedika.com/wp-content/uploads/2026/05/layanan-dokter-mgm.png','kategori'=>'Artikel','status'=>'published','created_at'=>'2026-05-13 09:09:35','updated_at'=>'2026-05-13 09:09:35']);
        \App\Models\CmsArtikel::firstOrCreate(['slug'=>'studi-tokoh-muhammad-bin-zakariya-ar-razi-dokter-muslim-pelopor-ilmu-penyakit'],['judul'=>'Studi Tokoh Muhammad bin Zakariya ar-Razi Dokter Muslim Pelopor Ilmu Penyakit','konten'=>'','excerpt'=>'','thumbnail'=>'https://www.mikalaglobalmedika.com/wp-content/uploads/2026/05/Studi-tokoh.png','kategori'=>'Artikel','status'=>'published','created_at'=>'2026-05-14 07:14:49','updated_at'=>'2026-05-14 07:14:49']);
        \App\Models\CmsArtikel::firstOrCreate(['slug'=>'melakukan-yang-terbaik-dan-tetap-bahagia-itu-sudah-sebuah-pencapaian'],['judul'=>'Melakukan yang Terbaik dan Tetap Bahagia Itu Sudah Sebuah Pencapaian','konten'=>'','excerpt'=>'','thumbnail'=>'https://www.mikalaglobalmedika.com/wp-content/uploads/2026/05/melakukan-terbaik.png','kategori'=>'Artikel','status'=>'published','created_at'=>'2026-05-15 08:18:00','updated_at'=>'2026-05-15 08:18:00']);
        \App\Models\CmsLayanan::firstOrCreate(['nama'=>'Pelayanan Medikal Evakuasi'],['deskripsi'=>'','gambar'=>'https://www.mikalaglobalmedika.com/wp-content/uploads/2023/04/ambulan.png','wa_link'=>'http://wa.me/6281296998827','is_active'=>true,'urutan'=>1]);
        \App\Models\CmsLayanan::firstOrCreate(['nama'=>'Pelayanan Penunjang Kesehatan lain'],['deskripsi'=>'','gambar'=>'https://www.mikalaglobalmedika.com/wp-content/uploads/2023/04/fisioterapi1.jpg','wa_link'=>'http://wa.me/6281296998827','is_active'=>true,'urutan'=>2]);
        \App\Models\CmsLayanan::firstOrCreate(['nama'=>'Perawat Jiwa'],['deskripsi'=>'','gambar'=>'https://www.mikalaglobalmedika.com/wp-content/uploads/2023/04/perawat-pasien-gangguan-jiwa.jpg','wa_link'=>'http://wa.me/6281296998827','is_active'=>true,'urutan'=>3]);
        \App\Models\CmsLayanan::firstOrCreate(['nama'=>'Perawat Medis'],['deskripsi'=>'','gambar'=>'https://www.mikalaglobalmedika.com/wp-content/uploads/2024/08/1.jpg','wa_link'=>'http://wa.me/6281296998827','is_active'=>true,'urutan'=>4]);
        \App\Models\CmsLayanan::firstOrCreate(['nama'=>'Caregiver (Perawat Lansia)'],['deskripsi'=>'','gambar'=>'https://www.mikalaglobalmedika.com/wp-content/uploads/2024/08/caregiver_cover.jpg','wa_link'=>'http://wa.me/6281296998827','is_active'=>true,'urutan'=>5]);
        \App\Models\CmsLayanan::firstOrCreate(['nama'=>'Baby Sitter'],['deskripsi'=>'','gambar'=>'https://www.mikalaglobalmedika.com/wp-content/uploads/2024/08/bunda-ini-panduan-memilih-dan-melatih-babysitter-untuk-si-kecil.jpg','wa_link'=>'http://wa.me/6281296998827','is_active'=>true,'urutan'=>6]);
        \App\Models\CmsLayanan::firstOrCreate(['nama'=>'Dokter Visit'],['deskripsi'=>'','gambar'=>'https://www.mikalaglobalmedika.com/wp-content/uploads/2024/08/Pengertian-Home-Visit.jpg','wa_link'=>'http://wa.me/6281296998827','is_active'=>true,'urutan'=>7]);
        \App\Models\Setting::updateOrCreate(['key'=>'site_title'],['value'=>'Mikala Global Medika']);
        \App\Models\Setting::updateOrCreate(['key'=>'site_description'],['value'=>'Layanan Homecare 24 Jam Profesional']);
        \App\Models\Setting::updateOrCreate(['key'=>'hero_title'],['value'=>'Melayani Kebutuhan Kesehatan Anda']);
        \App\Models\Setting::updateOrCreate(['key'=>'hero_subtitle'],['value'=>'Penyedia layanan medis terpercaya yang berkomitmen memberikan pelayanan Homecare terbaik secara profesional']);
        \App\Models\Setting::updateOrCreate(['key'=>'hero_image'],['value'=>'https://www.mikalaglobalmedika.com/wp-content/uploads/2024/09/home-imag-MGM.jpg']);
        \App\Models\Setting::updateOrCreate(['key'=>'wa_number'],['value'=>'6281296998827']);
        \App\Models\Setting::updateOrCreate(['key'=>'email_cs'],['value'=>'cs@mikalaglobalmedika.com']);
        \App\Models\Setting::updateOrCreate(['key'=>'alamat'],['value'=>'Jl. Anyelir No. 1-2, Jatibening, Kota Bekasi']);
        \App\Models\Setting::updateOrCreate(['key'=>'jam_operasional'],['value'=>'Senin-Sabtu 08.00-21.00 WIB | Standby Admin 24 jam']);
        \App\Models\Setting::updateOrCreate(['key'=>'facebook'],['value'=>'https://www.facebook.com/mikalaglobalmdk/']);
        \App\Models\Setting::updateOrCreate(['key'=>'instagram'],['value'=>'https://www.instagram.com/mikalaglobalmedika/']);
        \App\Models\Setting::updateOrCreate(['key'=>'tiktok'],['value'=>'https://www.tiktok.com/@mikalaglobalmedika_pt']);
        \App\Models\Setting::updateOrCreate(['key'=>'youtube'],['value'=>'https://www.youtube.com/@MikalaGlobalMedika']);
        \App\Models\Setting::updateOrCreate(['key'=>'stats_customer'],['value'=>'500']);
        \App\Models\Setting::updateOrCreate(['key'=>'stats_nakes'],['value'=>'100']);
        \App\Models\Setting::updateOrCreate(['key'=>'stats_mitra'],['value'=>'50']);
        \App\Models\Setting::updateOrCreate(['key'=>'phone'],['value'=>'0821-1448-8878']);
        return response()->json(['success'=>true,'articles'=>\App\Models\CmsArtikel::count(),'layanan'=>\App\Models\CmsLayanan::count()]);
    } catch (\Exception $e) {
        return response()->json(['success'=>false,'message'=>$e->getMessage()]);
    }
});

// CMS Public Routes (untuk frontend MGM)
Route::prefix('cms')->group(function () {
    Route::get('artikel', [CmsController::class, 'indexArtikel']);
    Route::get('artikel/{slug}', [CmsController::class, 'showArtikel']);
    Route::get('layanan', [CmsController::class, 'indexLayanan']);
    Route::get('galeri', [CmsController::class, 'indexGaleri']);
    Route::get('testimoni', [CmsController::class, 'indexTestimoni']);
    Route::get('settings', [CmsController::class, 'getSettings']);
    Route::post('testimoni', [CmsController::class, 'storeTestimoni']);
});


// TEMPORARY - Create CMS tables
Route::get('/migrate-cms', function() {
    try {
        \Illuminate\Support\Facades\DB::statement("
            CREATE TABLE IF NOT EXISTS cms_artikel (
                id BIGSERIAL PRIMARY KEY,
                judul VARCHAR(255) NOT NULL,
                slug VARCHAR(255) UNIQUE NOT NULL,
                konten TEXT,
                excerpt TEXT,
                thumbnail TEXT,
                kategori VARCHAR(100),
                status VARCHAR(20) DEFAULT 'draft',
                author_id BIGINT,
                meta_title VARCHAR(255),
                meta_description TEXT,
                tags TEXT,
                views INT DEFAULT 0,
                created_at TIMESTAMP DEFAULT NOW(),
                updated_at TIMESTAMP DEFAULT NOW(),
                deleted_at TIMESTAMP NULL
            )
        ");
        \Illuminate\Support\Facades\DB::statement("
            CREATE TABLE IF NOT EXISTS cms_layanan (
                id BIGSERIAL PRIMARY KEY,
                nama VARCHAR(255) NOT NULL,
                deskripsi TEXT,
                deskripsi_panjang TEXT,
                icon VARCHAR(100),
                gambar TEXT,
                urutan INT DEFAULT 0,
                wa_link VARCHAR(255),
                is_active BOOLEAN DEFAULT true,
                meta_title VARCHAR(255),
                meta_description TEXT,
                created_at TIMESTAMP DEFAULT NOW(),
                updated_at TIMESTAMP DEFAULT NOW()
            )
        ");
        \Illuminate\Support\Facades\DB::statement("
            CREATE TABLE IF NOT EXISTS cms_galeri (
                id BIGSERIAL PRIMARY KEY,
                judul VARCHAR(255),
                url TEXT NOT NULL,
                thumbnail TEXT,
                kategori VARCHAR(100),
                deskripsi TEXT,
                created_at TIMESTAMP DEFAULT NOW(),
                updated_at TIMESTAMP DEFAULT NOW()
            )
        ");
        \Illuminate\Support\Facades\DB::statement("
            CREATE TABLE IF NOT EXISTS cms_testimoni (
                id BIGSERIAL PRIMARY KEY,
                nama VARCHAR(255) NOT NULL,
                layanan VARCHAR(255),
                rating INT DEFAULT 5,
                komentar TEXT,
                foto TEXT,
                status VARCHAR(20) DEFAULT 'pending',
                created_at TIMESTAMP DEFAULT NOW(),
                updated_at TIMESTAMP DEFAULT NOW()
            )
        ");
        return response()->json(['success'=>true,'message'=>'CMS tables created!']);
    } catch (\Exception $e) {
        return response()->json(['success'=>false,'message'=>$e->getMessage()]);
    }
});

Route::middleware('auth:sanctum')->group(function () {

    // Auth routes (logged-in users)
    Route::prefix('auth')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/refresh', [AuthController::class, 'refresh']);
    });

    // ========================================================================
    // INTERNAL ROUTES (Staff Only)
    // ========================================================================
    Route::middleware('internal')->prefix('internal')->group(function () {
        Route::middleware('role:manajemen')->group(function () {
            Route::get('settings', [SettingController::class, 'index']);
            Route::patch('settings', [SettingController::class, 'update']);
            // User management
            Route::get('users', [SettingController::class, 'indexUsers']);
            Route::post('users', [SettingController::class, 'storeUser']);
            Route::patch('users/{id}', [SettingController::class, 'updateUser']);
            Route::delete('users/{id}', [SettingController::class, 'deleteUser']);
        });

        // Dashboard
        Route::prefix('dashboard')->group(function () {
            Route::get('/summary', [DashboardController::class, 'summary']);
            Route::get('/notifikasi', [DashboardController::class, 'notifikasi']);
        });

        // Upload
        Route::post('upload', [UploadController::class, 'upload']);
        Route::post('upload/base64', [UploadController::class, 'uploadBase64']);

        // Shared - semua divisi internal bisa akses
        Route::get('klien-list', function(\Illuminate\Http\Request $request) {
            $klien = \App\Models\Klien::with('user')->orderBy('created_at','desc')->get();
            return response()->json(['success' => true, 'data' => $klien]);
        });
        Route::get('mitra-list', function(\Illuminate\Http\Request $request) {
            $query = \App\Models\Mitra::with('user')->orderBy('created_at','desc');
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }
            $mitra = $query->get();
            return response()->json(['success' => true, 'data' => $mitra]);
        });

        // Rekrutmen
        Route::middleware('role:manajemen,rekrutmen')->prefix('rekrutmen')->group(function () {
            Route::apiResource('mitra', RekrutmenController::class);
            Route::get('report', [RekrutmenController::class, 'report']);
            Route::get('report/mitra-baru', [RekrutmenController::class, 'reportMitraBaru']);
            Route::get('report/mitra-keluar', [RekrutmenController::class, 'reportMitraKeluar']);
            Route::get('report/agen-institusi', [RekrutmenController::class, 'reportAgenInstitusi']);
        });

        // Training Center
        Route::middleware('role:manajemen,training_center')->prefix('training')->group(function () {
            Route::get('mitra', [TrainingController::class, 'indexMitra']);
            Route::get('mitra/{id}', [TrainingController::class, 'showMitra']);
            Route::post('mitra/{id}/checklist', [TrainingController::class, 'updateChecklist']);
            Route::post('mitra/{id}/feedback', [TrainingController::class, 'submitFeedback']);
            Route::patch('mitra/{id}/status', [TrainingController::class, 'updateStatus']);
            Route::get('feedback', [TrainingController::class, 'indexFeedback']);
            Route::get('report', [TrainingController::class, 'report']);
            Route::get('report/available', [TrainingController::class, 'reportAvailable']);
            Route::get('report/on-job', [TrainingController::class, 'reportOnJob']);
            Route::get('report/re-training', [TrainingController::class, 'reportReTraining']);
            Route::get('pricing', [TrainingController::class, 'indexPricing']);
            Route::patch('pricing/{id}', [TrainingController::class, 'updatePricing']);
        });

        // Customer Care
        Route::middleware('role:manajemen,customer_care')->prefix('cc')->group(function () {
            Route::post('registrasi/klien', [CustomerCareController::class, 'registrasiKlien']);
            Route::post('registrasi/pasien', [CustomerCareController::class, 'registerPasien']);
            Route::get('klien', [CustomerCareController::class, 'indexKlien']);
            Route::get('klien/{id}', [CustomerCareController::class, 'showKlien']);
            Route::patch('klien/{id}', [CustomerCareController::class, 'updateKlien']);
            Route::get('mitra', [CustomerCareController::class, 'indexMitra']);
            Route::get('mitra/{id}', [CustomerCareController::class, 'showMitra']);
            Route::get('layanan', [CustomerCareController::class, 'indexLayanan']);
            Route::post('layanan', [CustomerCareController::class, 'storeLayanan']);
            Route::patch('layanan/{id}/status', [CustomerCareController::class, 'updateLayananStatus']);
            Route::get('feedback', [CustomerCareController::class, 'indexFeedback']);
            Route::post('feedback', [CustomerCareController::class, 'submitFeedback']);
            Route::patch('layanan/{id}/assign', [CustomerCareController::class, 'assignMitra']);
            Route::get('report', [CustomerCareController::class, 'report']);
            Route::get('report/handling', [CustomerCareController::class, 'reportHandling']);
            Route::get('report/deal', [CustomerCareController::class, 'reportDeal']);
            Route::get('report/loss', [CustomerCareController::class, 'reportLoss']);
        });

        // Finance
        Route::middleware('role:manajemen,finance')->prefix('finance')->group(function () {
            Route::get('tagihan', [FinanceController::class, 'indexTagihan']);
            Route::post('tagihan', [FinanceController::class, 'storeTagihan']);
            Route::get('tagihan/{id}', [FinanceController::class, 'showTagihan']);
            Route::patch('tagihan/{id}/status', [FinanceController::class, 'updateStatusTagihan']);
            Route::get('payroll', [FinanceController::class, 'indexPayroll']);
            Route::post('payroll/generate', [FinanceController::class, 'generatePayroll']);
            Route::get('payroll/{id}', [FinanceController::class, 'showPayroll']);
            Route::patch('payroll/{id}/status', [FinanceController::class, 'updateStatusPayroll']);
            Route::get('jurnal', [FinanceController::class, 'indexJurnal']);
            Route::post('jurnal', [FinanceController::class, 'storeJurnal']);
            Route::get('jurnal/balancing', [FinanceController::class, 'balancing']);
            Route::get('report/income', [FinanceController::class, 'reportIncome']);
            Route::get('report/outcome', [FinanceController::class, 'reportOutcome']);
            Route::get('report/saldo', [FinanceController::class, 'reportSaldo']);
            Route::get('report/piutang', [FinanceController::class, 'reportPiutang']);
            Route::get('report/utang', [FinanceController::class, 'reportUtang']);
        });

        // CMS Management
        Route::middleware('role:manajemen,marketing')->prefix('cms')->group(function () {
            Route::get('artikel', [CmsController::class, 'indexArtikel']);
            Route::post('artikel', [CmsController::class, 'storeArtikel']);
            Route::patch('artikel/{id}', [CmsController::class, 'updateArtikel']);
            Route::delete('artikel/{id}', [CmsController::class, 'deleteArtikel']);
            Route::get('layanan', [CmsController::class, 'indexLayanan']);
            Route::post('layanan', [CmsController::class, 'storeLayanan']);
            Route::patch('layanan/{id}', [CmsController::class, 'updateLayanan']);
            Route::delete('layanan/{id}', [CmsController::class, 'deleteLayanan']);
            Route::get('galeri', [CmsController::class, 'indexGaleri']);
            Route::post('galeri', [CmsController::class, 'storeGaleri']);
            Route::delete('galeri/{id}', [CmsController::class, 'deleteGaleri']);
            Route::get('testimoni', [CmsController::class, 'indexTestimoni']);
            Route::patch('testimoni/{id}', [CmsController::class, 'updateTestimoni']);
            Route::get('settings', [CmsController::class, 'getSettings']);
            Route::post('settings', [CmsController::class, 'updateSettings']);
        });

        // Marketing
        Route::middleware('role:manajemen,marketing')->prefix('marketing')->group(function () {
            Route::get('leads', [MarketingController::class, 'indexLeads']);
            Route::post('leads', [MarketingController::class, 'storeLeads']);
            Route::get('leads/{id}', [MarketingController::class, 'showLeads']);
            Route::patch('leads/{id}/status', [MarketingController::class, 'updateLeadsStatus']);
            Route::get('kerjasama', [MarketingController::class, 'indexKerjasama']);
            Route::post('kerjasama', [MarketingController::class, 'storeKerjasama']);
            Route::get('kerjasama/{id}', [MarketingController::class, 'showKerjasama']);
            Route::get('report/order-in', [MarketingController::class, 'reportOrderIn']);
            Route::get('report/deal', [MarketingController::class, 'reportDeal']);
            Route::get('report/gap-analysis', [MarketingController::class, 'reportGapAnalysis']);
            Route::patch('leads/{id}/status', [MarketingController::class, 'updateLeadsStatus']);
            Route::get('kerjasama', [MarketingController::class, 'indexKerjasama']);
            Route::post('kerjasama', [MarketingController::class, 'storeKerjasama']);
            Route::get('kerjasama/{id}', [MarketingController::class, 'showKerjasama']);
            Route::get('report/order-in', [MarketingController::class, 'reportOrderIn']);
            Route::get('report/deal', [MarketingController::class, 'reportDeal']);
            Route::get('report/gap-analysis', [MarketingController::class, 'reportGapAnalysis']);
        });
    });

    // ========================================================================
    // MITRA ROUTES
    // ========================================================================
    Route::middleware('role:mitra')->prefix('mitra')->group(function () {
        Route::post('upload', [UploadController::class, 'upload']);
        Route::get('dashboard', function(\Illuminate\Http\Request $request) {
            try {
                $user = $request->user();
                $mitra = $user->mitra;
                if (!$mitra) return response()->json(['success' => false, 'message' => 'Mitra not found'], 404);

                $activeJobs    = \App\Models\Order::where('mitra_id', $mitra->id)->whereIn('status', ['confirmed','in_progress'])->count();
                $completedJobs = \App\Models\Order::where('mitra_id', $mitra->id)->where('status', 'completed')->count();
                $totalEarnings = \App\Models\Payroll::where('mitra_id', $mitra->id)->where('status', 'paid')->sum('total');
                $recentJobs    = \App\Models\Order::where('mitra_id', $mitra->id)->with(['klien.user','pasien'])->orderBy('created_at','desc')->limit(3)->get();

                return response()->json(['success' => true, 'data' => [
                    'active_jobs'    => $activeJobs,
                    'completed_jobs' => $completedJobs,
                    'total_earnings' => $totalEarnings,
                    'recent_jobs'    => $recentJobs,
                ]]);
            } catch (\Exception $e) {
                return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
            }
        });
        Route::get('profile', [MitraProfileController::class, 'show']);
        Route::patch('profile', [MitraProfileController::class, 'update']);
        Route::get('jobs', [MitraJobController::class, 'index']);
        Route::get('jobs/{id}', [MitraJobController::class, 'show']);
        Route::patch('jobs/{id}/status', [MitraJobController::class, 'updateStatus']);
        Route::get('payroll', [MitraPayrollController::class, 'index']);
        Route::get('payroll/{id}', [MitraPayrollController::class, 'show']);
        Route::get('notifikasi', [NotifikasiController::class, 'index']);
    });

    // ========================================================================
    // KLIEN ROUTES
    // ========================================================================
    Route::get('payment-settings', [SettingController::class, 'publicPayment']);
    Route::middleware('role:klien')->prefix('klien')->group(function () {
        Route::get('profile', [KlienProfileController::class, 'show']);
        Route::patch('profile', [KlienProfileController::class, 'update']);
        Route::get('pasien', [KlienLayananController::class, 'indexPasien']);
        Route::post('pasien', [KlienLayananController::class, 'storePasien']);
        Route::patch('pasien/{id}', [KlienLayananController::class, 'updatePasien']);
        Route::get('layanan', [KlienLayananController::class, 'index']);
        Route::post('layanan', [KlienLayananController::class, 'store']);
        Route::get('layanan/{id}', [KlienLayananController::class, 'show']);
        Route::get('tagihan', [KlienBillingController::class, 'index']);
        Route::get('tagihan/{id}', [KlienBillingController::class, 'show']);
        Route::post('tagihan/{id}/bayar', [KlienBillingController::class, 'bayar']);
        Route::get('mitra', [KlienLayananController::class, 'indexMitra']);
        Route::post('layanan/{orderId}/feedback', [KlienLayananController::class, 'submitFeedback']);
        Route::get('notifikasi', [NotifikasiController::class, 'index']);
    });

    // ========================================================================
    // SHARED ROUTES (Accessible by authenticated users)
    // ========================================================================
    Route::prefix('notifikasi')->group(function () {
        Route::get('/', [NotifikasiController::class, 'index']);
        Route::get('/unread-count', [NotifikasiController::class, 'unreadCount']);
        Route::patch('/{id}/read', [NotifikasiController::class, 'markAsRead']);
        Route::post('/mark-all-read', [NotifikasiController::class, 'markAllAsRead']);
    });
});


