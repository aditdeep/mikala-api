<?php

namespace App\Http\Controllers\Internal;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use App\Models\Klien;
use App\Models\Pasien;
use App\Models\Mitra;
use App\Models\Order;
use App\Models\User;
use App\Models\Feedback;
use App\Services\NotifikasiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

class CustomerCareController extends Controller
{
    protected $notifikasiService;

    public function __construct(NotifikasiService $notifikasiService)
    {
        $this->notifikasiService = $notifikasiService;
    }

    /**
     * Register new client
     */
    public function registrasiKlien(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'phone' => 'required|string|max:20',
            'password' => 'required|min:8',
            'tipe' => 'required|in:individu,keluarga,rumah_sakit,panti_jompo,klinik',
            'alamat' => 'required|string',
            'kota' => 'required|string',
            'provinsi' => 'required|string',
        ]);

        DB::beginTransaction();
        try {
            // Create user account
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'phone' => $request->phone,
                'password' => Hash::make($request->password),
                'role' => 'klien',
                'status' => 'active',
            ]);

            // Create klien profile
            $klien = Klien::create([
                'user_id' => $user->id,
                'nama_lengkap' => $request->name,
                'tipe' => $request->tipe ?? 'individu',
                'alamat' => $request->alamat,
                'kota' => $request->kota,
                'provinsi' => $request->provinsi,
                'status' => 'active',
                'is_verified' => false,
            ]);

            // Send welcome notification
            $this->notifikasiService->send(
                $user->id,
                'Welcome to Mikala! Your account has been created successfully.',
                'welcome'
            );

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Client registered successfully',
                'data' => $klien->load('user')
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to register client: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Register patient for client
     */
    public function registrasiPasien(Request $request)
    {
        $request->validate([
            'klien_id' => 'required|exists:klien,id',
            'nama_pasien' => 'required|string|max:255',
            'tanggal_lahir' => 'nullable|date',
            'jenis_kelamin' => 'nullable|in:L,P',
            'kondisi_kesehatan' => 'nullable|string',
            'catatan' => 'nullable|string',
        ]);

        try {
            $pasien = Pasien::create($request->all());

            return response()->json([
                'success' => true,
                'message' => 'Patient registered successfully',
                'data' => $pasien
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to register patient: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * List all clients
     */
    public function indexKlien(Request $request)
    {
        try {
            $query = Klien::with('user');

            // Filters
            if ($request->has('tipe_klien')) {
                $query->where('tipe_klien', $request->tipe_klien);
            }
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }
            if ($request->has('search')) {
                $search = $request->search;
                $query->whereHas('user', function($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%");
                });
            }

            $klien = $query->orderBy('created_at', 'desc')->paginate(15);

            return response()->json([
                'success' => true,
                'data' => $klien->items(),
                'pagination' => [
                    'total' => $klien->total(),
                    'per_page' => $klien->perPage(),
                    'current_page' => $klien->currentPage(),
                    'last_page' => $klien->lastPage()
                ]
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve clients: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Show client detail with patients
     */
    public function showKlien($id)
    {
        try {
            $klien = Klien::with(['user', 'pasien', 'orders'])->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $klien
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Client not found'
            ], 404);
        }
    }

    /**
     * Update client
     */
    public function updateKlien(Request $request, $id)
    {
        $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email',
            'phone' => 'sometimes|string|max:20',
            'tipe_klien' => 'sometimes|in:personal,corporate',
            'alamat' => 'nullable|string',
            'status' => 'sometimes|in:active,inactive',
        ]);

        DB::beginTransaction();
        try {
            $klien = Klien::with('user')->findOrFail($id);

            if ($request->has('name') || $request->has('email') || $request->has('phone')) {
                $klien->user->update($request->only(['name', 'email', 'phone']));
            }

            $klien->update($request->only(['tipe_klien', 'alamat', 'status']));

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Client updated successfully',
                'data' => $klien->load('user')
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to update client: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * List mitra for assignment
     */
    public function indexMitra(Request $request)
    {
        try {
            $query = Mitra::with('user')
                ->where('status', 'aktif')
                ->where('training_status', 'available');

            if ($request->has('search')) {
                $search = $request->search;
                $query->whereHas('user', function($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%");
                });
            }

            $mitra = $query->get();

            return response()->json([
                'success' => true,
                'data' => $mitra
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve mitra: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Show mitra detail
     */
    public function showMitra($id)
    {
        try {
            $mitra = Mitra::with(['user', 'orders', 'trainings'])->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $mitra
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Mitra not found'
            ], 404);
        }
    }

    /**
     * List service orders
     */
    public function indexLayanan(Request $request)
    {
        try {
            $query = Order::with(['klien.user', 'mitra.user', 'pasien']);

            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            $orders = $query->orderBy('created_at', 'desc')->paginate(15);

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
                'message' => 'Failed to retrieve orders: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create new service order
     */
    public function storeLayanan(Request $request)
    {
        $request->validate([
            'klien_id' => 'required|exists:klien,id',
            'pasien_id' => 'nullable|exists:pasien,id',
            'mitra_id' => 'nullable|exists:mitra,id',
            'layanan_type' => 'required|string',
            'deskripsi' => 'nullable|string',
            'tanggal_mulai' => 'required|date',
            'tanggal_selesai' => 'nullable|date|after:tanggal_mulai',
            'durasi_shift' => 'nullable|string',
            'lokasi' => 'nullable|string',
            'harga_per_shift' => 'required|numeric',
            'total_shift' => 'required|integer',
        ]);

        try {
            // Auto-assign mitra if not provided
            if (!$request->mitra_id) {
                $availableMitra = Mitra::where('status', 'available')
                    ->where('training_status', 'available')
                    ->inRandomOrder()
                    ->first();
                
                if ($availableMitra) {
                    $request->merge(['mitra_id' => $availableMitra->id]);
                }
            }

            $orderNumber = 'ORD-' . date('Ymd') . '-' . str_pad(Order::count() + 1, 4, '0', STR_PAD_LEFT);
            $order = Order::create([
                'order_number'   => $orderNumber,
                'klien_id'       => $request->klien_id,
                'pasien_id'      => $request->pasien_id,
                'mitra_id'       => $request->mitra_id,
                'tipe_layanan'   => $request->layanan_type,
                'catatan'        => $request->deskripsi,
                'tanggal_mulai'  => $request->tanggal_mulai,
                'tanggal_selesai'=> $request->tanggal_selesai,
                'lokasi'         => $request->lokasi,
                'harga_per_hari' => $request->harga_per_shift ?? 0,
                'harga_per_shift'=> $request->harga_per_shift ?? 0,
                'durasi_hari'    => $request->tanggal_selesai
                    ? \Carbon\Carbon::parse($request->tanggal_mulai)->diffInDays(\Carbon\Carbon::parse($request->tanggal_selesai)) + 1
                    : 1,
                'total_shift'    => $request->total_shift ?? 1,
                'total'          => ($request->harga_per_shift ?? 0) * ($request->total_shift ?? 1),
                'status'         => 'pending',
            ]);

            // Notify client
            $klien = Klien::find($request->klien_id);
            $this->notifikasiService->send(
                $klien->user_id,
                "New order #{$order->id} has been created.",
                'order_created'
            );

            // Notify mitra if assigned
            if ($order->mitra_id) {
                $this->notifikasiService->send(
                    $order->mitra->user_id,
                    "You have been assigned to order #{$order->id}.",
                    'order_assigned'
                );
            }

            return response()->json([
                'success' => true,
                'message' => 'Service order created successfully',
                'data' => $order->load(['klien.user', 'mitra.user', 'pasien'])
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create order: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update order status
     */
    public function updateLayananStatus(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|in:pending,confirmed,in_progress,completed,cancelled,on_hold',
        ]);

        try {
            $order = Order::with(['klien.user', 'mitra.user'])->findOrFail($id);
            $oldStatus = $order->status;
            
            $order->status = $request->status;
            $order->save();

            // Update status mitra berdasarkan status order
            if ($order->mitra_id) {
                if (in_array($request->status, ['completed', 'cancelled'])) {
                    \App\Models\Mitra::where('id', $order->mitra_id)->update(['status' => 'available']);
                } elseif (in_array($request->status, ['confirmed', 'in_progress'])) {
                    \App\Models\Mitra::where('id', $order->mitra_id)->update(['status' => 'on_job']);
                }
            }

            // Notify both parties
            $this->notifikasiService->send(
                $order->klien->user_id,
                "Order #{$order->id} status changed to {$request->status}.",
                'order_status_changed'
            );

            if ($order->mitra_id) {
                $this->notifikasiService->send(
                    $order->mitra->user_id,
                    "Order #{$order->id} status changed to {$request->status}.",
                    'order_status_changed'
                );
            }

            return response()->json([
                'success' => true,
                'message' => 'Order status updated successfully',
                'data' => $order
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update order status: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Submit customer feedback
     */
    public function submitFeedback(Request $request)
    {
        $request->validate([
            'klien_id' => 'nullable|integer',
            'order_id' => 'nullable|exists:orders,id',
            'rating'   => 'required|integer|min:1|max:5',
            'komentar' => 'nullable|string',
            'catatan'  => 'nullable|string',
            'tipe'     => 'nullable|string',
        ]);

        try {
            $rating = (int) $request->rating;
            $feedback = Feedback::create([
                'order_id'               => $request->order_id,
                'klien_id'               => $request->klien_id,
                'mitra_id'               => $request->order_id ? (\App\Models\Order::find($request->order_id)?->mitra_id) : null,
                'rating_kualitas'        => $rating,
                'rating_profesionalisme' => $rating,
                'rating_komunikasi'      => $rating,
                'rating_average'         => $rating,
                'komentar'               => $request->komentar ?? $request->catatan,
                'is_published'           => true,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Feedback berhasil disimpan',
                'data' => $feedback
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to submit feedback: ' . $e->getMessage()
            ], 500);
        }
    }

    // ========== REPORTS ==========

    /**
     * Report: CC handling stats
     */
    public function reportHandling(Request $request)
    {
        try {
            $startDate = $request->input('start_date', now()->startOfMonth());
            $endDate = $request->input('end_date', now()->endOfMonth());

            $stats = [
                'total_inquiries' => Order::whereBetween('created_at', [$startDate, $endDate])->count(),
                'deals' => Order::whereBetween('created_at', [$startDate, $endDate])
                    ->whereIn('status', ['active', 'completed'])->count(),
                'losses' => Order::whereBetween('created_at', [$startDate, $endDate])
                    ->where('status', 'cancelled')->count(),
                'pending' => Order::whereBetween('created_at', [$startDate, $endDate])
                    ->where('status', 'pending')->count(),
            ];

            return response()->json([
                'success' => true,
                'data' => $stats
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate report: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Report: Deal conversion
     */
    public function reportDeal(Request $request)
    {
        try {
            $deals = Order::whereIn('status', ['active', 'completed'])
                ->with(['klien.user', 'mitra.user'])
                ->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'total' => $deals->count(),
                    'total_revenue' => $deals->sum('total_amount'),
                    'orders' => $deals
                ]
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate report: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Report: Lost deals
     */
    public function reportLoss(Request $request)
    {
        try {
            $losses = Order::where('status', 'cancelled')
                ->with(['klien.user'])
                ->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'total' => $losses->count(),
                    'potential_revenue_lost' => $losses->sum('total_amount'),
                    'orders' => $losses
                ]
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate report: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Report: CC performance rating
     */
    public function reportCCRating(Request $request)
    {
        try {
            $avgRating = Feedback::avg('rating');
            $totalFeedback = Feedback::count();
            $ratingDistribution = Feedback::select('rating', DB::raw('count(*) as count'))
                ->groupBy('rating')
                ->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'average_rating' => round($avgRating, 2),
                    'total_feedback' => $totalFeedback,
                    'distribution' => $ratingDistribution
                ]
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate report: ' . $e->getMessage()
            ], 500);
        }
    }

    public function indexFeedback(Request $request)
    {
        try {
            $feedback = \App\Models\Feedback::with(['klien.user'])
                ->orderBy('created_at', 'desc')
                ->get();
            return response()->json(['success' => true, 'data' => $feedback]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function report(Request $request)
    {
        try {
            $total   = \App\Models\Klien::count();
            $handling = \App\Models\Order::whereIn('status', ['pending','confirmed','in_progress'])->count();
            $deal    = \App\Models\Order::where('status', 'completed')->count();
            $loss    = \App\Models\Order::where('status', 'cancelled')->count();

            return response()->json([
                'success' => true,
                'data' => [
                    'total'    => $total,
                    'handling' => ['total' => $handling],
                    'deal'     => ['total' => $deal],
                    'loss'     => ['total' => $loss],
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }


    public function assignMitra(Request $request, $id)
    {
        $request->validate([
            'mitra_id' => 'required|exists:mitra,id',
        ]);

        try {
            $order = Order::findOrFail($id);
            $order->update([
                'mitra_id' => $request->mitra_id,
                'status'   => 'confirmed',
            ]);

            // Update status mitra jadi on_job
            \App\Models\Mitra::where('id', $request->mitra_id)->update(['status' => 'on_job']);

            return response()->json([
                'success' => true,
                'message' => 'Mitra berhasil di-assign',
                'data'    => $order->fresh(['mitra.user', 'klien.user'])
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

}
