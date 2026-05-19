<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MitraKreditPelatihan extends Model {
    protected $table = 'mitra_kredit_pelatihan';
    protected $fillable = ['mitra_id','total_biaya','total_terbayar','sisa_tagihan',
        'cicilan_per_job','status','keterangan','lunas_at','created_by'];
    protected $casts = ['total_biaya'=>'decimal:2','total_terbayar'=>'decimal:2',
        'sisa_tagihan'=>'decimal:2','cicilan_per_job'=>'decimal:2','lunas_at'=>'datetime'];

    public function mitra() { return $this->belongsTo(Mitra::class); }
    public function potongan() { return $this->hasMany(MitraKreditPotongan::class, 'kredit_id'); }

    public function prosesPotongan(int $orderId): ?MitraKreditPotongan {
        if ($this->status !== 'active' || $this->sisa_tagihan <= 0) return null;
        $jumlah = min($this->cicilan_per_job, $this->sisa_tagihan);
        $sisa = $this->sisa_tagihan - $jumlah;
        $potongan = $this->potongan()->create([
            'mitra_id' => $this->mitra_id, 'order_id' => $orderId,
            'jumlah_potongan' => $jumlah, 'sisa_setelah_potong' => $sisa,
        ]);
        $this->total_terbayar += $jumlah;
        $this->sisa_tagihan = $sisa;
        if ($sisa <= 0) { $this->status = 'lunas'; $this->lunas_at = now(); }
        $this->save();
        return $potongan;
    }
}
