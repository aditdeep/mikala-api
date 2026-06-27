<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Notifikasi extends Model
{
    use HasFactory;

    protected $table = 'notifikasi';

    protected $fillable = [
        'user_id',
        'title',
        'message',
        'type',
        'related_type',
        'related_id',
        'is_read',
        'is_sent_push',
        'is_sent_email',
        'action_url',
        'read_at',
        'sent_at',
    ];

    protected $casts = [
        'is_read' => 'boolean',
        'is_sent_push' => 'boolean',
        'is_sent_email' => 'boolean',
        'read_at' => 'datetime',
        'sent_at' => 'datetime',
    ];

    // Relationships
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function related()
    {
        if ($this->related_type && $this->related_id) {
            return $this->morphTo('related', 'related_type', 'related_id');
        }
        return null;
    }

    // Scopes
    public function scopeUnread($query)
    {
        return $query->whereRaw('is_read = false');
    }

    public function scopeByType($query, $type)
    {
        return $query->where('type', $type);
    }

    // Helpers
    public function markAsRead(): void
    {
        $this->update([
            'is_read' => true,
            'read_at' => now(),
        ]);
    }
}
