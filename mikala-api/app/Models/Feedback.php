<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Feedback extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'feedback';

    protected $fillable = [
        'order_id',
        'klien_id',
        'mitra_id',
        'rating_kualitas',
        'rating_profesionalisme',
        'rating_komunikasi',
        'rating_average',
        'komentar',
        'saran',
        'is_published',
        'is_featured',
        'response',
        'responded_by',
        'responded_at',
    ];

    protected $casts = [
        'rating_average' => 'decimal:2',
        'is_published' => 'boolean',
        'is_featured' => 'boolean',
        'responded_at' => 'datetime',
    ];

    // Relationships
    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function klien()
    {
        return $this->belongsTo(Klien::class);
    }

    public function mitra()
    {
        return $this->belongsTo(Mitra::class);
    }

    public function responder()
    {
        return $this->belongsTo(User::class, 'responded_by');
    }

    // Scopes
    public function scopePublished($query)
    {
        return $query->where('is_published', true);
    }

    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }

    public function scopeByMitra($query, $mitraId)
    {
        return $query->where('mitra_id', $mitraId);
    }

    // Helpers
    public function calculateAverage(): void
    {
        $avg = ($this->rating_kualitas + $this->rating_profesionalisme + $this->rating_komunikasi) / 3;
        $this->update(['rating_average' => round($avg, 2)]);
    }

    // Observers can handle this better, but for now:
    protected static function boot()
    {
        parent::boot();

        static::saving(function ($feedback) {
            $feedback->rating_average = round(
                ($feedback->rating_kualitas + $feedback->rating_profesionalisme + $feedback->rating_komunikasi) / 3,
                2
            );
        });
    }
}
