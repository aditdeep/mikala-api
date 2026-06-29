<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Cloudinary\Cloudinary;
use Cloudinary\Configuration\Configuration;

class MigrasiGaleriWp extends Command
{
    protected $signature = 'migrasi:galeri-wp';
    protected $description = 'Download gambar galeri+layanan dari WP IP lama, upload ke Cloudinary, output URL';

    private $ip = '89.21.85.182';
    private $host = 'www.mikalaglobalmedika.com';

    // path WP => public_id Cloudinary
    private $images = [
        '2024/08/1.jpg' => 'galeri/tim-perawat-medis',
        '2024/08/caregiver_cover.jpg' => 'galeri/caregiver',
        '2024/08/bunda-ini-panduan-memilih-dan-melatih-babysitter-untuk-si-kecil.jpg' => 'galeri/babysitter',
        '2024/08/Medikal-evakuasi.jpg' => 'galeri/medikal-evakuasi',
        '2024/08/Fisioterapi_ok.jpg' => 'galeri/fisioterapi',
        '2024/08/Alat-Medis.jpg' => 'galeri/alat-medis',
        '2024/08/Apotik_ok.jpg' => 'galeri/apotik',
        '2024/09/home-imag-MGM.jpg' => 'galeri/homecare-mgm',
    ];

    private function getCloudinary()
    {
        $url = config('cloudinary.cloud_url');
        $parsed = parse_url($url);
        Configuration::instance([
            'cloud' => [
                'cloud_name' => $parsed['host'],
                'api_key'    => $parsed['user'],
                'api_secret' => $parsed['pass'],
            ],
            'url' => ['secure' => true]
        ]);
        return new Cloudinary();
    }

    private function download($path)
    {
        $url = 'https://' . $this->host . '/wp-content/uploads/' . $path;
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RESOLVE => [$this->host . ':443:' . $this->ip],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_USERAGENT => 'Mozilla/5.0',
            CURLOPT_SSL_VERIFYPEER => false,
        ]);
        $data = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return ($code == 200 && $data) ? $data : null;
    }

    public function handle()
    {
        $cloudinary = $this->getCloudinary();
        $ok = 0; $error = 0;
        $hasil = [];

        foreach ($this->images as $path => $publicId) {
            $data = $this->download($path);
            if (!$data) {
                $this->error("GAGAL download: {$path}");
                $error++;
                continue;
            }
            $tmp = sys_get_temp_dir() . '/' . basename($path);
            file_put_contents($tmp, $data);

            try {
                $up = $cloudinary->uploadApi()->upload($tmp, [
                    'folder' => 'mikala',
                    'public_id' => $publicId,
                    'resource_type' => 'image',
                    'overwrite' => true,
                ]);
                @unlink($tmp);
                $url = $up['secure_url'] ?? null;
                $hasil[$path] = $url;
                $this->info("OK {$path} -> {$url}");
                $ok++;
            } catch (\Throwable $e) {
                $this->error("UPLOAD gagal {$path}: " . $e->getMessage());
                $error++;
            }
        }

        $this->info("\n=== HASIL URL (buat ganti di frontend) ===");
        foreach ($hasil as $path => $url) {
            $this->line($path . ' => ' . $url);
        }
        $this->info("\nSelesai. OK:{$ok} | ERROR:{$error}");
        return 0;
    }
}
