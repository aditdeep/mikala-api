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
                'is_verified'=>DB::raw('false'),
            ]);

            // Send welcome notification
            $this->notifikasiService->send(
                $user->id,
                'welcome',
                'Selamat Datang di Mikala 👋',
                'Akun Anda berhasil dibuat. Selamat bergabung di Mikala Global Medika.'
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
                'order_created',
                'Pesanan Baru Dibuat 📋',
                "Order #{$order->id} telah dibuat."
            );

            // Notify mitra if assigned
            if ($order->mitra_id) {
                $this->notifikasiService->send(
                    $order->mitra->user_id,
                    'order_assigned',
                    'Penugasan Order Baru 📋',
                    "Anda ditugaskan pada order #{$order->id}."
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
                'order_status_changed',
                'Status Order Berubah 🔄',
                "Order #{$order->id} berubah status menjadi {$request->status}."
            );

            if ($order->mitra_id) {
                $this->notifikasiService->send(
                    $order->mitra->user_id,
                    'order_status_changed',
                    'Status Order Berubah 🔄',
                    "Order #{$order->id} berubah status menjadi {$request->status}."
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

            // Guard: 1 mitra = 1 job aktif (tolak assign ke-2)
            $mitraSibuk = Order::where('mitra_id', $request->mitra_id)
                ->whereIn('status', ['confirmed', 'in_progress', 'active'])
                ->where('id', '!=', $order->id)
                ->exists();
            if ($mitraSibuk) {
                return response()->json([
                    'success' => false,
                    'message' => 'Mitra ini sudah memiliki job aktif dan tidak bisa di-assign ke order lain.'
                ], 422);
            }
            $order->update([
                'mitra_id' => $request->mitra_id,
                'status'   => 'confirmed',
            ]);

            // Update status mitra jadi on_job
            \App\Models\Mitra::where('id', $request->mitra_id)->update(['status' => 'on_job']);

            // Notif realtime ke mitra: dapat assignment order baru
            $mitraNotif = \App\Models\Mitra::find($request->mitra_id);
            if ($mitraNotif && $mitraNotif->user_id) {
                \App\Services\NotifikasiService::send(
                    $mitraNotif->user_id,
                    'order',
                    'Order Baru Ditugaskan 📋',
                    "Anda mendapat penugasan order baru #" . ($order->order_number ?? $order->id) . ". Silakan cek detail order Anda.",
                    ['related_type' => 'order', 'related_id' => $order->id]
                );
            }


            // Notif realtime ke klien: mitra sudah ditugaskan
            $orderKlien = \App\Models\Order::with('klien')->find($id);
            if ($orderKlien && $orderKlien->klien && $orderKlien->klien->user_id) {
                \App\Services\NotifikasiService::send(
                    $orderKlien->klien->user_id,
                    'order',
                    'Mitra Telah Ditugaskan 👩‍⚕️',
                    "Order Anda #" . ($order->order_number ?? $order->id) . " telah mendapatkan mitra. Layanan akan segera dimulai.",
                    ['related_type' => 'order', 'related_id' => $order->id]
                );
            }
            return response()->json([
                'success' => true,
                'message' => 'Mitra berhasil di-assign',
                'data'    => $order->fresh(['mitra.user', 'klien.user'])
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Leads pipeline dashboard summary (Stage 1: Layanan breakdown).
     * Menurunkan baris "Jenis Layanan x Tier" dari cms_layanan + tier_data,
     * digabung dengan hitungan leads (proses/deal/loss) & exchange.
     */
    public function leadsSummary(Request $request)
    {
        try {
            $totalLeads = \App\Models\Lead::count();
            $totalDeal  = \App\Models\Lead::deal()->count();
            $totalLoss  = \App\Models\Lead::batal()->count();

            $layananList = \App\Models\CmsLayanan::orderBy('urutan')->get();

            $rows = [];
            foreach ($layananList as $layanan) {
                $tiers = [];
                if (!empty($layanan->tier_data)) {
                    $decoded = json_decode($layanan->tier_data, true);
                    if (is_array($decoded)) $tiers = $decoded;
                }

                if (empty($tiers)) {
                    $rows[] = $this->buildLeadsRow($layanan->id, $layanan->nama, null);
                } else {
                    foreach ($tiers as $tier) {
                        $tierNama = $tier['nama'] ?? null;
                        $rows[] = $this->buildLeadsRow($layanan->id, $layanan->nama, $tierNama);
                    }
                }
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'total_leads' => $totalLeads,
                    'total_deal'  => $totalDeal,
                    'total_loss'  => $totalLoss,
                    'by_layanan'  => $rows,
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * List leads (dengan filter status opsional).
     */
    public function indexLeads(Request $request)
    {
        try {
            $query = \App\Models\Lead::with(['layanan', 'klien.user', 'mitra.user', 'creator', 'referensiKlien.user', 'referensiMitra.user'])
                ->withCount('exchanges')
                ->orderBy('created_at', 'desc');

            if ($request->filled('status')) {
                $query->where('status', $request->status);
            }

            return response()->json(['success' => true, 'data' => $query->get()]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Tambah Leads baru (intake form).
     */
    public function storeLead(Request $request)
    {
        $request->validate([
            'cms_layanan_id'  => 'nullable|exists:cms_layanan,id',
            'tier_nama'       => 'nullable|string|max:100',
            'klien_id'        => 'nullable|exists:klien,id',
            // Cust/PJ = penanggung jawab pasien
            'nama_leads'      => 'required|string|max:255',
            'kontak'          => 'required|string|max:50',
            'no_rumah'            => 'nullable|string|max:50',
            'alamat_cust_pj'      => 'nullable|string',
            'no_ktp_cust_pj'      => 'nullable|string|max:30',
            'hubungan_dengan_pasien' => 'nullable|string|max:100',
            'email_cust_pj'       => 'nullable|email|max:255',
            // Klien = pasien
            'nama_pasien'         => 'nullable|string|max:255',
            'alamat_klien'        => 'nullable|string',
            'alamat_klien_2'      => 'nullable|string',
            'tanggal_lahir_klien' => 'nullable|date',
            'no_wa_klien'         => 'nullable|string|max:50',
            'tinggi_badan'        => 'nullable|string|max:20',
            'berat_badan'         => 'nullable|string|max:20',
            'jenis_kelamin_klien' => 'nullable|in:L,P',
            'diagnosis_awal'      => 'nullable|string',
            'deskripsi_diagnosa'  => 'nullable|string',
            'alat_pendukung'      => 'nullable|string',
            'alat_medis'          => 'nullable|array',
            'alat_medis.*'        => 'nullable|string|max:255',
            // Referensi
            'sumber'              => 'nullable|string|max:50',
            'referensi_tipe'      => 'nullable|string|max:50',
            'referensi_sub'       => 'nullable|string|max:50',
            'referensi_klien_id'  => 'nullable|exists:klien,id',
            'referensi_mitra_id'  => 'nullable|exists:mitra,id',
            'nama_referensi'      => 'nullable|string|max:255',
            'kontak_referensi'    => 'nullable|string|max:50',
            'catatan'             => 'nullable|string',
        ]);

        try {
            $lead = \App\Models\Lead::create([
                'nomor'           => \App\Models\Lead::generateNomor(),
                'cms_layanan_id'  => $request->cms_layanan_id,
                'tier_nama'       => $request->tier_nama,
                'klien_id'        => $request->klien_id,
                'nama_leads'      => $request->nama_leads,
                'kontak'          => $request->kontak,
                'no_rumah'        => $request->no_rumah,
                'alamat_cust_pj'  => $request->alamat_cust_pj,
                'no_ktp_cust_pj'  => $request->no_ktp_cust_pj,
                'hubungan_dengan_pasien' => $request->hubungan_dengan_pasien,
                'email_cust_pj'   => $request->email_cust_pj,
                'nama_pasien'     => $request->nama_pasien,
                'alamat_klien'    => $request->alamat_klien,
                'alamat_klien_2'  => $request->alamat_klien_2,
                'tanggal_lahir_klien' => $request->tanggal_lahir_klien,
                'no_wa_klien'     => $request->no_wa_klien,
                'tinggi_badan'    => $request->tinggi_badan,
                'berat_badan'     => $request->berat_badan,
                'jenis_kelamin_klien' => $request->jenis_kelamin_klien,
                'diagnosis_awal'  => $request->diagnosis_awal,
                'deskripsi_diagnosa' => $request->deskripsi_diagnosa,
                'alat_pendukung'  => $request->alat_pendukung,
                'alat_medis'      => $request->has('alat_medis') ? json_encode(array_values(array_filter($request->alat_medis))) : null,
                'sumber'          => $request->sumber,
                'referensi_tipe'  => $request->referensi_tipe,
                'referensi_sub'   => $request->referensi_sub,
                'referensi_klien_id' => $request->referensi_klien_id,
                'referensi_mitra_id' => $request->referensi_mitra_id,
                'nama_referensi'  => $request->nama_referensi,
                'kontak_referensi' => $request->kontak_referensi,
                'catatan'         => $request->catatan,
                'status'          => \App\Models\Lead::STATUS_PROSES,
                'created_by'      => $request->user()?->id,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Leads berhasil ditambahkan',
                'data'    => $lead->fresh(['layanan', 'klien.user']),
            ], 201);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Tandai Leads sebagai Deal: generate NIK, opsional assign mitra + field klinis/negosiasi jasa.
     */
    public function markLeadDeal(Request $request, $id)
    {
        $request->validate([
            'mitra_id'         => 'nullable|exists:mitra,id',
            'mitra_nim'        => 'nullable|string|max:100',
            'biaya_admin'      => 'nullable|numeric',
            'honor_mitra'      => 'nullable|numeric',
            'uang_cuti_mitra'  => 'nullable|numeric',
            'kesadaran'        => 'nullable|string|max:255',
            'komunikasi'       => 'nullable|string|max:255',
            'kelemahan'        => 'nullable|string|max:255',
            'mobilisasi'       => 'nullable|string|max:255',
            'jasa_diminta'     => 'nullable|string|max:255',
            'jasa_disarankan'  => 'nullable|string|max:255',
            'jasa_disetujui'   => 'nullable|string|max:255',
            'pembantu'         => 'nullable|string|max:255',
            'cara_mencuci_baju' => 'nullable|string|max:255',
        ]);

        try {
            $lead = \App\Models\Lead::findOrFail($id);
            $lead->update([
                'status'    => \App\Models\Lead::STATUS_DEAL,
                'nik'       => $lead->nik ?: \App\Models\Lead::generateNik(),
                'mitra_id'  => $request->filled('mitra_id') ? $request->mitra_id : $lead->mitra_id,
                'mitra_nim' => $request->filled('mitra_nim') ? $request->mitra_nim : $lead->mitra_nim,
                'biaya_admin' => $request->filled('biaya_admin') ? $request->biaya_admin : $lead->biaya_admin,
                'honor_mitra' => $request->filled('honor_mitra') ? $request->honor_mitra : $lead->honor_mitra,
                'uang_cuti_mitra' => $request->filled('uang_cuti_mitra') ? $request->uang_cuti_mitra : $lead->uang_cuti_mitra,
                'kesadaran' => $request->kesadaran ?? $lead->kesadaran,
                'komunikasi' => $request->komunikasi ?? $lead->komunikasi,
                'kelemahan' => $request->kelemahan ?? $lead->kelemahan,
                'mobilisasi' => $request->mobilisasi ?? $lead->mobilisasi,
                'jasa_diminta' => $request->jasa_diminta ?? $lead->jasa_diminta,
                'jasa_disarankan' => $request->jasa_disarankan ?? $lead->jasa_disarankan,
                'jasa_disetujui' => $request->jasa_disetujui ?? $lead->jasa_disetujui,
                'pembantu' => $request->pembantu ?? $lead->pembantu,
                'cara_mencuci_baju' => $request->cara_mencuci_baju ?? $lead->cara_mencuci_baju,
                'deal_at'  => $lead->deal_at ?: now(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Leads ditandai Deal',
                'data'    => $lead->fresh(['layanan', 'mitra.user']),
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Tandai Leads sebagai Gantung (on-hold).
     */
    public function markLeadGantung(Request $request, $id)
    {
        $request->validate([
            'alasan_status' => 'nullable|array',
            'alasan_status.*' => 'nullable|string|max:500',
        ]);

        try {
            $lead = \App\Models\Lead::findOrFail($id);
            $lead->update([
                'status'        => \App\Models\Lead::STATUS_GANTUNG,
                'alasan_status' => $request->has('alasan_status') ? json_encode(array_values(array_filter($request->alasan_status))) : $lead->alasan_status,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Leads ditandai Gantung',
                'data'    => $lead->fresh(['layanan']),
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Tandai Leads sebagai Batal (Loss) dengan alasan.
     */
    public function markLeadBatal(Request $request, $id)
    {
        $request->validate([
            'alasan_batal'     => 'required|string',
            'alasan_status'    => 'nullable|array',
            'alasan_status.*'  => 'nullable|string|max:500',
        ]);

        try {
            $lead = \App\Models\Lead::findOrFail($id);
            $lead->update([
                'status'       => \App\Models\Lead::STATUS_BATAL,
                'alasan_batal' => $request->alasan_batal,
                'alasan_status' => $request->has('alasan_status') ? json_encode(array_values(array_filter($request->alasan_status))) : $lead->alasan_status,
                'batal_at'     => now(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Leads ditandai Batal',
                'data'    => $lead->fresh(['layanan']),
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * List semua histori Exchange (tukar mitra) lintas leads.
     */
    public function indexLeadsExchange(Request $request)
    {
        try {
            $data = \App\Models\LeadExchange::with(['lead.layanan', 'mitraLama.user', 'mitraBaru.user', 'creator'])
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json(['success' => true, 'data' => $data]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Catat Exchange (tukar mitra) untuk sebuah lead yang sudah Deal.
     */
    public function storeLeadExchange(Request $request, $id)
    {
        $request->validate([
            'mitra_baru_id' => 'required|exists:mitra,id',
            'alasan'        => 'required|string',
        ]);

        try {
            $lead = \App\Models\Lead::with('layanan')->findOrFail($id);
            $mitraLamaId = $lead->mitra_id;
            $kategori = $this->layananKategoriCode($lead->layanan?->nama);

            $exchange = \App\Models\LeadExchange::create([
                'nomor'         => \App\Models\LeadExchange::generateNomor($kategori),
                'lead_id'       => $lead->id,
                'mitra_lama_id' => $mitraLamaId,
                'mitra_baru_id' => $request->mitra_baru_id,
                'alasan'        => $request->alasan,
                'exchanged_at'  => now(),
                'created_by'    => $request->user()?->id,
            ]);

            $lead->update(['mitra_id' => $request->mitra_baru_id]);

            return response()->json([
                'success' => true,
                'message' => 'Exchange berhasil dicatat',
                'data'    => $exchange->fresh(['lead.layanan', 'mitraLama.user', 'mitraBaru.user']),
            ], 201);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Kode kategori 2 huruf untuk NIM Exchange, mis. Caregiver -> CG.
     */
    private function layananKategoriCode(?string $layananNama): string
    {
        if (!$layananNama) return 'XX';
        $map = [
            'caregiver'      => 'CG',
            'perawat medis'  => 'PM',
            'perawat jiwa'   => 'PW',
            'babysitter'     => 'BS',
            'terapi'         => 'TR',
        ];
        $lower = strtolower($layananNama);
        foreach ($map as $key => $code) {
            if (str_contains($lower, $key)) return $code;
        }
        return strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $layananNama), 0, 2)) ?: 'XX';
    }

    private function buildLeadsRow($layananId, $layananNama, $tierNama)
    {
        $base = \App\Models\Lead::where('cms_layanan_id', $layananId);
        if ($tierNama !== null) {
            $base = $base->where('tier_nama', $tierNama);
        } else {
            $base = $base->whereNull('tier_nama');
        }

        $leads = (clone $base)->count();
        $deal  = (clone $base)->deal()->count();
        $loss  = (clone $base)->batal()->count();
        $exchange = \App\Models\LeadExchange::whereIn('lead_id', (clone $base)->pluck('id'))->count();

        return [
            'layanan_id'   => $layananId,
            'layanan_nama' => $layananNama,
            'tier_nama'    => $tierNama,
            'leads'        => $leads,
            'deal'         => $deal,
            'loss'         => $loss,
            'exchange'     => $exchange,
        ];
    }

}
