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
}
