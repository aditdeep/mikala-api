<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
class CmsArtikel extends Model {
    use SoftDeletes;
    protected $table = 'cms_artikel';
    protected $casts = ['published_at' => 'datetime'];

    protected $fillable = ['judul','slug','konten','excerpt','thumbnail','thumbnail_caption','kategori','status','published_at','author_id','meta_title','meta_description','tags','views'];
}
