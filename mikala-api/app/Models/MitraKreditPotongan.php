<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class MitraKreditPotongan extends Model {
    protected $table = 'mitra_kredit_potongan';
    protected $fillable = ['kredit_id','mitra_id','order_id','jumlah_potongan','sisa_setelah_potong','keterangan'];
    public function kredit() { return $this->belongsTo(MitraKreditPelatihan::class, 'kredit_id'); }
    public function order() { return $this->belongsTo(Order::class); }
}
