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
        'user_id',
        'nik',
        'nama_lengkap',
        'tanggal_lahir',
        'jenis_kelamin',
        'alamat',
        'kota',
        'provinsi',
        'pendidikan_terakhir',
        'sertifikasi',
        'pengalaman',
        'ktp_file',
        'sertifikat_file',
        'cv_file',
        'status',
        'is_verified',
        'training_status',
        'training_score',
        'training_completed_at',
        'rating',
        'total_reviews',
        'total_jobs',
    ];

    protected $casts = [
        'tanggal_lahir' => 'date',
        'training_completed_at' => 'date',
        'is_verified' => 'boolean',
        'rating' => 'decimal:2',
    ];

    // Relationships
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    public function trainings()
    {
        return $this->hasMany(Training::class);
    }

    public function payrolls()
    {
        return $this->hasMany(Payroll::class);
    }

    public function feedback()
    {
        return $this->hasMany(Feedback::class);
    }

    // Scopes
    public function scopeAvailable($query)
    {
        return $query->where('status', 'available')->where('is_verified', true);
    }

    public function scopeOnJob($query)
    {
        return $query->where('status', 'on_job');
    }

    public function scopeInTraining($query)
    {
        return $query->where('status', 'training');
    }

    public function scopeVerified($query)
    {
        return $query->where('is_verified', true);
    }

    // Helpers
    public function updateRating()
    {
        $avgRating = $this->feedback()->avg('rating_average');
        $totalReviews = $this->feedback()->count();

        $this->update([
            'rating' => $avgRating ?? 0,
            'total_reviews' => $totalReviews,
        ]);
    }
}
