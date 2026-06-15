<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Cuti extends Model
{
    use HasFactory;

    protected $table = 'cuti_mitra';

    protected $fillable = [
        'mitra_id', 'tanggal_mulai', 'tanggal_selesai', 'jumlah_hari',
        'alasan', 'status', 'approved_by', 'approved_at', 'catatan_admin',
    ];

    protected $casts = [
        'tanggal_mulai'   => 'date',
        'tanggal_selesai' => 'date',
        'approved_at'     => 'datetime',
    ];

    public function mitra()    { return $this->belongsTo(Mitra::class); }
    public function approver() { return $this->belongsTo(User::class, 'approved_by'); }
}
