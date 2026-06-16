<?php

namespace App\Events;

use App\Models\Notifikasi;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NotifikasiCreated implements ShouldBroadcastNow
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
            'id'           => $this->notifikasi->id,
            'type'         => $this->notifikasi->type,
            'title'        => $this->notifikasi->title,
            'message'      => $this->notifikasi->message,
            'related_type' => $this->notifikasi->related_type,
            'related_id'   => $this->notifikasi->related_id,
            'is_read'      => $this->notifikasi->is_read,
            'created_at'   => $this->notifikasi->created_at?->toIso8601String(),
        ];
    }
}
