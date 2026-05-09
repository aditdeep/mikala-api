<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class OrderRequest extends FormRequest
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
        return [
            // Required relationships
            'klien_id' => 'required|exists:klien,id',
            'pasien_id' => 'required|exists:pasien,id',
            'mitra_id' => 'nullable|exists:mitra,id',
            'agen_id' => 'nullable|exists:agen,id',
            
            // Service details
            'tipe_layanan' => 'required|in:perawat_lansia,perawat_medis,caregiver,perawat_stroke,baby_sitter,post_surgery',
            'deskripsi_layanan' => 'nullable|string|max:1000',
            
            // Schedule
            'tanggal_mulai' => 'required|date|after_or_equal:today',
            'tanggal_selesai' => 'required|date|after:tanggal_mulai',
            'jam_mulai' => 'nullable|date_format:H:i',
            'jam_selesai' => 'nullable|date_format:H:i|after:jam_mulai',
            'durasi_hari' => 'nullable|integer|min:1',
            
            // Pricing
            'harga_per_hari' => 'required|numeric|min:0',
            'subtotal' => 'nullable|numeric|min:0',
            'pajak' => 'nullable|numeric|min:0',
            'diskon' => 'nullable|numeric|min:0',
            'total' => 'nullable|numeric|min:0',
            
            // Status & notes
            'status' => 'sometimes|in:pending,confirmed,in_progress,completed,cancelled',
            'catatan' => 'nullable|string|max:1000',
            'cancel_reason' => 'nullable|string|max:500',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'klien_id.exists' => 'Selected client not found',
            'pasien_id.exists' => 'Selected patient not found',
            'mitra_id.exists' => 'Selected mitra not found',
            'tanggal_mulai.after_or_equal' => 'Start date cannot be in the past',
            'tanggal_selesai.after' => 'End date must be after start date',
            'jam_selesai.after' => 'End time must be after start time',
            'harga_per_hari.min' => 'Price must be a positive number',
        ];
    }

    /**
     * Prepare data for validation
     */
    protected function prepareForValidation()
    {
        // Calculate durasi_hari if not provided
        if ($this->has('tanggal_mulai') && $this->has('tanggal_selesai')) {
            $start = \Carbon\Carbon::parse($this->tanggal_mulai);
            $end = \Carbon\Carbon::parse($this->tanggal_selesai);
            $this->merge([
                'durasi_hari' => $end->diffInDays($start) + 1
            ]);
        }

        // Calculate pricing if not provided
        if ($this->has('harga_per_hari') && $this->has('durasi_hari')) {
            $subtotal = $this->harga_per_hari * $this->durasi_hari;
            $pajak = $this->pajak ?? ($subtotal * 0.11); // Default 11% tax
            $diskon = $this->diskon ?? 0;
            $total = $subtotal + $pajak - $diskon;

            $this->merge([
                'subtotal' => $subtotal,
                'pajak' => $pajak,
                'total' => $total
            ]);
        }
    }
}
