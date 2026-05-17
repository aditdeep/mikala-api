<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class CmsGaleri extends Model {
    protected $table = 'cms_galeri';
    protected $fillable = ['judul','url','thumbnail','kategori','deskripsi'];
}
