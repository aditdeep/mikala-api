<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Mitra extends Model
{
    use HasFactory, SoftDeletes;
    protected $table = 'mitra';

    // NIM (Nomor Induk Mitra): format {KodeTipePekerjaan}.{Bulan}.{Tahun2digit}-{urutan},
    // misal CG.03.26-001. Kode diambil dari field tipe_pekerjaan (lihat TIPE_KODE_MAP).
    const TIPE_KODE_MAP = [
        'Perawat Homecare'             => 'PHC',
        'Perawat Lansia / Caregiver'   => 'CG',
        'Babysitter'                   => 'BS',
        'Babysitter New Born Care'     => 'BNC',
        'Perawat Jiwa'                 => 'PJ',
        'Caregiver / Kaigo (Jepang)'   => 'PK',
        'Ke Jepang Lainnya'            => 'KJL',
    ];

    protected $fillable = [
        'user_id','nik','nomor_induk','nama_lengkap','tanggal_lahir','jenis_kelamin',
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
        // Data pribadi/fisik -- dulu di-encode di blob `pengalaman`, sekarang kolom asli
        'tempat_lahir','tinggi_badan','berat_badan','vaksin','agama','status_nikah',
        'takut_hewan','bisa_memasak','tipe_pekerjaan','suku','pengalaman_pelatihan',
        // FIX: data_tambahan (dipakai command migrasi:mitra-update utk nim_lama/status_lama/dst)
        // ketinggalan gak masuk fillable, jadi Mitra::create()/update() diam2 BUANG field ini --
        // akibatnya status_lama gak pernah kesimpan, --fix-status gak nemu apa2, dan idempotency
        // (skip baris yg udah diimpor) juga gak pernah jalan.
        'data_tambahan',
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

    // Generate NIM baru: {kode}.{bulan}.{tahun2digit}-{urutan berjalan bulan ini utk kode ini}.
    // $tipePekerjaan boleh salah satu value TIPE_KODE_MAP, atau null/lainnya -> pakai kode "MTR".
    public static function generateNim(?string $tipePekerjaan): string
    {
        $now = now();
        $kode = self::TIPE_KODE_MAP[$tipePekerjaan] ?? 'MTR';
        $prefix = $kode . '.' . $now->format('m') . '.' . $now->format('y');
        $count = self::where('nomor_induk', 'like', $prefix . '-%')->count() + 1;
        return $prefix . '-' . str_pad($count, 3, '0', STR_PAD_LEFT);
    }
}
