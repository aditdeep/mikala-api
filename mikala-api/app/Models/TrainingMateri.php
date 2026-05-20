<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class TrainingMateri extends Model {
    protected $table = 'training_materi';
    protected $fillable = ['kode','nama','kategori','parent_kode','urutan','is_active'];
    protected $casts = ['is_active'=>'boolean'];
    public function checklists() { return $this->hasMany(TrainingChecklist::class,'materi_id'); }
}
