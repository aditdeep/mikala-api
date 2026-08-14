<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Lead extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'leads';

    // status: 0 = baru/proses, 1 = deal, 2 = batal (loss)
    const STATUS_PROSES = 0;
    const STATUS_DEAL = 1;
    const STATUS_BATAL = 2;

    protected $fillable = [
        'nomor',
        'cms_layanan_id',
        'tier_nama',
        'klien_id',
        // Cust/PJ = penanggung jawab pasien (yang bayar/kontak)
        'nama_leads',
        'kontak',
        'alamat_cust_pj',
        // Klien = pasien (istilah baru; nama_pasien tetap dipakai sebagai nama kolom)
        'nama_pasien',
        'alamat_klien',
        'diagnosis_awal',
        'alat_pendukung',
        'sumber',
        'catatan',
        'mitra_id',
        'status',
        'alasan_batal',
        'deal_at',
        'batal_at',
        'created_by',
    ];

    protected $casts = [
        'status' => 'integer',
        'deal_at' => 'datetime',
        'batal_at' => 'datetime',
    ];

    public function layanan()
    {
        return $this->belongsTo(CmsLayanan::class, 'cms_layanan_id');
    }

    public function klien()
    {
        return $this->belongsTo(Klien::class, 'klien_id');
    }

    public function mitra()
    {
        return $this->belongsTo(Mitra::class, 'mitra_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function exchanges()
    {
        return $this->hasMany(LeadExchange::class, 'lead_id');
    }

    public function scopeDeal($query)
    {
        return $query->where('status', self::STATUS_DEAL);
    }

    public function scopeBatal($query)
    {
        return $query->where('status', self::STATUS_BATAL);
    }

    public function scopeProses($query)
    {
        return $query->where('status', self::STATUS_PROSES);
    }

    // NIK format sesuai dokumen: V1.DD.MM.YY-001 (V1 = Leads/Deal)
    public static function generateNomor(): string
    {
        $now = now();
        $prefix = 'V1.' . str_pad($now->day, 2, '0', STR_PAD_LEFT) . '.' . str_pad($now->month, 2, '0', STR_PAD_LEFT) . '.' . $now->format('y');
        $count = self::whereYear('created_at', $now->year)->whereMonth('created_at', $now->month)->whereDay('created_at', $now->day)->count() + 1;
        return $prefix . '-' . str_pad($count, 3, '0', STR_PAD_LEFT);
    }
}
