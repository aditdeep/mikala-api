<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MitraResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'name' => $this->nama_lengkap,
            'email' => $this->user->email ?? null,
            'phone' => $this->user->phone ?? null,
            'nik' => $this->nik,
            'birth_date' => $this->tanggal_lahir?->format('Y-m-d'),
            'age' => $this->tanggal_lahir ? now()->diffInYears($this->tanggal_lahir) : null,
            'gender' => $this->jenis_kelamin,
            'address' => $this->alamat,
            'city' => $this->kota,
            'province' => $this->provinsi,
            'education' => $this->pendidikan_terakhir,
            'certifications' => $this->sertifikasi,
            'experience' => $this->pengalaman,
            'status' => $this->status,
            'training_status' => $this->training_status,
            'training_score' => $this->training_score,
            'training_completed_at' => $this->training_completed_at?->format('Y-m-d'),
            'is_verified' => $this->is_verified,
            'rating' => round($this->rating, 2),
            'total_reviews' => $this->total_reviews,
            'total_jobs' => $this->total_jobs,
            'files' => [
                'ktp' => $this->ktp_file,
                'certificate' => $this->sertifikat_file,
                'cv' => $this->cv_file,
            ],
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
        ];
    }
}
