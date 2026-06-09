<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class TrainingChecklist extends Model {
    protected $table = 'training_checklist';
    protected $fillable = ['mitra_id','materi_id','tanggal_dapat','pengajar','checked_by','checked_at','catatan','rating'];
    protected $casts = ['tanggal_dapat'=>'date','checked_at'=>'datetime'];
    public function mitra()   { return $this->belongsTo(Mitra::class); }
    public function materi()  { return $this->belongsTo(TrainingMateri::class,'materi_id'); }
    public function checker() { return $this->belongsTo(User::class,'checked_by'); }
}
