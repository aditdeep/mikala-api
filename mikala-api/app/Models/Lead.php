<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Lead extends Model
{
    use HasFactory, SoftDeletes;

    // Catatan: tabel dinamai 'cc_leads' (bukan 'leads') karena nama 'leads' sudah dipakai
    // oleh tabel marketing-leads lama (App\Models\Leads, lihat MarketingController/MGMController).
    protected $table = 'cc_leads';

    // status: 0 = baru/proses, 1 = deal, 2 = batal (loss), 3 = gantung (on-hold)
    const STATUS_PROSES = 0;
    const STATUS_DEAL = 1;
    const STATUS_BATAL = 2;
    const STATUS_GANTUNG = 3;

    protected $fillable = [
        'nomor',
        'nomor_kontrak_klien',
        'nomor_kontrak_mitra',
        'nomor_kontrak_klien_mitra',
        'nik',
        'cms_layanan_id',
        'tier_nama',
        'klien_id',
        // Cust/PJ = penanggung jawab pasien (yang bayar/kontak)
        'nama_leads',
        'kontak',
        'no_rumah',
        'alamat_cust_pj',
        'no_ktp_cust_pj',
        'hubungan_dengan_pasien',
        'email_cust_pj',
        // Klien = pasien (istilah baru; nama_pasien tetap dipakai sebagai nama kolom)
        'nama_pasien',
        'alamat_klien',
        'alamat_klien_2',
        'tanggal_lahir_klien',
        'no_wa_klien',
        'tinggi_badan',
        'berat_badan',
        'jenis_kelamin_klien',
        'diagnosis_awal',
        'deskripsi_diagnosa',
        'alat_pendukung',
        'alat_medis',
        // Referensi
        'sumber',
        'referensi_tipe',
        'referensi_sub',
        'referensi_klien_id',
        'referensi_mitra_id',
        'nama_referensi',
        'kontak_referensi',
        'catatan',
        'mitra_id',
        'mitra_nim',
        'biaya_admin',
        'honor_mitra',
        'uang_cuti_mitra',
        'biaya_transport',
        'status',
        'alasan_batal',
        'alasan_status',
        'catatan_revisi_kontrak',
        // Field tahap Deal
        'kesadaran',
        'komunikasi',
        'kelemahan',
        'mobilisasi',
        'jasa_diminta',
        'jasa_disarankan',
        'jasa_disetujui',
        'pembantu',
        'cara_mencuci_baju',
        'deal_at',
        'batal_at',
        'created_by',
    ];

    protected $casts = [
        'status' => 'integer',
        'deal_at' => 'datetime',
        'batal_at' => 'datetime',
        'tanggal_lahir_klien' => 'date',
        'biaya_admin' => 'decimal:2',
        'honor_mitra' => 'decimal:2',
        'uang_cuti_mitra' => 'decimal:2',
        'biaya_transport' => 'decimal:2',
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

    public function referensiKlien()
    {
        return $this->belongsTo(Klien::class, 'referensi_klien_id');
    }

    public function referensiMitra()
    {
        return $this->belongsTo(Mitra::class, 'referensi_mitra_id');
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

    public function scopeGantung($query)
    {
        return $query->where('status', self::STATUS_GANTUNG);
    }

    // No. Order, dibuat sekali saat intake, format: T-LN.MGM.01.00001
    public static function generateNomor(): string
    {
        $count = self::count() + 1;
        return 'T-LN.MGM.01.' . str_pad($count, 5, '0', STR_PAD_LEFT);
    }

    // NIK (Nomor Induk Klien), dibuat saat lead ditandai Deal. Format: V1.DD.MM.YY-001
    public static function generateNik(): string
    {
        $now = now();
        $prefix = 'V1.' . str_pad($now->day, 2, '0', STR_PAD_LEFT) . '.' . str_pad($now->month, 2, '0', STR_PAD_LEFT) . '.' . $now->format('y');
        $count = self::whereYear('created_at', $now->year)->whereMonth('created_at', $now->month)->whereDay('created_at', $now->day)->count() + 1;
        return $prefix . '-' . str_pad($count, 3, '0', STR_PAD_LEFT);
    }

    // Nomor Kontrak MGM-Klien (Kontrak 1.1/1.2), dibuat sekali saat kontrak pertama kali
    // di-generate/download. Format: {urut}/MGM/KLIEN/{bulan romawi}/{tahun}, sesuai dok kontrak.
    public static function generateNomorKontrakKlien(): string
    {
        $now = now();
        $romawi = ['','I','II','III','IV','V','VI','VII','VIII','IX','X','XI','XII'];
        $count = self::whereNotNull('nomor_kontrak_klien')->count() + 1;
        return str_pad($count, 3, '0', STR_PAD_LEFT) . '/MGM/KLIEN/' . $romawi[(int)$now->format('n')] . '/' . $now->format('Y');
    }

    // Nomor Kontrak 2 (Perjanjian Penempatan MGM-Mitra). Format: {urut}/MGM/KK/{bulan romawi}/{tahun}.
    public static function generateNomorKontrakMitra(): string
    {
        $now = now();
        $romawi = ['','I','II','III','IV','V','VI','VII','VIII','IX','X','XI','XII'];
        $count = self::whereNotNull('nomor_kontrak_mitra')->count() + 1;
        return str_pad($count, 3, '0', STR_PAD_LEFT) . '/MGM/KK/' . $romawi[(int)$now->format('n')] . '/' . $now->format('Y');
    }

    // Nomor Kontrak 3 (Perjanjian Kerja Mitra-Klien). Format: {urut}/MGM/{layanan disetujui}-KLIEN/{bulan romawi}/{tahun}.
    public static function generateNomorKontrakKlienMitra(string $layananSegment = 'LAYANAN'): string
    {
        $now = now();
        $romawi = ['','I','II','III','IV','V','VI','VII','VIII','IX','X','XI','XII'];
        $count = self::whereNotNull('nomor_kontrak_klien_mitra')->count() + 1;
        $segment = strtoupper(preg_replace('/[^A-Za-z0-9]+/', '-', trim($layananSegment))) ?: 'LAYANAN';
        return str_pad($count, 3, '0', STR_PAD_LEFT) . '/MGM/' . $segment . '-KLIEN/' . $romawi[(int)$now->format('n')] . '/' . $now->format('Y');
    }
}
