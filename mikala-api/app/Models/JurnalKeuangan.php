<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class JurnalKeuangan extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'jurnal_keuangan';

    protected $fillable = [
        'kode_transaksi',
        'tanggal',
        'tipe',
        'kategori',
        'jumlah',
        'deskripsi',
        'related_type',
        'related_id',
        'created_by',
        'approved_by',
        'approved_at',
        'bukti_file',
    ];

    protected $casts = [
        'tanggal' => 'date',
        'jumlah' => 'decimal:2',
        'approved_at' => 'datetime',
    ];

    // Relationships
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function related()
    {
        if ($this->related_type && $this->related_id) {
            return $this->morphTo('related', 'related_type', 'related_id');
        }
        return null;
    }

    // Scopes
    public function scopeDebit($query)
    {
        return $query->where('tipe', 'debit');
    }

    public function scopeKredit($query)
    {
        return $query->where('tipe', 'kredit');
    }

    public function scopeByKategori($query, $kategori)
    {
        return $query->where('kategori', $kategori);
    }

    public function scopePendapatan($query)
    {
        return $query->where('kategori', 'like', 'pendapatan_%');
    }

    public function scopeBiaya($query)
    {
        return $query->where('kategori', 'like', 'biaya_%');
    }

    public function scopeApproved($query)
    {
        return $query->whereNotNull('approved_at');
    }

    // Helpers
    public static function generateKodeTransaksi(): string
    {
        $date = now()->format('Ymd');
        $count = self::whereDate('created_at', today())->count() + 1;
        return "TRX-{$date}-" . str_pad($count, 4, '0', STR_PAD_LEFT);
    }

    public static function getSaldo($startDate = null, $endDate = null)
    {
        $query = self::query();
        
        if ($startDate) {
            $query->where('tanggal', '>=', $startDate);
        }
        if ($endDate) {
            $query->where('tanggal', '<=', $endDate);
        }

        $debit = $query->clone()->debit()->sum('jumlah');
        $kredit = $query->clone()->kredit()->sum('jumlah');

        return $debit - $kredit;
    }
}
