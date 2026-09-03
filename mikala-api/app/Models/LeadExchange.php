<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LeadExchange extends Model
{
    use HasFactory;

    protected $table = 'cc_leads_exchange';

    protected $fillable = [
        'nomor',
        'nomor_adendum',
        'lead_id',
        'mitra_lama_id',
        'mitra_baru_id',
        'alasan',
        'biaya_jasa_lama',
        'biaya_jasa_baru',
        'uang_cuti_lama',
        'uang_cuti_baru',
        'surat_tugas_lama',
        'surat_tugas_baru',
        'biaya_transport',
        'exchanged_at',
        'created_by',
    ];

    protected $casts = [
        'exchanged_at'    => 'datetime',
        'biaya_jasa_lama' => 'decimal:2',
        'biaya_jasa_baru' => 'decimal:2',
        'uang_cuti_lama'  => 'decimal:2',
        'uang_cuti_baru'  => 'decimal:2',
        'biaya_transport' => 'decimal:2',
    ];

    public function lead()
    {
        return $this->belongsTo(Lead::class, 'lead_id');
    }

    public function mitraLama()
    {
        return $this->belongsTo(Mitra::class, 'mitra_lama_id');
    }

    public function mitraBaru()
    {
        return $this->belongsTo(Mitra::class, 'mitra_baru_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // NIM format sesuai dokumen: V2.{kategori 2 huruf}.MM.YY-001, contoh V2.CG.03.26-001
    public static function generateNomor(string $kategori = 'XX'): string
    {
        $now = now();
        $kategori = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $kategori), 0, 2)) ?: 'XX';
        $prefix = 'V2.' . $kategori . '.' . str_pad($now->month, 2, '0', STR_PAD_LEFT) . '.' . $now->format('y');
        $count = self::where('nomor', 'like', 'V2.' . $kategori . '.%')->count() + 1;
        return $prefix . '-' . str_pad($count, 3, '0', STR_PAD_LEFT);
    }

    // Nomor Adendum Exchange, sesuai dok "Adendum - Exchange.docx". Format: {urut}/MGM/ST/{bulan romawi}/{tahun}.
    public static function generateNomorAdendum(): string
    {
        $now = now();
        $romawi = ['','I','II','III','IV','V','VI','VII','VIII','IX','X','XI','XII'];
        $count = self::whereNotNull('nomor_adendum')->count() + 1;
        return str_pad($count, 3, '0', STR_PAD_LEFT) . '/MGM/ST/' . $romawi[(int)$now->format('n')] . '/' . $now->format('Y');
    }
}
