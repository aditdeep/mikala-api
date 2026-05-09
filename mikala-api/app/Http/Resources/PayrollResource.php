<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PayrollResource extends JsonResource
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
            'payroll_number' => $this->payroll_number,
            'mitra' => [
                'id' => $this->mitra->id ?? null,
                'name' => $this->mitra->nama_lengkap ?? null,
                'nik' => $this->mitra->nik ?? null,
            ],
            'order' => $this->when($this->order, [
                'id' => $this->order->id ?? null,
                'order_number' => $this->order->order_number ?? null,
            ]),
            'period' => [
                'month' => $this->bulan,
                'year' => $this->tahun,
                'work_days' => $this->hari_kerja,
            ],
            'earnings' => [
                'base_salary' => round($this->gaji_pokok, 2),
                'bonus' => round($this->bonus, 2),
                'allowance' => round($this->tunjangan, 2),
                'total_earnings' => round($this->total_pendapatan, 2),
            ],
            'deductions' => [
                'tax' => round($this->potongan_pajak, 2),
                'other' => round($this->potongan_lainnya, 2),
                'total_deductions' => round($this->total_potongan, 2),
            ],
            'net_salary' => round($this->gaji_bersih, 2),
            'payment' => [
                'status' => $this->status,
                'method' => $this->metode_pembayaran,
                'paid_at' => $this->paid_at?->format('Y-m-d H:i:s'),
            ],
            'notes' => $this->catatan,
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
        ];
    }
}
