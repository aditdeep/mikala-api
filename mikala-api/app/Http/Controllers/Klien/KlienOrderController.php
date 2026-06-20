<?php

namespace App\Http\Controllers\Klien;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;

class KlienOrderController extends Controller
{
    /**
     * GET /klien/layanan — List semua order milik klien login
     */
    public function index(Request $request)
    {
        $user  = $request->user();
        $klien = $user->klien;
        if (!$klien) {
            return response()->json(['success'=>false,'message'=>'Klien not found'], 404);
        }

        $query = Order::where('klien_id', $klien->id)
            ->with(['mitra:id,nama_lengkap,foto_url', 'pasien:id,nama_lengkap']);

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $orders = $query->orderBy('created_at', 'desc')->paginate(20);

        return response()->json([
            'success' => true,
            'data'    => $orders,
        ]);
    }

    /**
     * GET /klien/layanan/{id} — Detail order
     */
    public function show(Request $request, $id)
    {
        $user  = $request->user();
        $klien = $user->klien;
        if (!$klien) return response()->json(['success'=>false,'message'=>'Klien not found'], 404);

        $order = Order::where('id', $id)
            ->where('klien_id', $klien->id)
            ->with(['mitra:id,nama_lengkap,foto_url,no_hp', 'pasien:id,nama_lengkap,jenis_kelamin,usia,kondisi'])
            ->first();

        if (!$order) {
            return response()->json(['success'=>false,'message'=>'Order not found'], 404);
        }

        return response()->json(['success'=>true,'data'=>$order]);
    }

    /**
     * POST /klien/layanan — Buat order baru
     */
    public function store(Request $request)
    {
        $request->validate([
            'pasien_id'       => 'required|exists:pasien,id',
            'tipe_layanan'    => 'required|string',
            'tanggal_mulai'   => 'required|date',
            'durasi_hari'     => 'nullable|integer|min:1',
            'tanggal_selesai' => 'nullable|date|after:tanggal_mulai',
            'jenis_jadwal'    => 'nullable|in:harian,bulanan,shift',
            'catatan'         => 'nullable|string',
        ]);

        $user  = $request->user();
        $klien = $user->klien;
        if (!$klien) return response()->json(['success'=>false,'message'=>'Klien not found'], 404);

        // Hitung tanggal selesai kalau tidak diisi
        $tanggalSelesai = $request->tanggal_selesai;
        if (!$tanggalSelesai && $request->durasi_hari) {
            $tanggalSelesai = \Carbon\Carbon::parse($request->tanggal_mulai)
                ->addDays($request->durasi_hari - 1)
                ->toDateString();
        }

        $orderNumber = 'ORD-' . date('Ymd') . '-' . str_pad(Order::count() + 1, 4, '0', STR_PAD_LEFT);

        $order = Order::create([
            'order_number'    => $orderNumber,
            'klien_id'        => $klien->id,
            'pasien_id'       => $request->pasien_id,
            'tipe_layanan'    => $request->tipe_layanan,
            'tanggal_mulai'   => $request->tanggal_mulai,
            'tanggal_selesai' => $tanggalSelesai,
            'jenis_jadwal'    => $request->jenis_jadwal ?? 'harian',
            'catatan'         => $request->catatan,
            'status'          => 'pending',
        ]);

        // Notif realtime ke staff Customer Care: order baru masuk
        $ccUserIds = \App\Models\User::byRole('customer_care')->pluck('id')->toArray();
        if (!empty($ccUserIds)) {
            \App\Services\NotifikasiService::sendBulk(
                $ccUserIds,
                'order',
                'Order Baru Masuk 📥',
                "Order baru " . $order->order_number . " dari klien " . ($klien->nama_lembaga ?? $klien->nama ?? 'Klien') . " perlu di-assign mitra.",
                ['related_type' => 'order', 'related_id' => $order->id]
            );
        }

        return response()->json([
            'success' => true,
            'message' => 'Order berhasil dibuat, menunggu assignment mitra',
            'data'    => $order->load(['pasien:id,nama_lengkap']),
        ], 201);
    }
    /**
     * GET /klien/dashboard — Statistik ringkas untuk homepage klien
     */
    public function dashboard(Request $request)
    {
        $user  = $request->user();
        $klien = $user->klien;
        if (!$klien) return response()->json(['success'=>false,'message'=>'Klien not found'], 404);

        $activeServices = Order::where('klien_id', $klien->id)
            ->whereIn('status', ['pending', 'assigned', 'confirmed', 'in_progress'])
            ->count();

        $totalPatients = $klien->pasien()->count();

        $recentServices = Order::where('klien_id', $klien->id)
            ->with('pasien:id,nama_lengkap')
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get()
            ->map(function ($o) {
                return [
                    'id'           => $o->id,
                    'service_type' => $o->tipe_layanan,
                    'patient_name' => $o->pasien->nama_lengkap ?? '-',
                    'status'       => $o->status,
                ];
            });

        return response()->json([
            'success' => true,
            'data' => [
                'active_services' => $activeServices,
                'total_patients'  => $totalPatients,
                'recent_services' => $recentServices,
            ],
        ]);
    }


    /**
     * GET /klien/order/active — Order aktif untuk dashboard
     */
    public function activeOrders(Request $request)
    {
        $user  = $request->user();
        $klien = $user->klien;
        if (!$klien) return response()->json(['success'=>false,'message'=>'Klien not found'], 404);

        $orders = Order::where('klien_id', $klien->id)
            ->whereIn('status', ['pending', 'assigned', 'in_progress'])
            ->with(['mitra:id,nama_lengkap,foto_url', 'pasien:id,nama_lengkap'])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json(['success'=>true,'data'=>$orders]);
    }

    /**
     * PATCH /klien/layanan/{id}/cancel — Batal order (hanya pending)
     */
    public function cancel(Request $request, $id)
    {
        $user  = $request->user();
        $klien = $user->klien;
        if (!$klien) return response()->json(['success'=>false,'message'=>'Klien not found'], 404);

        $order = Order::where('id', $id)->where('klien_id', $klien->id)->first();
        if (!$order) {
            return response()->json(['success'=>false,'message'=>'Order not found'], 404);
        }

        if ($order->status !== 'pending') {
            return response()->json(['success'=>false,'message'=>'Hanya order pending yang bisa dibatalkan'], 400);
        }

        $order->update([
            'status'         => 'cancelled',
            'catatan_cancel' => $request->catatan ?? 'Dibatalkan oleh klien',
        ]);

        return response()->json(['success'=>true,'data'=>$order]);
    }
}
