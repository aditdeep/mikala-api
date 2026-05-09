<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MitraRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $mitraId = $this->route('id') ?? $this->route('mitra');
        $userId = null;

        // Get user_id if updating existing mitra
        if ($mitraId) {
            $mitra = \App\Models\Mitra::find($mitraId);
            $userId = $mitra->user_id ?? null;
        }

        return [
            // User data
            'user.name' => 'sometimes|required|string|max:255',
            'user.email' => 'sometimes|required|email|unique:users,email,' . ($userId ?? 'NULL'),
            'user.password' => $this->isMethod('POST') ? 'required|min:8' : 'sometimes|min:8',
            'user.phone' => 'sometimes|required|string|max:20',
            
            // Mitra personal data
            'nama_lengkap' => 'required|string|max:255',
            'nik' => 'required|string|size:16|unique:mitra,nik,' . ($mitraId ?? 'NULL'),
            'tanggal_lahir' => 'required|date|before:today',
            'jenis_kelamin' => 'required|in:male,female',
            'alamat' => 'required|string|max:500',
            'kota' => 'required|string|max:100',
            'provinsi' => 'nullable|string|max:100',
            
            // Professional data
            'pendidikan_terakhir' => 'required|string|max:100',
            'sertifikasi' => 'nullable|string|max:500',
            'pengalaman' => 'nullable|string|max:1000',
            
            // Files
            'ktp_file' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:2048',
            'sertifikat_file' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:2048',
            'cv_file' => 'nullable|file|mimes:pdf,doc,docx|max:2048',
            
            // Status
            'status' => 'sometimes|in:pending,available,on_job,training,inactive,rejected',
            'training_status' => 'sometimes|in:pending,on_training,completed,failed',
            'is_verified' => 'sometimes|boolean',
            'training_score' => 'nullable|numeric|min:0|max:100',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'nik.size' => 'NIK must be exactly 16 digits',
            'nik.unique' => 'NIK already registered',
            'user.email.unique' => 'Email already registered',
            'tanggal_lahir.before' => 'Birth date must be in the past',
            'ktp_file.max' => 'KTP file size must not exceed 2MB',
            'sertifikat_file.max' => 'Certificate file size must not exceed 2MB',
            'cv_file.max' => 'CV file size must not exceed 2MB',
        ];
    }
}
