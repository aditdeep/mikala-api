<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Order extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'order_number',
        'klien_id',
        'pasien_id',
        'mitra_id',
        'agen_id',
        'tipe_layanan',
        'deskripsi_layanan',
        'tanggal_mulai',
        'tanggal_selesai',
        'durasi_hari',
        'jam_mulai',
        'jam_selesai',
        'harga_per_hari',
        'subtotal',
        'pajak',
        'diskon',
        'total',
        'status',
        'catatan',
        'cancel_reason',
        'confirmed_at',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'tanggal_mulai' => 'date',
        'tanggal_selesai' => 'date',
        'harga_per_hari' => 'decimal:2',
        'subtotal' => 'decimal:2',
        'pajak' => 'decimal:2',
        'diskon' => 'decimal:2',
        'total' => 'decimal:2',
        'confirmed_at' => 'datetime',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    // Relationships
    public function klien()
    {
        return $this->belongsTo(Klien::class);
    }

    public function pasien()
    {
        return $this->belongsTo(Pasien::class);
    }

    public function mitra()
    {
        return $this->belongsTo(Mitra::class);
    }

    public function agen()
    {
        return $this->belongsTo(Agen::class);
    }

    public function tagihan()
    {
        return $this->hasMany(Tagihan::class);
    }

    public function feedback()
    {
        return $this->hasOne(Feedback::class);
    }

    // Scopes
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeInProgress($query)
    {
        return $query->where('status', 'in_progress');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeByMitra($query, $mitraId)
    {
        return $query->where('mitra_id', $mitraId);
    }

    public function scopeByKlien($query, $klienId)
    {
        return $query->where('klien_id', $klienId);
    }

    // Helpers
    public static function generateOrderNumber(): string
    {
        $date = now()->format('Ymd');
        $count = self::whereDate('created_at', today())->count() + 1;
        return "ORD-{$date}-" . str_pad($count, 4, '0', STR_PAD_LEFT);
    }
}
