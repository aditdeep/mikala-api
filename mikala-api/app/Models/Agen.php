<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Agen extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'agen';

    protected $fillable = [
        'user_id',
        'nama_institusi',
        'tipe_institusi',
        'npwp',
        'contact_person_name',
        'contact_person_jabatan',
        'contact_person_phone',
        'contact_person_email',
        'alamat',
        'kota',
        'provinsi',
        'komisi_persen',
        'notes',
        'status',
        'total_referrals',
        'total_komisi',
    ];

    protected $casts = [
        'komisi_persen' => 'decimal:2',
        'total_komisi' => 'decimal:2',
    ];

    // Relationships
    public function user()
    {
        return $this->belongsTo(User::class);
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
    public function calculateKomisi($orderTotal)
    {
        return ($orderTotal * $this->komisi_persen) / 100;
    }
}
