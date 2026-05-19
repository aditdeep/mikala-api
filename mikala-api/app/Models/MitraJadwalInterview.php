<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class MitraJadwalInterview extends Model {
    protected $table = 'mitra_jadwal_interview';
    protected $fillable = ['mitra_id','jadwal_at','lokasi','link_online','tipe','status','catatan','interviewer_id','done_at'];
    protected $casts = ['jadwal_at'=>'datetime','done_at'=>'datetime'];
    public function mitra() { return $this->belongsTo(Mitra::class); }
    public function interviewer() { return $this->belongsTo(User::class, 'interviewer_id'); }
}
