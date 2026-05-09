<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Pasien extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'pasien';

    protected $fillable = [
        'klien_id',
        'nama_lengkap',
        'nik',
        'tanggal_lahir',
        'jenis_kelamin',
        'alamat',
        'riwayat_penyakit',
        'alergi',
        'golongan_darah',
        'obat_rutin',
        'catatan_khusus',
        'kontak_darurat_nama',
        'kontak_darurat_phone',
        'kontak_darurat_relasi',
        'status',
    ];

    protected $casts = [
        'tanggal_lahir' => 'date',
    ];

    // Relationships
    public function klien()
    {
        return $this->belongsTo(Klien::class);
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    // Helpers
    public function getAge(): int
    {
        return $this->tanggal_lahir->age;
    }
}
