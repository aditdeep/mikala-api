<?php

namespace App\Jobs;

use App\Models\Notifikasi;
use App\Services\NotifikasiService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendPushNotifikasiJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $notifikasiId
    ) {}

    /**
     * Execute the job.
     */
    public function handle(NotifikasiService $notifikasiService): void
    {
        $notif = Notifikasi::find($this->notifikasiId);
        
        if (!$notif || $notif->is_sent_push) {
            return;
        }

        // TODO: Send via FCM
        // $fcm->send($notif->user->fcm_token, $notif->title, $notif->message);

        $notif->update(['is_sent_push' => true]);
    }
}
