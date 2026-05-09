<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BillingRequest extends FormRequest
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
            'order_id' => 'required|exists:orders,id',
            
            // Invoice details
            'invoice_number' => 'sometimes|string|max:50|unique:tagihan,invoice_number,' . ($this->route('id') ?? 'NULL'),
            'tanggal_invoice' => 'required|date',
            'tanggal_jatuh_tempo' => 'required|date|after:tanggal_invoice',
            
            // Amounts
            'subtotal' => 'required|numeric|min:0',
            'pajak' => 'nullable|numeric|min:0',
            'diskon' => 'nullable|numeric|min:0',
            'total' => 'required|numeric|min:0',
            'jumlah_bayar' => 'nullable|numeric|min:0|max:total',
            'sisa' => 'nullable|numeric|min:0',
            
            // Payment info
            'status' => 'sometimes|in:unpaid,paid,overdue,partial',
            'metode_pembayaran' => 'nullable|in:transfer,cash,credit_card,ewallet',
            'bukti_transfer' => 'nullable|string|max:255',
            'catatan' => 'nullable|string|max:500',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'klien_id.exists' => 'Selected client not found',
            'order_id.exists' => 'Selected order not found',
            'tanggal_jatuh_tempo.after' => 'Due date must be after invoice date',
            'total.min' => 'Total amount must be a positive number',
            'jumlah_bayar.max' => 'Payment amount cannot exceed total amount',
        ];
    }

    /**
     * Prepare data for validation
     */
    protected function prepareForValidation()
    {
        // Calculate total if not provided
        if ($this->has('subtotal')) {
            $subtotal = $this->subtotal;
            $pajak = $this->pajak ?? 0;
            $diskon = $this->diskon ?? 0;
            $total = $subtotal + $pajak - $diskon;

            $this->merge([
                'total' => $total,
                'sisa' => $total - ($this->jumlah_bayar ?? 0)
            ]);
        }

        // Generate invoice number if not provided (for new invoices)
        if (!$this->has('invoice_number') && $this->isMethod('POST')) {
            $this->merge([
                'invoice_number' => \App\Models\Tagihan::generateInvoiceNumber()
            ]);
        }

        // Set default invoice date
        if (!$this->has('tanggal_invoice')) {
            $this->merge([
                'tanggal_invoice' => now()->toDateString()
            ]);
        }
    }
}
