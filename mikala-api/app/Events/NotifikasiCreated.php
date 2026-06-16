<?php

namespace App\Events;

use App\Models\Notifikasi;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NotifikasiCreated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Notifikasi $notifikasi;

    public function __construct(Notifikasi $notifikasi)
    {
        $this->notifikasi = $notifikasi;
    }

    /**
     * Broadcast ke private channel per user
     * Channel: notifikasi.{user_id}
     */
    public function broadcastOn(): Channel
    {
        return new PrivateChannel('notifikasi.' . $this->notifikasi->user_id);
    }

    public function broadcastAs(): string
    {
        return 'notifikasi.created';
    }

    public function broadcastWith(): array
    {
        return [
            'id'         => $this->notifikasi->id,
            'tipe'       => $this->notifikasi->tipe,
            'judul'      => $this->notifikasi->judul,
            'pesan'      => $this->notifikasi->pesan,
            'data'       => $this->notifikasi->data,
            'created_at' => $this->notifikasi->created_at?->toIso8601String(),
        ];
    }
}
