<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Lembaga extends Model {
    protected $table = 'lembaga';
    protected $fillable = [
        'nama','tipe','kontak_nama','kontak_hp','kontak_email',
        'alamat','kota','provinsi','fee_per_mitra','status','catatan','created_by',
    ];
    protected $casts = ['fee_per_mitra' => 'decimal:2'];

    public function mitra() {
        return $this->hasMany(Mitra::class, 'lembaga_id');
    }
    public function createdBy() {
        return $this->belongsTo(User::class, 'created_by');
    }
}
