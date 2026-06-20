<?php

namespace App\Http\Controllers\Klien;

use App\Http\Controllers\Controller;
use App\Models\Feedback;
use App\Models\Mitra;
use App\Models\Notifikasi;
use App\Models\Order;
use App\Models\Pasien;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class KlienLayananController extends Controller
{
    /**
     * Get current klien's service orders
     */
    public function index(Request $request)
    {
        try {
            $klien = auth()->user()->klien;

            if (!$klien) {
                return response()->json([
                    'success' => false,
                    'message' => 'Klien profile not found'
                ], 404);
            }

            $query = Order::where('klien_id', $klien->id)
                ->with(['mitra', 'mitra.user', 'pasien']);

            // Filter by status
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            // Filter by date range
            if ($request->has('date_from')) {
                $query->whereDate('tanggal_mulai', '>=', $request->date_from);
            }

            if ($request->has('date_to')) {
                $query->whereDate('tanggal_selesai', '<=', $request->date_to);
            }

            // Order by most recent first
            $query->orderBy('created_at', 'desc');

            $orders = $query->paginate(15);

            return response()->json([
                'success' => true,
                'data' => $orders
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve orders',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get single order detail
     */
    public function show(Request $request, $id)
    {
        try {
            $klien = auth()->user()->klien;

            if (!$klien) {
                return response()->json([
                    'success' => false,
                    'message' => 'Klien profile not found'
                ], 404);
            }

            $order = Order::with([
                    'mitra.user',
                    'pasien',
                    'tagihan',
                    'feedback'
                ])
                ->where('id', $id)
                ->where('klien_id', $klien->id)
                ->first();

            if (!$order) {
                return response()->json([
                    'success' => false,
                    'message' => 'Order not found or unauthorized'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $order
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve order',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get current klien's patients
     */
    public function indexPasien(Request $request)
    {
        try {
            $klien = auth()->user()->klien;

            if (!$klien) {
                return response()->json([
                    'success' => false,
                    'message' => 'Klien profile not found'
                ], 404);
            }

            $query = Pasien::where('klien_id', $klien->id);

            // Filter by status
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            $pasien = $query->orderBy('nama_lengkap')->paginate(15);

            return response()->json([
                'success' => true,
                'data' => $pasien
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve patients',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get mitra assigned to current klien's orders
     */
    public function indexMitra(Request $request)
    {
        try {
            $klien = auth()->user()->klien;

            if (!$klien) {
                return response()->json([
                    'success' => false,
                    'message' => 'Klien profile not found'
                ], 404);
            }

            // Get distinct mitra from klien's orders
            $mitraIds = Order::where('klien_id', $klien->id)
                ->whereNotNull('mitra_id')
                ->pluck('mitra_id')
                ->unique();

            $mitra = Mitra::with('user')
                ->whereIn('id', $mitraIds)
                ->get()
                ->map(function ($m) use ($klien) {
                    return [
                        'id' => $m->id,
                        'nama_lengkap' => $m->nama_lengkap,
                        'email' => $m->user->email ?? null,
                        'phone' => $m->user->phone ?? null,
                        'rating' => $m->rating,
                        'total_reviews' => $m->total_reviews,
                        'completed_orders_count' => Order::where('mitra_id', $m->id)
                            ->where('klien_id', $klien->id)
                            ->where('status', 'completed')
                            ->count(),
                        'active_orders_count' => Order::where('mitra_id', $m->id)
                            ->where('klien_id', $klien->id)
                            ->where('status', 'in_progress')
                            ->count(),
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => $mitra
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve mitra',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Submit feedback for completed order
     */
    public function submitFeedback(Request $request, $orderId)
    {
        try {
            $request->validate([
                'rating_kualitas' => 'required|integer|min:1|max:5',
                'rating_profesionalisme' => 'required|integer|min:1|max:5',
                'rating_komunikasi' => 'required|integer|min:1|max:5',
                'komentar' => 'nullable|string|max:1000',
                'saran' => 'nullable|string|max:1000',
            ]);

            $klien = auth()->user()->klien;

            if (!$klien) {
                return response()->json([
                    'success' => false,
                    'message' => 'Klien profile not found'
                ], 404);
            }

            $order = Order::where('id', $orderId)
                ->where('klien_id', $klien->id)
                ->first();

            if (!$order) {
                return response()->json([
                    'success' => false,
                    'message' => 'Order not found or unauthorized'
                ], 404);
            }

            if ($order->status !== 'completed') {
                return response()->json([
                    'success' => false,
                    'message' => 'Feedback can only be submitted for completed orders'
                ], 400);
            }

            // Check if feedback already exists
            $existingFeedback = Feedback::where('order_id', $orderId)->first();
            if ($existingFeedback) {
                return response()->json([
                    'success' => false,
                    'message' => 'Feedback already submitted for this order'
                ], 400);
            }

            DB::beginTransaction();

            // Create feedback
            $feedback = Feedback::create([
                'order_id' => $orderId,
                'klien_id' => $klien->id,
                'mitra_id' => $order->mitra_id,
                'rating_kualitas' => $request->rating_kualitas,
                'rating_profesionalisme' => $request->rating_profesionalisme,
                'rating_komunikasi' => $request->rating_komunikasi,
                'komentar' => $request->komentar,
                'saran' => $request->saran,
                'is_published' => true,
            ]);

            // Update mitra rating
            if ($order->mitra) {
                $order->mitra->updateRating();
            }

            // Notify mitra
            if ($order->mitra && $order->mitra->user) {
                Notifikasi::create([
                    'user_id' => $order->mitra->user_id,
                    'title' => 'New Feedback Received',
                    'message' => "You received a new feedback from {$klien->nama_lengkap} for order {$order->order_number}",
                    'type' => 'feedback',
                    'related_type' => 'App\Models\Feedback',
                    'related_id' => $feedback->id,
                    'is_read' => false,
                ]);
            }

            // Notify customer care team
            $ccUsers = \App\Models\User::where('role', 'customer_care')->get();
            foreach ($ccUsers as $user) {
                Notifikasi::create([
                    'user_id' => $user->id,
                    'title' => 'New Feedback Submitted',
                    'message' => "New feedback submitted by {$klien->nama_lengkap} for order {$order->order_number}",
                    'type' => 'feedback',
                    'related_type' => 'App\Models\Feedback',
                    'related_id' => $feedback->id,
                    'is_read' => false,
                ]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Feedback submitted successfully',
                'data' => $feedback->fresh()
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to submit feedback',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function store(Request $request)
    {
        $request->validate([
            'service_type' => 'required|string',
            'pasien_id' => 'required|exists:pasien,id',
            'tanggal_mulai' => 'required|date',
            'harga_per_hari' => 'nullable|numeric|min:0',
            'catatan' => 'nullable|string',
        ]);

        try {
            $klien = $request->user()->klien;
            if (!$klien) {
                return response()->json(['success' => false, 'message' => 'Profil klien tidak ditemukan'], 404);
            }

            $harga = $request->harga_per_hari ?? 0;
            $orderNumber = 'ORD-' . date('Ymd') . '-' . str_pad(\App\Models\Order::count() + 1, 4, '0', STR_PAD_LEFT);

            $order = \App\Models\Order::create([
                'order_number' => $orderNumber,
                'klien_id' => $klien->id,
                'pasien_id' => $request->pasien_id,
                'tipe_layanan' => $request->service_type,
                'tanggal_mulai' => $request->tanggal_mulai,
                'harga_per_hari' => $harga,
                'subtotal' => $harga,
                'total' => $harga,
                'catatan' => $request->catatan,
                'status' => 'pending',
            ]);

            // Notif realtime ke staff Customer Care: order baru masuk
            $ccUserIds = \App\Models\User::where('role', 'customer_care')->pluck('id')->toArray();
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
                'message' => 'Permintaan layanan berhasil dikirim',
                'data' => $order
            ], 201);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function storePasien(Request $request)
    {
        $request->validate([
            'nama_lengkap' => 'required|string|max:255',
            'tanggal_lahir' => 'required|date',
            'jenis_kelamin' => 'required|in:L,P',
            'alamat' => 'required|string',
        ]);

        try {
            $klien = $request->user()->klien;
            if (!$klien) {
                return response()->json(['success' => false, 'message' => 'Profil klien tidak ditemukan'], 404);
            }

            $pasien = \App\Models\Pasien::create([
                'klien_id' => $klien->id,
                'nama_lengkap' => $request->nama_lengkap,
                'tanggal_lahir' => $request->tanggal_lahir,
                'jenis_kelamin' => $request->jenis_kelamin,
                'alamat' => $request->alamat,
                'riwayat_penyakit' => $request->riwayat_penyakit,
                'alergi' => $request->alergi,
                'catatan_khusus' => $request->catatan,
                'kontak_darurat_nama' => $request->kontak_darurat_nama,
                'kontak_darurat_phone' => $request->kontak_darurat_phone,
                'kontak_darurat_relasi' => $request->kontak_darurat_relasi ?? 'keluarga',
                'status' => 'active',
            ]);

            $klien->increment('total_pasien');

            return response()->json([
                'success' => true,
                'message' => 'Pasien berhasil ditambahkan',
                'data' => $pasien
            ], 201);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }


    /**
     * Update pasien data
     */
    public function updatePasien(Request $request, $id)
    {
        try {
            $klien = auth()->user()->klien;
            $pasien = Pasien::where('id', $id)->where('klien_id', $klien->id)->firstOrFail();

            $request->validate([
                'golongan_darah'       => 'nullable|in:A,B,AB,O',
                'riwayat_penyakit'     => 'nullable|string',
                'alergi'               => 'nullable|string',
                'obat_rutin'           => 'nullable|string',
                'catatan_khusus'       => 'nullable|string',
                'kontak_darurat_nama'  => 'nullable|string|max:255',
                'kontak_darurat_phone' => 'nullable|string|max:20',
                'kontak_darurat_relasi'=> 'nullable|string|max:50',
            ]);

            $pasien->update($request->only([
                'golongan_darah', 'riwayat_penyakit', 'alergi',
                'obat_rutin', 'catatan_khusus',
                'kontak_darurat_nama', 'kontak_darurat_phone', 'kontak_darurat_relasi',
            ]));

            return response()->json([
                'success' => true,
                'message' => 'Data pasien berhasil diperbarui',
                'data'    => $pasien->fresh(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

}
