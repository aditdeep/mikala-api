<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MigrasiKontenHtml extends Command
{
    protected $signature = 'migrasi:konten-html {--limit=} {--slug=} {--force}';
    protected $description = 'Scrape konten artikel dari HTML WP (elementor-widget-text-editor) ke cms_artikel.konten';

    private function fetchHtml($url)
    {
        $ctx = stream_context_create(['http' => [
            'timeout' => 30,
            'follow_location' => 1,
            'max_redirects' => 5,
            'header' => "User-Agent: Mozilla/5.0 (compatible; MikalaMigrator)\r\n",
        ]]);
        $html = @file_get_contents($url, false, $ctx);
        return $html === false ? null : $html;
    }

    private function extractKonten($html)
    {
        // ambil semua blok elementor-widget-text-editor > elementor-widget-container
        if (!preg_match_all('/elementor-widget-text-editor.*?<div class="elementor-widget-container">(.*?)<\/div>\s*<\/div>/s', $html, $m)) {
            return null;
        }
        $blocks = $m[1];
        if (empty($blocks)) return null;
        // ambil blok terpanjang = konten utama
        usort($blocks, fn($a, $b) => strlen($b) - strlen($a));
        $konten = trim($blocks[0]);
        // bersihkan: hapus komentar HTML, normalize whitespace berlebih
        $konten = preg_replace('/<!--.*?-->/s', '', $konten);
        $konten = trim($konten);
        return $konten ?: null;
    }

    public function handle()
    {
        $limit = $this->option('limit');
        $slug  = $this->option('slug');
        $force = $this->option('force');

        $q = DB::table('cms_artikel')->whereNull('deleted_at');
        if ($slug) {
            $q->where('slug', $slug);
        } elseif (!$force) {
            // default: cuma yang konten kosong
            $q->where(function ($x) {
                $x->whereNull('konten')->orWhere('konten', '');
            });
        }
        if ($limit) $q->limit((int) $limit);

        $rows = $q->get(['id', 'slug', 'judul']);
        $this->info('Total artikel diproses: ' . $rows->count());

        $ok = 0; $error = 0;
        foreach ($rows as $r) {
            $url = 'https://www.mikalaglobalmedika.com/' . $r->slug . '/';
            $html = $this->fetchHtml($url);
            if (!$html) {
                $this->error("FETCH GAGAL: {$r->slug}");
                $error++;
                continue;
            }
            $konten = $this->extractKonten($html);
            if (!$konten) {
                $this->error("NO KONTEN: {$r->slug}");
                $error++;
                continue;
            }
            DB::table('cms_artikel')->where('id', $r->id)->update([
                'konten' => $konten,
                'updated_at' => now(),
            ]);
            $this->info("OK {$r->slug} (" . strlen($konten) . " char)");
            $ok++;
            usleep(300000); // 0.3s jeda antar request (sopan ke server)
        }

        $this->info("Selesai. OK:{$ok} | ERROR:{$error}");
        return 0;
    }
}
