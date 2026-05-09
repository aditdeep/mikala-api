<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Tagihan;
use App\Models\Payroll;
use App\Models\Mitra;
use App\Models\Klien;
use App\Models\JurnalKeuangan;
use Illuminate\Support\Facades\DB;

class ReportService
{
    /**
     * Generate financial report
     */
    public function financialReport(string $startDate, string $endDate): array
    {
        $income = Tagihan::whereBetween('tanggal_invoice', [$startDate, $endDate])
            ->where('status', 'paid')
            ->sum('total');

        $outcome = Payroll::whereBetween('periode_mulai', [$startDate, $endDate])
            ->where('status', 'paid')
            ->sum('total');

        $saldo = JurnalKeuangan::getSaldo($startDate, $endDate);

        $piutang = Tagihan::whereIn('status', ['unpaid', 'partial', 'overdue'])
            ->sum('sisa');

        return [
            'periode' => [
                'mulai' => $startDate,
                'selesai' => $endDate,
            ],
            'income' => $income,
            'outcome' => $outcome,
            'profit' => $income - $outcome,
            'saldo' => $saldo,
            'piutang' => $piutang,
        ];
    }

    /**
     * Generate order report
     */
    public function orderReport(string $startDate, string $endDate): array
    {
        $orders = Order::whereBetween('created_at', [$startDate, $endDate])->get();

        $statusBreakdown = $orders->groupBy('status')->map->count();
        $layananBreakdown = $orders->groupBy('tipe_layanan')->map->count();
        $totalRevenue = $orders->where('status', 'completed')->sum('total');

        return [
            'total_orders' => $orders->count(),
            'status_breakdown' => $statusBreakdown,
            'layanan_breakdown' => $layananBreakdown,
            'total_revenue' => $totalRevenue,
        ];
    }

    /**
     * Generate mitra report
     */
    public function mitraReport(): array
    {
        $total = Mitra::count();
        $available = Mitra::where('status', 'available')->count();
        $onJob = Mitra::where('status', 'on_job')->count();
        $training = Mitra::where('status', 'training')->count();

        $topRated = Mitra::where('is_verified', true)
            ->orderBy('rating', 'desc')
            ->limit(10)
            ->get(['id', 'nama_lengkap', 'rating', 'total_reviews']);

        return [
            'total' => $total,
            'available' => $available,
            'on_job' => $onJob,
            'training' => $training,
            'top_rated' => $topRated,
        ];
    }

    /**
     * Generate klien report
     */
    public function klienReport(): array
    {
        $total = Klien::count();
        $active = Klien::where('status', 'active')->count();
        $byType = Klien::select('tipe', DB::raw('count(*) as count'))
            ->groupBy('tipe')
            ->pluck('count', 'tipe');

        return [
            'total' => $total,
            'active' => $active,
            'by_type' => $byType,
        ];
    }
}
