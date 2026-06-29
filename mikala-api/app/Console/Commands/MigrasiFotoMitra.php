<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Cloudinary\Cloudinary;
use Cloudinary\Configuration\Configuration;

class MigrasiFotoMitra extends Command
{
    protected $signature = 'migrasi:foto-mitra {--dry-run} {--limit=}';
    protected $description = 'Download foto mitra lama dari sis.mikalaglobalmedika.com, upload ke Cloudinary, update foto_url';

    private $baseUrl = 'https://sis.mikalaglobalmedika.com/uploads/';

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

    public function handle()
    {
        $dryRun = $this->option('dry-run');
        $limit  = $this->option('limit');

        $query = DB::table('mitra')
            ->where('data_tambahan', 'like', '%foto%')
            ->where(function ($q) {
                $q->whereNull('foto_url')->orWhere('foto_url', '');
            });
        if ($limit) $query->limit((int) $limit);

        $mitras = $query->get(['id', 'nama_lengkap', 'data_tambahan', 'foto_url']);
        $this->info('Total mitra akan diproses: ' . $mitras->count());

        $cloudinary = $dryRun ? null : $this->getCloudinary();
        $ok = 0; $skip = 0; $error = 0;

        foreach ($mitras as $m) {
            $data = json_decode($m->data_tambahan, true);
            $filename = $data['foto_lama'] ?? $data['foto'] ?? null;
            if (!$filename) { $skip++; continue; }

            $url = $this->baseUrl . $filename;

            if ($dryRun) {
                $this->line("[DRY] mitra#{$m->id} {$m->nama_lengkap} <- {$url}");
                $ok++;
                continue;
            }

            try {
                // download ke temp
                $tmp = sys_get_temp_dir() . '/' . $filename;
                $content = @file_get_contents($url);
                if ($content === false) {
                    $this->error("mitra#{$m->id}: gagal download {$filename}");
                    $error++;
                    continue;
                }
                file_put_contents($tmp, $content);

                // upload Cloudinary
                $result = $cloudinary->uploadApi()->upload($tmp, [
                    'folder'        => 'mikala/mitra-foto',
                    'public_id'     => 'mitra_' . $m->id,
                    'resource_type' => 'image',
                    'overwrite'     => true,
                ]);
                @unlink($tmp);

                $secureUrl = $result['secure_url'] ?? null;
                if (!$secureUrl) {
                    $this->error("mitra#{$m->id}: no secure_url");
                    $error++;
                    continue;
                }

                DB::table('mitra')->where('id', $m->id)->update([
                    'foto_url'   => $secureUrl,
                    'updated_at' => now(),
                ]);

                $this->info("OK mitra#{$m->id} {$m->nama_lengkap} -> {$secureUrl}");
                $ok++;
            } catch (\Throwable $e) {
                $this->error("mitra#{$m->id}: " . $e->getMessage());
                $error++;
            }
        }

        $this->info("Selesai. OK:{$ok} | SKIP:{$skip} | ERROR:{$error}");
        return 0;
    }
}
