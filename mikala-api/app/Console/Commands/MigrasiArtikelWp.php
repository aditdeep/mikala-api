<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Cloudinary\Cloudinary;
use Cloudinary\Configuration\Configuration;

class MigrasiArtikelWp extends Command
{
    protected $signature = 'migrasi:artikel-wp {--limit=} {--dry-run} {--skip-image}';
    protected $description = 'Migrasi artikel dari WordPress mikalaglobalmedika.com ke cms_artikel (upsert via slug)';

    private $wp = 'https://mikalaglobalmedika.com/wp-json/wp/v2';
    private $catCache = [];

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

    private function get($url)
    {
        $ctx = stream_context_create(['http' => ['timeout' => 30, 'header' => "User-Agent: MikalaMigrator\r\n"]]);
        $res = @file_get_contents($url, false, $ctx);
        return $res === false ? null : json_decode($res, true);
    }

    private function kategoriNama($ids)
    {
        if (empty($ids)) return 'Umum';
        $id = $ids[0];
        if (isset($this->catCache[$id])) return $this->catCache[$id];
        $cat = $this->get($this->wp . '/categories/' . $id);
        $nama = $cat['name'] ?? 'Umum';
        $this->catCache[$id] = $nama;
        return $nama;
    }

    public function handle()
    {
        $dryRun = $this->option('dry-run');
        $skipImage = $this->option('skip-image');
        $limit = $this->option('limit');

        $cloudinary = ($dryRun || $skipImage) ? null : $this->getCloudinary();
        $ok = 0; $new = 0; $upd = 0; $error = 0;
        $page = 1; $processed = 0;

        while (true) {
            $posts = $this->get($this->wp . '/posts?per_page=20&page=' . $page);
            if (empty($posts) || !is_array($posts)) break;

            foreach ($posts as $p) {
                if ($limit && $processed >= (int)$limit) break 2;
                $processed++;

                $judul = html_entity_decode(strip_tags($p['title']['rendered'] ?? ''), ENT_QUOTES, 'UTF-8');
                $slug  = $p['slug'] ?? Str::slug($judul);
                $konten = $p['content']['rendered'] ?? '';
                $excerpt = html_entity_decode(trim(strip_tags($p['excerpt']['rendered'] ?? '')), ENT_QUOTES, 'UTF-8');
                $excerpt = Str::limit($excerpt, 300, '');
                $kategori = $this->kategoriNama($p['categories'] ?? []);
                $tanggal = $p['date'] ?? now();

                $thumbnail = null;
                $caption = null;
                $mediaId = $p['featured_media'] ?? 0;
                if ($mediaId) {
                    $media = $this->get($this->wp . '/media/' . $mediaId);
                    $srcUrl = $media['source_url'] ?? null;
                    $caption = html_entity_decode(trim(strip_tags($media['caption']['rendered'] ?? '')), ENT_QUOTES, 'UTF-8') ?: null;

                    if ($srcUrl && !$skipImage && !$dryRun) {
                        try {
                            $tmp = sys_get_temp_dir() . '/wp_' . basename(parse_url($srcUrl, PHP_URL_PATH));
                            $img = @file_get_contents($srcUrl);
                            if ($img !== false) {
                                file_put_contents($tmp, $img);
                                $up = $cloudinary->uploadApi()->upload($tmp, [
                                    'folder' => 'mikala/artikel',
                                    'public_id' => 'artikel_' . $slug,
                                    'resource_type' => 'image',
                                    'overwrite' => true,
                                ]);
                                @unlink($tmp);
                                $thumbnail = $up['secure_url'] ?? null;
                            }
                        } catch (\Throwable $e) {
                            $this->error("img {$slug}: " . $e->getMessage());
                        }
                    } elseif ($srcUrl) {
                        $thumbnail = $srcUrl;
                    }
                }

                if ($dryRun) {
                    $this->line("[DRY] {$judul} | slug:{$slug} | kat:{$kategori} | cap:" . ($caption ? substr($caption,0,40) : '-'));
                    $ok++;
                    continue;
                }

                $exists = DB::table('cms_artikel')->where('slug', $slug)->first();
                $payload = [
                    'judul' => $judul,
                    'slug' => $slug,
                    'konten' => $konten,
                    'excerpt' => $excerpt,
                    'kategori' => $kategori,
                    'status' => 'published',
                    'author_id' => 1,
                    'meta_title' => Str::limit($judul, 60, ''),
                    'meta_description' => Str::limit($excerpt, 160, ''),
                    'updated_at' => now(),
                ];
                if ($thumbnail) $payload['thumbnail'] = $thumbnail;
                if ($caption !== null) $payload['thumbnail_caption'] = $caption;

                if ($exists) {
                    DB::table('cms_artikel')->where('slug', $slug)->update($payload);
                    $upd++;
                } else {
                    $payload['created_at'] = date('Y-m-d H:i:s', strtotime($tanggal));
                    DB::table('cms_artikel')->insert($payload);
                    $new++;
                }
                $ok++;
                $this->info("OK {$judul}");
            }
            $page++;
        }

        $this->info("Selesai. OK:{$ok} (baru:{$new} update:{$upd}) | ERROR:{$error}");
        return 0;
    }
}
