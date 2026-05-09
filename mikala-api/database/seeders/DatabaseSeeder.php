<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Mitra;
use App\Models\Klien;
use App\Models\Pasien;
use App\Models\Agen;
use App\Models\Order;
use App\Models\Tagihan;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 1. Create Admin (Manajemen)
        $admin = User::create([
            'name' => 'Admin Mikala',
            'email' => 'admin@mikala.com',
            'phone' => '081234567890',
            'password' => Hash::make('password'),
            'role' => 'manajemen',
            'status' => 'active',
        ]);

        // 2. Create Finance User
        $finance = User::create([
            'name' => 'Finance Staff',
            'email' => 'finance@mikala.com',
            'phone' => '081234567891',
            'password' => Hash::make('password'),
            'role' => 'finance',
            'status' => 'active',
        ]);

        // 3. Create Customer Care User
        $cc = User::create([
            'name' => 'Customer Care',
            'email' => 'cc@mikala.com',
            'phone' => '081234567892',
            'password' => Hash::make('password'),
            'role' => 'customer_care',
            'status' => 'active',
        ]);

        // 4. Create Sample Mitra (2 nurses)
        $mitraUser1 = User::create([
            'name' => 'Siti Nurhaliza',
            'email' => 'siti@example.com',
            'phone' => '081298765431',
            'password' => Hash::make('password'),
            'role' => 'mitra',
            'status' => 'active',
        ]);

        $mitra1 = Mitra::create([
            'user_id' => $mitraUser1->id,
            'nik' => '3201234567890001',
            'nama_lengkap' => 'Siti Nurhaliza',
            'tanggal_lahir' => '1992-05-15',
            'jenis_kelamin' => 'P',
            'alamat' => 'Jl. Mawar No. 10, Bandung',
            'kota' => 'Bandung',
            'provinsi' => 'Jawa Barat',
            'pendidikan_terakhir' => 'D3 Keperawatan',
            'sertifikasi' => 'STR Aktif',
            'status' => 'available',
            'is_verified' => true,
            'training_status' => 'completed',
            'training_score' => 85,
            'rating' => 4.80,
            'total_reviews' => 12,
            'total_jobs' => 25,
        ]);

        $mitraUser1->update([
            'profile_type' => 'Mitra',
            'profile_id' => $mitra1->id,
        ]);

        $mitraUser2 = User::create([
            'name' => 'Budi Santoso',
            'email' => 'budi@example.com',
            'phone' => '081298765432',
            'password' => Hash::make('password'),
            'role' => 'mitra',
            'status' => 'active',
        ]);

        $mitra2 = Mitra::create([
            'user_id' => $mitraUser2->id,
            'nik' => '3201234567890002',
            'nama_lengkap' => 'Budi Santoso',
            'tanggal_lahir' => '1988-08-20',
            'jenis_kelamin' => 'L',
            'alamat' => 'Jl. Melati No. 25, Jakarta',
            'kota' => 'Jakarta',
            'provinsi' => 'DKI Jakarta',
            'pendidikan_terakhir' => 'S1 Keperawatan',
            'sertifikasi' => 'STR Aktif, BTCLS',
            'status' => 'on_job',
            'is_verified' => true,
            'training_status' => 'completed',
            'training_score' => 90,
            'rating' => 4.95,
            'total_reviews' => 30,
            'total_jobs' => 50,
        ]);

        $mitraUser2->update([
            'profile_type' => 'Mitra',
            'profile_id' => $mitra2->id,
        ]);

        // 5. Create Sample Klien (2 clients)
        $klienUser1 = User::create([
            'name' => 'Ibu Aminah',
            'email' => 'aminah@example.com',
            'phone' => '081298765433',
            'password' => Hash::make('password'),
            'role' => 'klien',
            'status' => 'active',
        ]);

        $klien1 = Klien::create([
            'user_id' => $klienUser1->id,
            'nama_lengkap' => 'Ibu Aminah',
            'tipe' => 'individu',
            'nik' => '3201234567890010',
            'alamat' => 'Jl. Anggrek No. 5, Jakarta',
            'kota' => 'Jakarta',
            'provinsi' => 'DKI Jakarta',
            'billing_method' => 'transfer',
            'status' => 'active',
            'is_verified' => true,
            'total_pasien' => 1,
        ]);

        $klienUser1->update([
            'profile_type' => 'Klien',
            'profile_id' => $klien1->id,
        ]);

        // Create pasien for klien1
        $pasien1 = Pasien::create([
            'klien_id' => $klien1->id,
            'nama_lengkap' => 'Bapak Ahmad (Ayah Ibu Aminah)',
            'tanggal_lahir' => '1945-03-10',
            'jenis_kelamin' => 'L',
            'alamat' => 'Jl. Anggrek No. 5, Jakarta',
            'riwayat_penyakit' => 'Diabetes, Hipertensi',
            'golongan_darah' => 'O',
            'status' => 'active',
        ]);

        $klienUser2 = User::create([
            'name' => 'RS Harapan Sehat',
            'email' => 'admin@rsharapansehat.com',
            'phone' => '081298765434',
            'password' => Hash::make('password'),
            'role' => 'klien',
            'status' => 'active',
        ]);

        $klien2 = Klien::create([
            'user_id' => $klienUser2->id,
            'nama_lengkap' => 'RS Harapan Sehat',
            'tipe' => 'rumah_sakit',
            'nama_perusahaan' => 'RS Harapan Sehat',
            'npwp' => '123456789012345',
            'alamat' => 'Jl. Kesehatan No. 100, Bandung',
            'kota' => 'Bandung',
            'provinsi' => 'Jawa Barat',
            'billing_method' => 'corporate',
            'status' => 'active',
            'is_verified' => true,
        ]);

        $klienUser2->update([
            'profile_type' => 'Klien',
            'profile_id' => $klien2->id,
        ]);

        // 6. Create Sample Agen
        $agenUser = User::create([
            'name' => 'Agen Panti Jompo Sejahtera',
            'email' => 'agen@pantisejahtera.com',
            'phone' => '081298765435',
            'password' => Hash::make('password'),
            'role' => 'agen',
            'status' => 'active',
        ]);

        $agen = Agen::create([
            'user_id' => $agenUser->id,
            'nama_institusi' => 'Panti Jompo Sejahtera',
            'tipe_institusi' => 'Panti Jompo',
            'contact_person_name' => 'Bapak Sutrisno',
            'contact_person_jabatan' => 'Direktur',
            'contact_person_phone' => '081298765435',
            'contact_person_email' => 'sutrisno@pantisejahtera.com',
            'alamat' => 'Jl. Harmoni No. 50, Surabaya',
            'kota' => 'Surabaya',
            'provinsi' => 'Jawa Timur',
            'komisi_persen' => 10.00,
            'status' => 'active',
        ]);

        $agenUser->update([
            'profile_type' => 'Agen',
            'profile_id' => $agen->id,
        ]);

        // 7. Create Sample Order
        $order1 = Order::create([
            'order_number' => Order::generateOrderNumber(),
            'klien_id' => $klien1->id,
            'pasien_id' => $pasien1->id,
            'mitra_id' => $mitra2->id,
            'tipe_layanan' => 'homecare_live_in',
            'deskripsi_layanan' => 'Perawatan lansia dengan diabetes dan hipertensi',
            'tanggal_mulai' => now()->subDays(7),
            'tanggal_selesai' => now()->addDays(23),
            'durasi_hari' => 30,
            'harga_per_hari' => 500000,
            'subtotal' => 15000000,
            'pajak' => 0,
            'diskon' => 0,
            'total' => 15000000,
            'status' => 'in_progress',
            'confirmed_at' => now()->subDays(7),
            'started_at' => now()->subDays(7),
        ]);

        // 8. Create Sample Tagihan
        $tagihan1 = Tagihan::create([
            'invoice_number' => Tagihan::generateInvoiceNumber(),
            'klien_id' => $klien1->id,
            'order_id' => $order1->id,
            'tanggal_invoice' => now()->subDays(7),
            'tanggal_jatuh_tempo' => now()->addDays(7),
            'subtotal' => 15000000,
            'pajak' => 0,
            'diskon' => 0,
            'total' => 15000000,
            'jumlah_bayar' => 0,
            'sisa' => 15000000,
            'status' => 'unpaid',
        ]);

        $this->command->info('✅ Seeding completed successfully!');
        $this->command->info('');
        $this->command->info('Test Credentials:');
        $this->command->info('Admin: admin@mikala.com / password');
        $this->command->info('Finance: finance@mikala.com / password');
        $this->command->info('CC: cc@mikala.com / password');
        $this->command->info('Mitra 1: siti@example.com / password');
        $this->command->info('Mitra 2: budi@example.com / password');
        $this->command->info('Klien 1: aminah@example.com / password');
        $this->command->info('Klien 2: admin@rsharapansehat.com / password');
        $this->command->info('Agen: agen@pantisejahtera.com / password');
    }
}
