<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'password',
        'role',
        'profile_type',
        'profile_id',
        'status',
        'fcm_token',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    // Relationships
    public function profile()
    {
        if ($this->profile_type && $this->profile_id) {
            return $this->morphTo('profile', 'profile_type', 'profile_id');
        }
        return null;
    }

    public function mitra()
    {
        return $this->hasOne(Mitra::class);
    }

    public function klien()
    {
        return $this->hasOne(Klien::class);
    }

    public function agen()
    {
        return $this->hasOne(Agen::class);
    }

    public function notifikasi()
    {
        return $this->hasMany(Notifikasi::class);
    }

    public function unreadNotifikasi()
    {
        return $this->hasMany(Notifikasi::class)->whereRaw('is_read = false');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeByRole($query, $role)
    {
        return $query->where('role', $role);
    }

    public function scopeInternal($query)
    {
        return $query->whereIn('role', [
            'manajemen',
            'customer_care',
            'training_center',
            'rekrutmen',
            'finance',
            'marketing'
        ]);
    }

    // Helpers
    public function isInternal(): bool
    {
        return in_array($this->role, [
            'manajemen',
            'customer_care',
            'training_center',
            'rekrutmen',
            'finance',
            'marketing'
        ]);
    }

    public function isMitra(): bool
    {
        return $this->role === 'mitra';
    }

    public function isKlien(): bool
    {
        return $this->role === 'klien';
    }

    public function isAgen(): bool
    {
        return $this->role === 'agen';
    }
}
