<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class CmsPenunjang extends Model {
    protected $table = 'cms_penunjang';
    protected $fillable = ['nama','tipe','deskripsi','gambar','urutan','wa_link','is_active'];
    protected $casts = ['is_active' => 'boolean', 'urutan' => 'integer'];
}
