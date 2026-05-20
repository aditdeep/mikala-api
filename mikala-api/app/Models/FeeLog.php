<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class FeeLog extends Model {
    protected $table = 'fee_log';
    protected $fillable = [
        'referral_id','penerima_tipe','penerima_id',
        'jumlah','status','keterangan','paid_at','paid_by',
    ];
    protected $casts = ['jumlah' => 'decimal:2', 'paid_at' => 'datetime'];

    public function referral() { return $this->belongsTo(MitraReferral::class, 'referral_id'); }
}
