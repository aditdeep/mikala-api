<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class KlienRequest extends FormRequest
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
        $klienId = $this->route('id') ?? $this->route('klien');
        $userId = null;

        // Get user_id if updating existing klien
        if ($klienId) {
            $klien = \App\Models\Klien::find($klienId);
            $userId = $klien->user_id ?? null;
        }

        return [
            // User data
            'user.name' => 'sometimes|required|string|max:255',
            'user.email' => 'sometimes|required|email|unique:users,email,' . ($userId ?? 'NULL'),
            'user.password' => $this->isMethod('POST') ? 'required|min:8' : 'sometimes|min:8',
            'user.phone' => 'sometimes|required|string|max:20',
            
            // Klien data
            'nama_lengkap' => 'required|string|max:255',
            'tipe' => 'required|in:individu,rumah_sakit,panti_jompo,klinik',
            'nik' => 'required_if:tipe,individu|nullable|string|size:16|unique:klien,nik,' . ($klienId ?? 'NULL'),
            'nama_perusahaan' => 'required_unless:tipe,individu|nullable|string|max:255',
            'npwp' => 'nullable|string|max:20',
            'alamat' => 'required|string|max:500',
            'kota' => 'required|string|max:100',
            'provinsi' => 'nullable|string|max:100',
            'phone_secondary' => 'nullable|string|max:20',
            
            // Billing info
            'billing_method' => 'nullable|in:transfer,cash,credit_card,tempo',
            'bank_name' => 'nullable|string|max:100',
            'bank_account' => 'nullable|string|max:50',
            'bank_account_name' => 'nullable|string|max:255',
            
            // Status
            'status' => 'sometimes|in:active,inactive,suspended',
            'is_verified' => 'sometimes|boolean',
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
            'nik.required_if' => 'NIK is required for individual clients',
            'nama_perusahaan.required_unless' => 'Company name is required for institutional clients',
            'user.email.unique' => 'Email already registered',
        ];
    }
}
