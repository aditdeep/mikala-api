<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Leads extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'leads';

    protected $fillable = [
        'nama',
        'email',
        'phone',
        'kota',
        'source',
        'tipe_layanan',
        'pesan',
        'status',
        'notes',
        'assigned_to',
        'assigned_at',
        'converted_to_klien_id',
        'converted_at',
    ];

    protected $casts = [
        'assigned_at' => 'datetime',
        'converted_at' => 'datetime',
    ];

    // Relationships
    public function assignedUser()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function convertedKlien()
    {
        return $this->belongsTo(Klien::class, 'converted_to_klien_id');
    }

    // Scopes
    public function scopeNew($query)
    {
        return $query->where('status', 'new');
    }

    public function scopeAssigned($query)
    {
        return $query->whereNotNull('assigned_to');
    }

    public function scopeUnassigned($query)
    {
        return $query->whereNull('assigned_to');
    }

    public function scopeDeal($query)
    {
        return $query->where('status', 'deal');
    }

    public function scopeLost($query)
    {
        return $query->where('status', 'lost');
    }

    public function scopeBySource($query, $source)
    {
        return $query->where('source', $source);
    }

    // Helpers
    public function assignTo(User $user): void
    {
        $this->update([
            'assigned_to' => $user->id,
            'assigned_at' => now(),
            'status' => 'contacted',
        ]);
    }

    public function convertToKlien(Klien $klien): void
    {
        $this->update([
            'converted_to_klien_id' => $klien->id,
            'converted_at' => now(),
            'status' => 'deal',
        ]);
    }
}
