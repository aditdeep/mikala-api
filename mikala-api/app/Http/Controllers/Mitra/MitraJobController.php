<?php

namespace App\Http\Controllers\Mitra;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use App\Models\Order;
use Illuminate\Http\Request;

class MitraJobController extends Controller
{
    /**
     * List assigned jobs/orders
     */
    public function index(Request $request)
    {
        try {
            $user = $request->user();
            $mitra = $user->mitra;

            if (!$mitra) {
                return response()->json([
                    'success' => false,
                    'message' => 'Mitra profile not found'
                ], 404);
            }

            $query = Order::where('mitra_id', $mitra->id)
                ->with(['klien.user', 'pasien']);

            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            $orders = $query->orderBy('tanggal_mulai', 'desc')->paginate(15);

            return response()->json([
                'success' => true,
                'data' => $orders->items(),
                'pagination' => [
                    'total' => $orders->total(),
                    'per_page' => $orders->perPage(),
                    'current_page' => $orders->currentPage(),
                    'last_page' => $orders->lastPage()
                ]
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve jobs: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Show job detail
     */
    public function show(Request $request, $id)
    {
        try {
            $user = $request->user();
            $mitra = $user->mitra;

            $order = Order::where('id', $id)
                ->where('mitra_id', $mitra->id)
                ->with(['klien.user', 'pasien', 'feedbacks'])
                ->firstOrFail();

            return response()->json([
                'success' => true,
                'data' => $order
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Job not found or access denied'
            ], 404);
        }
    }

    /**
     * Update job status (accept/complete)
     */
    public function updateStatus(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|in:pending,confirmed,in_progress,completed,cancelled,on_hold',
            'notes' => 'nullable|string',
        ]);

        try {
            $user = $request->user();
            $mitra = $user->mitra;

            $order = Order::where('id', $id)
                ->where('mitra_id', $mitra->id)
                ->firstOrFail();

            $order->status = $request->status;
            if ($request->status === 'in_progress') {
                $order->started_at = now();
                // Pastikan status mitra on_job
                $mitra->update(['status' => 'on_job']);
            } elseif ($request->status === 'completed') {
                $order->completed_at = now();
                // Kembalikan status mitra ke available
                $mitra->update(['status' => 'available']);
                // Auto generate payroll saat selesai
                $total = floatval($order->total ?? $order->total_amount ?? $order->total_harga ?? 0);
                $mitraShare = $total * 0.8;
                $tanggalMulai = $order->tanggal_mulai ?? now();
                $tanggalSelesai = $order->completed_at ?? now();
                $jumlahHari = max(1, \Carbon\Carbon::parse($tanggalMulai)->diffInDays($tanggalSelesai));
                $payrollNumber = 'PAY-'.date('Ymd').'-'.str_pad(\App\Models\Payroll::count()+1, 4, '0', STR_PAD_LEFT);

                \App\Models\Payroll::firstOrCreate(
                    ['order_id' => $order->id, 'mitra_id' => $mitra->id],
                    [
                        'payroll_number'   => $payrollNumber,
                        'periode_mulai'    => $tanggalMulai,
                        'periode_selesai'  => $tanggalSelesai,
                        'jumlah_hari_kerja'=> $jumlahHari,
                        'tarif_per_hari'   => $jumlahHari > 0 ? $mitraShare / $jumlahHari : 0,
                        'gaji_pokok'       => $mitraShare,
                        'bonus'            => 0,
                        'potongan'         => 0,
                        'total'            => $mitraShare,
                        'status'           => 'pending',
                        'catatan'          => 'Auto dari Order #'.$order->order_number,
                    ]
                );
            }

            if ($request->has('notes')) {
                $order->mitra_notes = $request->notes;
            }

            $order->save();

            return response()->json([
                'success' => true,
                'message' => 'Job status updated successfully',
                'data' => $order
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update job status: ' . $e->getMessage()
            ], 500);
        }
    }
}
