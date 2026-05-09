<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class FeedbackRequest extends FormRequest
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
            'order_id' => 'required|exists:orders,id',
            'klien_id' => 'required|exists:klien,id',
            'mitra_id' => 'required|exists:mitra,id',
            
            // Ratings (1-5 scale)
            'rating_kualitas' => 'required|integer|min:1|max:5',
            'rating_profesionalisme' => 'required|integer|min:1|max:5',
            'rating_komunikasi' => 'required|integer|min:1|max:5',
            
            // Comments
            'komentar' => 'nullable|string|max:1000',
            'saran' => 'nullable|string|max:1000',
            
            // Admin fields
            'is_published' => 'sometimes|boolean',
            'is_featured' => 'sometimes|boolean',
            'response' => 'nullable|string|max:500',
            'responded_by' => 'nullable|exists:users,id',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'order_id.exists' => 'Selected order not found',
            'klien_id.exists' => 'Selected client not found',
            'mitra_id.exists' => 'Selected mitra not found',
            'rating_kualitas.min' => 'Quality rating must be between 1 and 5',
            'rating_kualitas.max' => 'Quality rating must be between 1 and 5',
            'rating_profesionalisme.min' => 'Professionalism rating must be between 1 and 5',
            'rating_profesionalisme.max' => 'Professionalism rating must be between 1 and 5',
            'rating_komunikasi.min' => 'Communication rating must be between 1 and 5',
            'rating_komunikasi.max' => 'Communication rating must be between 1 and 5',
            'komentar.max' => 'Comment cannot exceed 1000 characters',
            'saran.max' => 'Suggestion cannot exceed 1000 characters',
        ];
    }

    /**
     * Prepare data for validation
     */
    protected function prepareForValidation()
    {
        // Calculate average rating
        if ($this->has('rating_kualitas') && $this->has('rating_profesionalisme') && $this->has('rating_komunikasi')) {
            $average = ($this->rating_kualitas + $this->rating_profesionalisme + $this->rating_komunikasi) / 3;
            $this->merge([
                'rating_average' => round($average, 2)
            ]);
        }
    }
}
