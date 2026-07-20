<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class CmsLayanan extends Model {
    protected $table = 'cms_layanan';
    protected $fillable = ['nama','deskripsi','deskripsi_panjang','manfaat','icon','gambar','urutan','wa_link','is_active','meta_title','meta_description'];
}
