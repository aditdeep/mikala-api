<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Klien extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'klien';

    protected $fillable = [
        'user_id',
        'nama_lengkap',
        'tipe',
        'nik',
        'nama_perusahaan',
        'npwp',
        'alamat',
        'kota',
        'provinsi',
        'phone_secondary',
        'billing_method',
        'bank_name',
        'bank_account',
        'bank_account_name',
        'status',
        'is_verified',
        'total_pasien',
        'total_orders',
        'total_tagihan',
    ];

    protected $casts = [
        'is_verified' => 'boolean',
        'total_tagihan' => 'decimal:2',
    ];

    // Relationships
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function pasien()
    {
        return $this->hasMany(Pasien::class);
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    public function tagihan()
    {
        return $this->hasMany(Tagihan::class);
    }

    public function feedback()
    {
        return $this->hasMany(Feedback::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeVerified($query)
    {
        return $query->whereRaw('is_verified = true');
    }

    public function scopeIndividu($query)
    {
        return $query->where('tipe', 'individu');
    }

    public function scopeInstitusi($query)
    {
        return $query->whereIn('tipe', ['rumah_sakit', 'panti_jompo', 'klinik']);
    }

    // Helpers
    public function updateStats()
    {
        $this->update([
            'total_pasien' => $this->pasien()->count(),
            'total_orders' => $this->orders()->count(),
            'total_tagihan' => $this->tagihan()->sum('total'),
        ]);
    }
}
