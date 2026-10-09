<?php

namespace App\Console\Commands;

use App\Support\ArtikelScheduler;
use Illuminate\Console\Command;

class PublishScheduledArtikel extends Command
{
    protected $signature = 'artikel:publish-scheduled';
    protected $description = 'Publish artikel scheduled yang sudah waktunya (cms_artikel + mga_artikel)';

    public function handle()
    {
        $count = ArtikelScheduler::publishDue();
        $this->info("Published: MGM={$count['mgm']}, MGA={$count['mga']} artikel @ " . now());
        return 0;
    }
}
