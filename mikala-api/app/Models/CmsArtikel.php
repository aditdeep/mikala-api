<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
class CmsArtikel extends Model {
    use SoftDeletes;
    protected $table = 'cms_artikel';
    protected $fillable = ['judul','slug','konten','excerpt','thumbnail','kategori','status','author_id','meta_title','meta_description','tags','views'];
}
