<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LeadExchange extends Model
{
    use HasFactory;

    protected $table = 'leads_exchange';

    protected $fillable = [
        'nomor',
        'lead_id',
        'mitra_lama_id',
        'mitra_baru_id',
        'alasan',
        'exchanged_at',
        'created_by',
    ];

    protected $casts = [
        'exchanged_at' => 'datetime',
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
}
