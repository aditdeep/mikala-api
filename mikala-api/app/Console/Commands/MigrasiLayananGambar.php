<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Cloudinary\Cloudinary;
use Cloudinary\Configuration\Configuration;

class MigrasiLayananGambar extends Command
{
    protected $signature = 'migrasi:layanan-gambar';
    protected $description = 'Download gambar layanan dari WP IP lama, upload Cloudinary, update cms_layanan.gambar';

    private $ip = '89.21.85.182';
    private $host = 'www.mikalaglobalmedika.com';

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

    private function download($fullUrl)
    {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $fullUrl,
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
        $rows = DB::table('cms_layanan')->get(['id', 'nama', 'gambar']);
        $ok = 0; $skip = 0; $error = 0;

        foreach ($rows as $r) {
            // skip kalau udah cloudinary
            if (strpos($r->gambar, 'cloudinary.com') !== false) {
                $this->line("SKIP (sudah cloudinary): {$r->nama}");
                $skip++;
                continue;
            }
            // hanya proses kalau dari wp-content
            if (strpos($r->gambar, 'wp-content') === false) {
                $this->line("SKIP (bukan WP): {$r->nama}");
                $skip++;
                continue;
            }

            $data = $this->download($r->gambar);
            if (!$data) {
                $this->error("GAGAL download: {$r->nama} ({$r->gambar})");
                $error++;
                continue;
            }
            $ext = pathinfo(parse_url($r->gambar, PHP_URL_PATH), PATHINFO_EXTENSION) ?: 'jpg';
            $tmp = sys_get_temp_dir() . '/layanan_' . $r->id . '.' . $ext;
            file_put_contents($tmp, $data);

            try {
                $up = $cloudinary->uploadApi()->upload($tmp, [
                    'folder' => 'mikala/layanan',
                    'public_id' => 'layanan_' . $r->id,
                    'resource_type' => 'image',
                    'overwrite' => true,
                ]);
                @unlink($tmp);
                $secureUrl = $up['secure_url'] ?? null;
                if (!$secureUrl) { $this->error("no url: {$r->nama}"); $error++; continue; }

                DB::table('cms_layanan')->where('id', $r->id)->update([
                    'gambar' => $secureUrl,
                    'updated_at' => now(),
                ]);
                $this->info("OK {$r->nama} -> {$secureUrl}");
                $ok++;
            } catch (\Throwable $e) {
                $this->error("upload gagal {$r->nama}: " . $e->getMessage());
                $error++;
            }
        }

        $this->info("\nSelesai. OK:{$ok} | SKIP:{$skip} | ERROR:{$error}");
        return 0;
    }
}
