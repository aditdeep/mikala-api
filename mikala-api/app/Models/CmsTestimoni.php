<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class CmsTestimoni extends Model {
    protected $table = 'cms_testimoni';
    protected $fillable = ['nama','layanan','rating','komentar','foto','status'];
}
