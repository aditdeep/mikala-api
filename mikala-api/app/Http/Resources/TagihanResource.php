<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TagihanResource extends JsonResource
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
            'invoice_number' => $this->invoice_number,
            'client' => [
                'id' => $this->klien->id ?? null,
                'name' => $this->klien->nama_lengkap ?? null,
            ],
            'order' => [
                'id' => $this->order->id ?? null,
                'order_number' => $this->order->order_number ?? null,
                'service_type' => $this->order->tipe_layanan ?? null,
            ],
            'dates' => [
                'invoice_date' => $this->tanggal_invoice?->format('Y-m-d'),
                'due_date' => $this->tanggal_jatuh_tempo?->format('Y-m-d'),
                'paid_at' => $this->paid_at?->format('Y-m-d H:i:s'),
                'days_until_due' => $this->getDaysUntilDue(),
            ],
            'amounts' => [
                'subtotal' => round($this->subtotal, 2),
                'tax' => round($this->pajak, 2),
                'discount' => round($this->diskon, 2),
                'total' => round($this->total, 2),
                'paid_amount' => round($this->jumlah_bayar, 2),
                'remaining' => round($this->sisa, 2),
            ],
            'payment' => [
                'status' => $this->status,
                'method' => $this->metode_pembayaran,
                'proof' => $this->bukti_transfer,
            ],
            'notes' => $this->catatan,
            'is_overdue' => $this->status === 'overdue',
            'overdue_notified_at' => $this->overdue_notified_at?->toISOString(),
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
        ];
    }
}
