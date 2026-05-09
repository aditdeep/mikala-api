<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class KlienResource extends JsonResource
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
            'phone_secondary' => $this->phone_secondary,
            'type' => $this->tipe,
            'nik' => $this->nik,
            'company_name' => $this->nama_perusahaan,
            'npwp' => $this->npwp,
            'address' => $this->alamat,
            'city' => $this->kota,
            'province' => $this->provinsi,
            'billing_method' => $this->billing_method,
            'bank_info' => [
                'bank_name' => $this->bank_name,
                'account_number' => $this->bank_account,
                'account_name' => $this->bank_account_name,
            ],
            'status' => $this->status,
            'is_verified' => $this->is_verified,
            'stats' => [
                'total_patients' => $this->total_pasien,
                'total_orders' => $this->total_orders,
                'total_billing' => round($this->total_tagihan, 2),
            ],
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
        ];
    }
}
