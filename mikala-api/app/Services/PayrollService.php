<?php

namespace App\Services;

use App\Models\Payroll;
use App\Models\Order;
use App\Models\Mitra;
use Illuminate\Support\Facades\DB;

class PayrollService
{
    protected $notifikasiService;

    public function __construct(NotifikasiService $notifikasiService)
    {
        $this->notifikasiService = $notifikasiService;
    }

    /**
     * Generate payroll from completed order
     */
    public function generateFromOrder(Order $order): Payroll
    {
        return DB::transaction(function () use ($order) {
            $payroll = Payroll::create([
                'payroll_number' => Payroll::generatePayrollNumber(),
                'mitra_id' => $order->mitra_id,
                'order_id' => $order->id,
                'periode_mulai' => $order->tanggal_mulai,
                'periode_selesai' => $order->tanggal_selesai ?? now(),
                'jumlah_hari_kerja' => $order->durasi_hari,
                'tarif_per_hari' => $order->harga_per_hari * 0.7, // 70% untuk mitra (example)
                'status' => 'pending',
            ]);

            $payroll->calculateTotal();

            // Notify mitra
            $this->notifikasiService->send(
                $order->mitra->user_id,
                'payroll',
                'Payroll Dibuat',
                "Payroll {$payroll->payroll_number} sebesar Rp " . number_format($payroll->total, 0, ',', '.') . " telah dibuat dan menunggu persetujuan.",
                ['related_type' => Payroll::class, 'related_id' => $payroll->id]
            );

            return $payroll;
        });
    }

    /**
     * Approve payroll
     */
    public function approve(Payroll $payroll, int $approvedBy): bool
    {
        $payroll->update([
            'status' => 'approved',
            'approved_by' => $approvedBy,
            'approved_at' => now(),
        ]);

        $this->notifikasiService->send(
            $payroll->mitra->user_id,
            'payroll',
            'Payroll Disetujui',
            "Payroll {$payroll->payroll_number} telah disetujui. Pembayaran akan segera diproses.",
            ['related_type' => Payroll::class, 'related_id' => $payroll->id]
        );

        return true;
    }

    /**
     * Mark payroll as paid
     */
    public function markAsPaid(Payroll $payroll, string $method, ?string $bukti = null): bool
    {
        $payroll->update([
            'status' => 'paid',
            'metode_pembayaran' => $method,
            'bukti_transfer' => $bukti,
            'paid_at' => now(),
        ]);

        $this->notifikasiService->send(
            $payroll->mitra->user_id,
            'payroll',
            'Payroll Dibayarkan',
            "Payroll {$payroll->payroll_number} sebesar Rp " . number_format($payroll->total, 0, ',', '.') . " telah dibayarkan.",
            ['related_type' => Payroll::class, 'related_id' => $payroll->id]
        );

        return true;
    }
}
