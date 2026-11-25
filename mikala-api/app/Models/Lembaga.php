<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Lembaga extends Model {
    protected $table = 'lembaga';
    protected $fillable = [
        'nama','tipe','kontak_nama','kontak_hp','kontak_email',
        'alamat','kota','provinsi','fee_per_mitra','status','catatan','created_by',
        'rekening_bank','rekening_nomor','rekening_atas_nama',
    ];
    protected $casts = ['fee_per_mitra' => 'decimal:2'];

    // kode otomatis dari id (LMB-0001), tidak bisa diisi manual
    protected static function booted() {
        static::created(fn ($l) => $l->kode ?: $l->forceFill(['kode' => 'LMB-' . str_pad($l->id, 4, '0', STR_PAD_LEFT)])->saveQuietly());
    }

    public function mitra() {
        return $this->hasMany(Mitra::class, 'lembaga_id');
    }
    public function createdBy() {
        return $this->belongsTo(User::class, 'created_by');
    }
}
