<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class MitraReferral extends Model {
    protected $table = 'mitra_referral';
    protected $fillable = [
        'mitra_id','sumber_tipe','sumber_detail','lembaga_id',
        'referrer_mitra_id','lead_source','fee_amount','fee_status',
        'fee_paid_at','fee_paid_by','catatan',
    ];
    protected $casts = ['fee_amount' => 'decimal:2', 'fee_paid_at' => 'datetime'];

    public function mitra()          { return $this->belongsTo(Mitra::class); }
    public function lembaga()        { return $this->belongsTo(Lembaga::class); }
    public function referrerMitra()  { return $this->belongsTo(Mitra::class, 'referrer_mitra_id'); }
    public function feeLog()         { return $this->hasMany(FeeLog::class, 'referral_id'); }
}
