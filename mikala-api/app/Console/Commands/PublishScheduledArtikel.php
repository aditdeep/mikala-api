<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PublishScheduledArtikel extends Command
{
    protected $signature = 'artikel:publish-scheduled';
    protected $description = 'Publish artikel scheduled yang sudah waktunya (cms_artikel + mga_artikel)';

    public function handle()
    {
        $now = now();

        // MGM: cms_artikel
        $cms = DB::table('cms_artikel')
            ->where('status', 'scheduled')
            ->where('published_at', '<=', $now)
            ->update(['status' => 'published', 'updated_at' => $now]);

        // MGA: mga_artikel
        $mga = DB::table('mga_artikel')
            ->where('status', 'scheduled')
            ->where('published_at', '<=', $now)
            ->update(['status' => 'published', 'updated_at' => $now]);

        $this->info("Published: MGM={$cms}, MGA={$mga} artikel @ {$now}");
        return 0;
    }
}
