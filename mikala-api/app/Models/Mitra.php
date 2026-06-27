<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Mitra extends Model
{
    use HasFactory, SoftDeletes;
    protected $table = 'mitra';

    protected $fillable = [
        'user_id','nik','nama_lengkap','tanggal_lahir','jenis_kelamin',
        'alamat','kota','provinsi','pendidikan_terakhir','sertifikasi','pengalaman',
        'foto_url','cv_file','bank_name','bank_account','bank_account_name',
        'ktp_file','sertifikat_file','status','is_verified',
        'training_status','training_score','training_completed_at',
        'rating','total_reviews','total_jobs',
        // Rekrutmen
        'payment_type','contract_agreed_at','status_rekrutmen',
        'price_rate','jabatan','gaji_bulanan','catatan_rekrutmen','verified_at','verified_by',
        // Referral / Sumber
        'sumber_tipe','sumber_detail','lembaga_id','referrer_mitra_id',
    ];

    protected $casts = [
        'tanggal_lahir'         => 'date',
        'training_completed_at' => 'date',
        'is_verified'           => 'boolean',
        'rating'                => 'decimal:2',
        'contract_agreed_at'    => 'datetime',
        'verified_at'           => 'datetime',
    ];

    public function user()           { return $this->belongsTo(User::class); }
    public function orders()         { return $this->hasMany(Order::class); }
    public function trainings()      { return $this->hasMany(Training::class); }
    public function payrolls()       { return $this->hasMany(Payroll::class); }
    public function feedback()       { return $this->hasMany(Feedback::class); }
    public function kreditPelatihan(){ return $this->hasOne(MitraKreditPelatihan::class); }
    public function jadwalInterview(){ return $this->hasMany(MitraJadwalInterview::class); }
    public function referral()       { return $this->hasOne(MitraReferral::class); }
    public function lembaga()        { return $this->belongsTo(Lembaga::class); }
    public function referrerMitra()  { return $this->belongsTo(Mitra::class, 'referrer_mitra_id'); }
    public function referredMitra()  { return $this->hasMany(Mitra::class, 'referrer_mitra_id'); }
    public function feeLog()         { return $this->hasManyThrough(FeeLog::class, MitraReferral::class, 'referrer_mitra_id', 'referral_id', 'id', 'id'); }

    public function scopeAvailable($q) { return $q->where('status','available')->whereRaw('is_verified = true'); }
    public function scopeOnJob($q)     { return $q->where('status','on_job'); }
    public function scopeVerified($q)  { return $q->whereRaw('is_verified = true'); }

    public function updateRating() {
        $this->update([
            'rating'        => $this->feedback()->avg('rating_average') ?? 0,
            'total_reviews' => $this->feedback()->count(),
        ]);
    }
}
