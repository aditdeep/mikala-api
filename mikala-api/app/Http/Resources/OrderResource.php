<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
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
            'order_number' => $this->order_number,
            'service_type' => $this->tipe_layanan,
            'service_description' => $this->deskripsi_layanan,
            'schedule' => [
                'start_date' => $this->tanggal_mulai?->format('Y-m-d'),
                'end_date' => $this->tanggal_selesai?->format('Y-m-d'),
                'start_time' => $this->jam_mulai,
                'end_time' => $this->jam_selesai,
                'duration_days' => $this->durasi_hari,
            ],
            'pricing' => [
                'price_per_day' => round($this->harga_per_hari, 2),
                'subtotal' => round($this->subtotal, 2),
                'tax' => round($this->pajak, 2),
                'discount' => round($this->diskon, 2),
                'total' => round($this->total, 2),
            ],
            'client' => [
                'id' => $this->klien->id ?? null,
                'name' => $this->klien->nama_lengkap ?? null,
            ],
            'patient' => [
                'id' => $this->pasien->id ?? null,
                'name' => $this->pasien->nama_lengkap ?? null,
            ],
            'mitra' => $this->when($this->mitra, [
                'id' => $this->mitra->id ?? null,
                'name' => $this->mitra->nama_lengkap ?? null,
                'phone' => $this->mitra->user->phone ?? null,
                'rating' => $this->mitra ? round($this->mitra->rating, 2) : null,
            ]),
            'agent' => $this->when($this->agen, [
                'id' => $this->agen->id ?? null,
                'name' => $this->agen->nama ?? null,
            ]),
            'status' => $this->status,
            'notes' => $this->catatan,
            'cancel_reason' => $this->cancel_reason,
            'timestamps' => [
                'confirmed_at' => $this->confirmed_at?->toISOString(),
                'started_at' => $this->started_at?->toISOString(),
                'completed_at' => $this->completed_at?->toISOString(),
                'created_at' => $this->created_at->toISOString(),
                'updated_at' => $this->updated_at->toISOString(),
            ],
            'has_feedback' => $this->feedback !== null,
        ];
    }
}
