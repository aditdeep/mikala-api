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
     * Simpan field pelengkap kontrak (biaya transport, catatan revisi pasal) sebelum di-download,
     * sesuai flow "Deal In" -> tim CC bisa input hasil revisi kesepakatan dgn cust/PJ sebelum unduh
     * Kontrak MGM-Klien. Sekaligus generate nomor kontrak (sekali saja, dipertahankan setelahnya).
     */
    public function updateKontrakKlien(Request $request, $id)
    {
        $request->validate([
            'biaya_transport'        => 'nullable|numeric',
            'catatan_revisi_kontrak' => 'nullable|string',
        ]);

        try {
            $lead = \App\Models\Lead::findOrFail($id);
            if ($lead->status !== \App\Models\Lead::STATUS_DEAL) {
                return response()->json(['success' => false, 'message' => 'Kontrak hanya bisa dibuat untuk leads yang sudah Deal'], 422);
            }

            $lead->update([
                'biaya_transport'        => $request->filled('biaya_transport') ? $request->biaya_transport : ($lead->biaya_transport ?? 0),
                'catatan_revisi_kontrak' => $request->has('catatan_revisi_kontrak') ? $request->catatan_revisi_kontrak : $lead->catatan_revisi_kontrak,
                'nomor_kontrak_klien'    => $lead->nomor_kontrak_klien ?: \App\Models\Lead::generateNomorKontrakKlien(),
            ]);

            return response()->json(['success' => true, 'data' => $lead->fresh()]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Generate & stream PDF Kontrak MGM-Klien (1.1 bulanan / 1.2 harian, auto pilih dari tier_nama)
     * langsung dari data leads/deal -- sesuai "customer care flow sistem.pdf" step "Deal In".
     */
    public function downloadKontrakKlien($id)
    {
        $lead = \App\Models\Lead::with(['layanan', 'mitra'])->findOrFail($id);
        if ($lead->status !== \App\Models\Lead::STATUS_DEAL) {
            return response()->json(['success' => false, 'message' => 'Kontrak hanya bisa dibuat untuk leads yang sudah Deal'], 422);
        }
        if (!$lead->nomor_kontrak_klien) {
            $lead->update(['nomor_kontrak_klien' => \App\Models\Lead::generateNomorKontrakKlien()]);
        }

        $isHarian = str_contains(strtolower($lead->tier_nama ?? ''), 'harian');
        $html = $isHarian ? $this->buildKontrak12Html($lead) : $this->buildKontrak11Html($lead);

        $pdf = new \TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
        $pdf->SetCreator('Mikala Global Medika');
        $pdf->SetAuthor('PT. Mikala Global Medika');
        $pdf->SetTitle('Kontrak ' . $lead->nomor_kontrak_klien);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetMargins(20, 15, 20);
        $pdf->SetAutoPageBreak(true, 15);
        $pdf->AddPage();
        $pdf->SetFont('helvetica', '', 10);
        $pdf->writeHTML($html, true, false, true, false, '');

        $filename = 'Kontrak-' . str_replace('/', '-', $lead->nomor_kontrak_klien) . '.pdf';
        return response($pdf->Output($filename, 'S'), 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $filename . '"',
        ]);
    }

    private function rupiah($n): string
    {
        return 'Rp ' . number_format((float)($n ?? 0), 0, ',', '.');
    }

    /**
     * Kontrak 1.1 -- MGM-Klien (bulanan/regular). Teks pasal I-IX mengikuti dokumen
     * "Kontrak 1.1 - MG-Klien (regular) fix acc yani.docx".
     */
    private function buildKontrak11Html($lead): string
    {
        $now = now();
        $bulanIndo = ['','Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
        $namaMitra = $lead->mitra->nama_lengkap ?? '-';
        $namaCust  = $lead->nama_leads ?? '-';
        $total = (float)($lead->biaya_admin ?? 0) + (float)($lead->honor_mitra ?? 0) + (float)($lead->uang_cuti_mitra ?? 0) + (float)($lead->biaya_transport ?? 0);

        $revisi = trim($lead->catatan_revisi_kontrak ?? '');
        $revisiHtml = $revisi ? '<p><strong>Catatan / Revisi Kesepakatan Tambahan:</strong><br>' . nl2br(e($revisi)) . '</p>' : '';

        return '
        <style>
            body,p,td,li { font-size:10pt; text-align:justify; }
            h1 { font-size:13pt; text-align:center; margin-bottom:0; }
            h2 { font-size:11pt; text-align:center; margin-top:14px; }
            table.dt td { padding:1px 4px; vertical-align:top; }
        </style>
        <h1>KONTRAK SURAT PERJANJIAN ANTARA</h1>
        <h1>PT. MIKALA GLOBAL MEDIKA DENGAN PENGGUNA JASA</h1>
        <p style="text-align:center;">No. ' . e($lead->nomor_kontrak_klien) . '</p>
        <p>Pada hari ini tanggal ' . $now->day . ' bulan ' . $bulanIndo[(int)$now->format('n')] . ' Tahun ' . $now->year . ', yang bertanda tangan di bawah ini:</p>
        <table class="dt" cellpadding="0" cellspacing="0">
            <tr><td width="35%">Nama</td><td width="2%">:</td><td>Muji Mulyaningsih, ST</td></tr>
            <tr><td>Jabatan</td><td>:</td><td>Direktur</td></tr>
            <tr><td>NIK</td><td>:</td><td>001-MG-01</td></tr>
            <tr><td>Nama Lembaga</td><td>:</td><td>PT. Mikala Global Medika</td></tr>
            <tr><td>Alamat</td><td>:</td><td>Jl. Anyelir No. 1-2, Jatibening, Pondok Gede, Kota Bekasi</td></tr>
            <tr><td>Telepon</td><td>:</td><td>0821-1448-8878 / 0815-1338-2031 / 0812-9699-8827</td></tr>
        </table>
        <p>Dalam hal ini bertindak untuk dan atas nama PT. Mikala Global Medika yang selanjutnya disebut <strong>PIHAK PERTAMA</strong>.</p>
        <p>Sedangkan pengguna jasa (klien) dengan data sebagai berikut:</p>
        <table class="dt" cellpadding="0" cellspacing="0">
            <tr><td width="35%">Nama Penanggung Jawab</td><td width="2%">:</td><td>' . e($namaCust) . '</td></tr>
            <tr><td>Alamat</td><td>:</td><td>' . e($lead->alamat_cust_pj) . ' ' . e($lead->no_rumah) . '</td></tr>
            <tr><td>Telepon</td><td>:</td><td>' . e($lead->kontak) . '</td></tr>
            <tr><td>Kebutuhan</td><td>:</td><td>' . e($lead->layanan->nama ?? $lead->tier_nama ?? '-') . '</td></tr>
            <tr><td>Nama Pasien</td><td>:</td><td>' . e($lead->nama_pasien) . '</td></tr>
            <tr><td>Alamat Pasien</td><td>:</td><td>' . e($lead->alamat_klien) . '</td></tr>
            <tr><td>Hubungan dengan Pasien</td><td>:</td><td>' . e($lead->hubungan_dengan_pasien) . '</td></tr>
            <tr><td>Diagnosa Pasien</td><td>:</td><td>' . e($lead->deskripsi_diagnosa ?? $lead->diagnosis_awal) . '</td></tr>
        </table>
        <p>Disebut sebagai <strong>PIHAK KEDUA</strong> dalam surat perjanjian. PIHAK PERTAMA dan PIHAK KEDUA sepakat kemudian disebut sebagai para pihak.</p>
        <p>Dengan ini PIHAK KEDUA menyatakan persetujuannya untuk menggunakan jasa pelayanan dari tenaga home care profesional PT. Mikala Global Medika, dalam hal ini ditugaskan <strong>' . e($namaMitra) . '</strong> berikut pengganti-penggantinya di masa yang akan datang berdasarkan Surat Tugas dari PT. Mikala Global Medika, dengan syarat dan ketentuan yang tercantum pada pasal-pasal surat perjanjian ini.</p>

        <h2>PASAL I<br>DEFINISI DAN KETENTUAN UMUM</h2>
        <ol>
            <li>Tenaga home care profesional PT. Mikala Global Medika adalah individu yang telah mengikatkan diri melalui surat perjanjian kerja dan mendapatkan pelatihan sesuai bidang pekerjaan untuk memenuhi standar kompetensi, dan ditugaskan memberikan pelayanan kepada pengguna jasa yang membutuhkan bantuan untuk menunggu, menjaga, merawat pasien atau mengasuh anak.</li>
            <li>PT Mikala Global Medika adalah perusahaan yang bergerak pada bidang usaha penyalur tenaga perawat home care yang beralamat di Jl. Anyelir No. 1-2, Jatibening, Pd. Gede, Kota Bekasi. PT. Mikala Global Medika yang merupakan wadah bagi perawat home care (PHC), care giver (CG), baby sitter (BS), governess, dan jasa home care lainnya, berfungsi dalam menjaga kualitas pekerjaan, meningkatkan keterampilan dengan memberikan training, pengarahan, koordinasi, dan pembagian tugas.</li>
            <li>Biaya layanan jasa adalah biaya yang dibayarkan oleh PIHAK KEDUA kepada PIHAK PERTAMA atas pelayanan jasa untuk pembinaan, pergantian, dan penanggulangan permasalahan tenaga home care yang terjadi saat bekerja pada PIHAK KEDUA.</li>
            <li>PIHAK PERTAMA berhak menerima biaya administrasi pertama sesuai dengan tarif yang berlaku setelah Perjanjian Kontrak ini ditandatangani.</li>
            <li>PIHAK KEDUA mengetahui bahwa gaji tenaga home care profesional dititipkan kepada PIHAK PERTAMA. Dan akan di transfer kepada PIHAK PERTAMA bersama dengan biaya layanan jasa.</li>
            <li>Jika PIHAK KEDUA tidak membayar gaji tenaga Home Care selama 1 (satu) bulan berturut-turut, maka PIHAK PERTAMA berhak menarik penugasan tenaga home care tanpa penggantian dan biaya jasa harus dibayar lunas.</li>
            <li>PIHAK PERTAMA berhak menerima biaya antar awal atau biaya antar menukar tenaga home care untuk area Jabodetabek.</li>
            <li>PIHAK KEDUA wajib melakukan deposit 1 (satu) bulan di awal sesuai dengan biaya jasa yang telah disepakati.</li>
            <li>PIHAK PERTAMA akan melakukan negosiasi kepada PIHAK KEDUA atas biaya tenaga kerja jika mendampingi pengguna jasa ke luar negeri. Deposit di muka sebelum keberangkatan.</li>
            <li>PIHAK PERTAMA tidak akan bertanggung-jawab atas segala hutang dari tenaga home care secara pribadi kepada PIHAK KEDUA.</li>
            <li>Demi keamanan bersama, barang-barang berharga sebaiknya disimpan di tempat terkunci, demikian pula barang-barang bawaan tenaga home care yang bersangkutan sebelum meninggalkan tempat kerja wajib diperiksa untuk menghindari hal-hal yang tidak diinginkan. Kami tidak bertanggungjawab atas kehilangan materi apapun seperti barang berharga, uang dan sejenisnya.</li>
            <li>Pihak pertama akan memberikan penggantian tenaga home care yang se level. Jika tidak ada maka akan diberikan tenaga home care yang ada dengan konsekuensi biaya jasa yang lebih mahal atau lebih murah (Perubahan Biaya Jasa).</li>
            <li>Biaya administrasi tidak dapat dikembalikan (refund) jika terjadi pembatalan oleh PIHAK KEDUA karena dianggap keputusan sepihak.</li>
            <li>PIHAK KEDUA telah membaca dan menyetujui ketentuan umum yang merupakan satu kesatuan yang melekat pada perjanjian ini.</li>
            <li>Apabila terjadi tindakan asusila dan pelanggaran harkat / martabat terhadap tenaga home care maka perusahaan akan bertindak sebagai wakil tenaga home care untuk melakukan perbuatan hukum yang diperlukan.</li>
            <li>Tenaga home care yang bertugas pada PIHAK KEDUA, sewaktu-waktu dapat berhenti bekerja dan PIHAK PERTAMA tidak menggantikan uang administrasi dan biaya layanan jasa apabila PIHAK KEDUA melakukan pelanggaran terhadap Pasal I ayat 15 di atas atau terhadap isi surat perjanjian ini secara keseluruhan.</li>
            <li>Tenaga home care bertanggung jawab secara pribadi atas segala tindakannya baik di bidang perdata atau pidana antara lain tidak terbatas pada perbuatan yang melanggar kesusilaan, pelanggaran harkat / martabat baik terhadap pasien / anak ataupun PIHAK KEDUA serta pelanggaran atas ketentuan perundangan yang berlaku.</li>
            <li>PIHAK KEDUA dapat sewaktu-waktu memberhentikan tenaga home care dalam terjadinya perbuatan / tindakan yang dimaksud dalam pasal I ayat 17 tersebut diatas.</li>
            <li>Tenaga home care berhak mendapatkan libur 2x24 jam setiap 1 bulan bekerja, atau mendapat uang pengganti libur sesuai dengan kesepakatan atau sesuai dengan tarif yang berlaku, dan dibayarkan langsung oleh PIHAK KEDUA ke Tenaga home care.</li>
            <li>Tenaga home care berhak mendapatkan cuti selama 14 (empat belas) hari atau mendapatkan setengah bulan gaji setelah 1 tahun bekerja di rumah PIHAK KEDUA.</li>
            <li>Tenaga home care berhak mendapatkan kenaikan gaji berdasarkan hasil penilaian kinerja setelah 1 tahun bekerja di PIHAK KEDUA.</li>
            <li>Tenaga home care berhak mendapatkan Tunjangan Hari Raya yang disesuaikan dengan lamanya bekerja di PIHAK KEDUA, dengan ketentuan PIHAK PERTAMA dalam kondisi bekerja pada PIHAK KEDUA.</li>
            <li>Apabila ada pekerjaan di luar uraian tugas yang seharusnya, maka atas kesepakatan antara Tenaga home care dan PIHAK KEDUA, diberikan dana uang kompensasi. Kesepakatan dapat dalam bentuk uang tambahan sebagai kompensasi. Hal ini harus diketahui oleh PIHAK PERTAMA.</li>
            <li>Apabila dalam masa kontrak kerja PIHAK KEDUA memberhentikan Tenaga home care secara sepihak tanpa alasan yang bisa dibenarkan secara hukum, maka: (a) PIHAK KEDUA wajib memberitahukan paling lambat 2 minggu sebelum tanggal pemberhentian; (b) PIHAK KEDUA wajib membayarkan sisa gaji Tenaga home care secara proporsional sesuai jumlah hari kerja.</li>
            <li>Apabila PIHAK KEDUA memberhentikan Tenaga home care karena adanya pelanggaran kontrak kerja atau tindakan kriminal maka PIHAK KEDUA hanya membayarkan sisa gaji Tenaga home care.</li>
        </ol>

        <h2>PASAL II<br>TATA CARA PEMBAYARAN</h2>
        <ol>
            <li>PIHAK KEDUA setuju untuk melakukan pembayaran atas segala biaya-biaya yang timbul atas penggunaan jasa tenaga home care PT. Mikala Global Medika seperti yang tercantum pada Pasal I.</li>
            <li>Segala biaya-biaya awal yang timbul atas Surat Perjanjian ini, dapat dilihat pada Pasal IX tentang Rincian Biaya Pengguna Jasa Tenaga Home Care.</li>
            <li>Pembayaran Biaya Admin dan Biaya Jasa kepada PIHAK PERTAMA WAJIB ditransfer ke rekening bank: Bank Central Asia, Cabang Rawamangun, No. Rekening 6330713192, a.n. Muji Mulyaningsih.</li>
            <li>PIHAK KEDUA dalam melakukan pembayaran WAJIB mencantumkan "Nama Anak / Pasien" atau "Nama Penanggung Jawab" atau menghubungi Bagian Keuangan PERUSAHAAN di nomor 0812-9699-8827.</li>
            <li>Apabila PIHAK KEDUA tidak melakukan pembayaran sesuai dengan ketentuan Pasal II ayat 1, maka pembayaran tersebut dianggap TIDAK SAH sehingga PIHAK KEDUA tetap harus melakukan pembayaran sesuai dengan ketentuan.</li>
            <li>PIHAK KEDUA memberitahukan kepada PIHAK PERTAMA, apabila tenaga home care yang ditugaskan berhenti bertugas dengan alasan apapun. Apabila PIHAK KEDUA TIDAK memberitahukan PIHAK PERTAMA mengenai hal tersebut, dan apabila PIHAK PERTAMA melakukan kelebihan pembayaran kepada tenaga home care yang bertugas pada PIHAK KEDUA, maka PIHAK KEDUA tetap akan dibebankan atas biaya-biaya tersebut.</li>
        </ol>

        <h2>PASAL III<br>HAK PIHAK PERTAMA</h2>
        <ol>
            <li>PIHAK PERTAMA berhak untuk melakukan kunjungan atau pemantauan tenaga kerja selama bekerja di tempat PIHAK KEDUA.</li>
            <li>PIHAK PERTAMA berhak mendapatkan informasi pindah alamat, jika PIHAK KEDUA melakukan pindah alamat yang tidak sesuai lagi dengan alamat dalam perjanjian ini.</li>
            <li>PIHAK PERTAMA berhak menolak keluhan atas kelalaian dalam tindakan keperawatan yang dilakukan oleh tenaga home care, karena hal itu menjadi tanggung jawab pribadi dari tenaga home care yang bersangkutan bukan dari PIHAK PERTAMA. PIHAK KEDUA dan tenaga home care sepakat untuk membebaskan PIHAK PERTAMA dari semua ancaman atau tuntutan hukum yang timbul.</li>
            <li>PIHAK PERTAMA berhak menolak tanggung jawab atas segala tindakan pribadi tenaga home care, baik di bidang perdata dan/atau pidana, karena hal itu menjadi tanggung jawab pribadi tenaga home care.</li>
            <li>PIHAK PERTAMA berhak meminta PIHAK KEDUA untuk memberikan cuti tambahan sementara, apabila tenaga home care mengalami kondisi kurang istirahat yang disebabkan oleh keadaan pasien / anak, dan PIHAK PERTAMA akan membantu mencarikan pengganti sementara (jika ada).</li>
        </ol>

        <h2>PASAL IV<br>KEWAJIBAN PIHAK PERTAMA</h2>
        <ol>
            <li>PIHAK PERTAMA berkewajiban menyediakan tenaga kerja home care yang sehat jasmani dan rohani, bertanggungjawab dan profesional kepada PIHAK KEDUA.</li>
            <li>PIHAK PERTAMA berkewajiban untuk memberikan informasi yang sejelas-jelasnya kepada PIHAK KEDUA mengenai tenaga kerja yang diinginkan.</li>
            <li>PIHAK PERTAMA berkewajiban mengganti tenaga kerja apabila tidak cocok atau kabur.</li>
            <li>PIHAK PERTAMA berkewajiban melakukan pengawasan terhadap tugas dari tenaga home care sebagai bagian dari pelayanan.</li>
            <li>PIHAK PERTAMA berkewajiban menyediakan tata tertib dan ketentuan yang harus ditaati oleh tenaga kerja sebagai berikut: (a) Tenaga home care tidak diperkenankan meninggalkan tugasnya tanpa seizin Pengguna Jasa; (b) Untuk bulan pertama penugasan tidak diperkenankan mengambil izin cuti kecuali seizin Pengguna Jasa; (c) Tidak diperbolehkan menerima tamu, memberikan nomor telepon serta alamat tanpa seizin Pengguna Jasa; (d) Diwajibkan untuk selalu berpakaian bersih, rapi dan sopan kecuali saat istirahat serta terus bersikap sopan; (e) Dilarang menyebarkan informasi apapun tentang Pengguna Jasa dan pasien, baik secara lisan maupun non lisan, dan wajib menjaga kerahasiaan segala informasi tentang pengguna jasa dan pasien.</li>
        </ol>

        <h2>PASAL V<br>HAK PIHAK KEDUA</h2>
        <ol>
            <li>PIHAK KEDUA berhak mendapatkan tenaga kerja home care yang sehat jasmani dan rohani, bertanggungjawab dan profesional.</li>
            <li>PIHAK KEDUA berhak mendapatkan informasi yang sejelas-jelasnya dari PIHAK PERTAMA mengenai tenaga kerja yang diinginkan.</li>
            <li>PIHAK KEDUA berhak mendapatkan pengganti tenaga kerja apabila tidak cocok atau kabur.</li>
            <li>PIHAK KEDUA berhak mendapatkan pelayanan dan informasi sebaik mungkin dan sejelas-jelasnya dari PIHAK PERTAMA.</li>
            <li>PIHAK KEDUA berhak mendapatkan pengawasan dari PIHAK PERTAMA terhadap tugas dari tenaga home care sebagai bagian dari pelayanan.</li>
        </ol>

        <h2>PASAL VI<br>KEWAJIBAN PIHAK KEDUA</h2>
        <ol>
            <li>PIHAK KEDUA berkewajiban memberikan waktu kepada PIHAK PERTAMA untuk melakukan kunjungan atau pemantauan tenaga kerja selama bekerja di tempat PIHAK KEDUA.</li>
            <li>PIHAK KEDUA berkewajiban memberikan informasi pindah alamat, jika PIHAK KEDUA melakukan pindah alamat yang tidak sesuai lagi dengan alamat dalam perjanjian ini.</li>
            <li>PIHAK KEDUA berkewajiban memberikan pertolongan pertama ke klinik atau rumah sakit ketika tenaga kerja mengalami sakit.</li>
            <li>PIHAK KEDUA berkewajiban memulangkan tenaga kerja apabila terdapat musibah yang sifatnya fatal seperti kecelakaan kerja atau meninggal dunia, dan segala bentuk pembiayaan menjadi tanggung jawab PIHAK KEDUA.</li>
            <li>PIHAK KEDUA berkewajiban untuk tidak meminjamkan uang dan barang berharga kepada tenaga kerja; apabila hal tersebut terjadi bukan tanggung jawab PIHAK PERTAMA.</li>
            <li>PIHAK KEDUA berkewajiban untuk tidak memaksa mengerjakan pekerjaan yang bukan tugas dan tanggungjawab tenaga kerja.</li>
            <li>PIHAK KEDUA berkewajiban memberikan hak tenaga kerja cuti selama 2 (dua) hari atau diganti dengan uang sebesar Rp500.000,- untuk Baby Sitter dan Care Giver, dan minimal Rp700.000,- untuk Perawat Home Care.</li>
            <li>PIHAK KEDUA berkewajiban memberikan izin kepada tenaga kerja yang bersifat penting/urgen seperti sakit keras dan meninggal dunia keluarganya, dan wajib memberitahukan kepada PIHAK PERTAMA.</li>
            <li>PIHAK KEDUA berkewajiban memberikan rasa keamanan dan keselamatan tenaga kerja selama bekerja.</li>
            <li>PIHAK KEDUA memahami bahwa kelalaian dalam tindakan keperawatan yang dilakukan oleh tenaga home care menjadi tanggung jawab pribadi tenaga home care yang bersangkutan, bukan PIHAK PERTAMA. PIHAK KEDUA dan tenaga home care sepakat membebaskan PIHAK PERTAMA dari segala ancaman/tuntutan hukum yang timbul.</li>
            <li>PIHAK KEDUA memahami bahwa segala tindakan pribadi tenaga home care, baik di bidang perdata dan/atau pidana, menjadi tanggung jawab pribadi tenaga home care.</li>
            <li>PIHAK KEDUA berkewajiban memberikan tenaga home care cuti tambahan sementara apabila mengalami kondisi kurang istirahat akibat keadaan pasien/anak, dan PIHAK PERTAMA akan membantu mencarikan pengganti sementara.</li>
            <li>PIHAK KEDUA wajib menitipkan biaya jasa atas Tunjangan Hari Raya (THR) dengan cara ditransfer ke rekening PIHAK PERTAMA, dengan ketentuan: bekerja 1 tahun = THR 1 bulan gaji; kurang dari 1 tahun = THR proporsional; pembayaran selambat-lambatnya 2 minggu sebelum hari raya keagamaan; PIHAK PERTAMA menyalurkan THR tersebut serentak pada hari raya Idul Fitri baik untuk pekerja muslim maupun non-muslim.</li>
            <li>PIHAK KEDUA WAJIB memberitahukan kepada PIHAK PERTAMA apabila tenaga home care yang ditugaskan berhenti bertugas dengan alasan apapun. Apabila tidak, dan PIHAK PERTAMA melakukan kelebihan pembayaran kepada tenaga home care tersebut, maka PIHAK KEDUA tetap dibebankan atas biaya-biaya tersebut.</li>
        </ol>

        <h2>PASAL VII<br>PERSELISIHAN</h2>
        <p>Apabila terjadi perselisihan antara PIHAK PERTAMA dan PIHAK KEDUA mengenai perjanjian ini, maka kedua belah pihak sepakat untuk menyelesaikan dengan cara musyawarah untuk mencapai mufakat.</p>

        <h2>PASAL VIII<br>MASA KONTRAK</h2>
        <ol>
            <li>Masa kontrak akan berakhir apabila tugas dari tenaga home care telah selesai karena pasien meninggal/sembuh atau PIHAK KEDUA tidak melakukan pembayaran gaji tenaga home care lagi.</li>
            <li>PIHAK KEDUA setuju untuk TIDAK MENGAMBIL ALIH TENAGA HOME CARE dan mempekerjakannya tanpa sepengetahuan PIHAK PERTAMA, termasuk mempekerjakan tenaga home care yang sudah berhenti dari PIHAK PERTAMA.</li>
            <li>Apabila PIHAK KEDUA melakukan pelanggaran terhadap Pasal VIII ayat 2 di atas, maka PIHAK KEDUA setuju untuk membayar Rp50.000.000,- (lima puluh juta rupiah) sebagai ganti rugi materiil/immaterial kepada PIHAK PERTAMA.</li>
        </ol>

        <h2>PASAL IX<br>RINCIAN BIAYA PENGGUNAAN JASA</h2>
        <p>Selama dalam masa kontrak kerja PIHAK PERTAMA berhak menerima biaya jasa setiap bulan yang dibayarkan, sesuai kesepakatan para pihak. Dengan perincian:</p>
        <table class="dt" cellpadding="2" cellspacing="0">
            <tr><td width="60%">Biaya Administrasi (sekali diawal)</td><td>' . $this->rupiah($lead->biaya_admin) . '</td></tr>
            <tr><td>Gaji (' . e($lead->jasa_disetujui ?: '-') . ') + Management Fee / bulan</td><td>' . $this->rupiah($lead->honor_mitra) . '</td></tr>
            <tr><td>Uang Pengganti Cuti (2 hari dalam 1 bulan) / bulan</td><td>' . $this->rupiah($lead->uang_cuti_mitra) . '</td></tr>
            <tr><td>Biaya Transportasi Pengantaran (jika ada)</td><td>' . $this->rupiah($lead->biaya_transport) . '</td></tr>
            <tr><td><strong>TOTAL BIAYA AWAL</strong></td><td><strong>' . $this->rupiah($total) . '</strong></td></tr>
        </table>
        ' . $revisiHtml . '
        <p>Demikian Perjanjian Kontrak ini dibuat dalam keadaan sadar, sehat jasmani dan rohani serta tidak ada paksaan dari pihak manapun. Dan perjanjian ini dibuat dalam 2 (dua) rangkap dan ditandatangani oleh kedua belah pihak.</p>
        <br>
        <table cellpadding="0" cellspacing="0" width="100%">
            <tr>
                <td width="50%" style="text-align:center;">Pihak Pertama</td>
                <td width="50%" style="text-align:center;">Pihak Kedua</td>
            </tr>
            <tr><td><br><br><br></td><td></td></tr>
            <tr>
                <td style="text-align:center;">( Muji Mulyaningsih )</td>
                <td style="text-align:center;">( ' . e($namaCust) . ' )</td>
            </tr>
        </table>';
    }

    /**
     * Kontrak 1.2 -- MGM-Klien (harian). Teks mengikuti dokumen
     * "Kontrak 1.2 - MG-Klien (harian) fix acc yani.docx".
     */
    private function buildKontrak12Html($lead): string
    {
        $now = now();
        $bulanIndo = ['','Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
        $namaCust  = $lead->nama_leads ?? '-';
        $total = (float)($lead->biaya_admin ?? 0) + (float)($lead->honor_mitra ?? 0) + (float)($lead->biaya_transport ?? 0);

        $revisi = trim($lead->catatan_revisi_kontrak ?? '');
        $revisiHtml = $revisi ? '<p><strong>Catatan / Revisi Kesepakatan Tambahan:</strong><br>' . nl2br(e($revisi)) . '</p>' : '';

        return '
        <style>
            body,p,td,li { font-size:10pt; text-align:justify; }
            h1 { font-size:13pt; text-align:center; margin-bottom:0; }
            table.dt td { padding:1px 4px; vertical-align:top; }
        </style>
        <h1>KONTRAK PENGGUNAAN PERAWAT HOMECARE</h1>
        <p style="text-align:center;">ANTARA PT. MIKALA GLOBAL MEDIKA DENGAN PENANGGUNG JAWAB PASIEN</p>
        <p style="text-align:center;">No. ' . e($lead->nomor_kontrak_klien) . '</p>
        <p>Pada hari ini tanggal ' . $now->day . ' bulan ' . $bulanIndo[(int)$now->format('n')] . ' Tahun ' . $now->year . ', yang bertanda tangan di bawah ini:</p>
        <p>Muji Mulyaningsih, beralamat di Jl. Anyelir No. 1-2, Jatibening Permai, Jatibening, Pondok Gede, Jatiasih, Kota Bekasi, dalam hal ini bertindak untuk dan atas nama PT. Mikala Global Medika, dan untuk atas nama perawat-perawat yang bertugas di rumah pasien atau pengganti-penggantinya, yang selanjutnya disebut sebagai <strong>PIHAK PERTAMA</strong>.</p>
        <table class="dt" cellpadding="0" cellspacing="0">
            <tr><td width="35%">Nama Penanggung Jawab</td><td width="2%">:</td><td>' . e($namaCust) . '</td></tr>
            <tr><td>No. KTP</td><td>:</td><td>' . e($lead->no_ktp_cust_pj ?: '-') . '</td></tr>
            <tr><td>Alamat</td><td>:</td><td>' . e($lead->alamat_cust_pj) . ' ' . e($lead->no_rumah) . '</td></tr>
            <tr><td>Telepon</td><td>:</td><td>' . e($lead->kontak) . '</td></tr>
            <tr><td>Kebutuhan</td><td>:</td><td>' . e($lead->layanan->nama ?? $lead->tier_nama ?? '-') . '</td></tr>
            <tr><td>Alamat Pasien</td><td>:</td><td>' . e($lead->alamat_klien) . '</td></tr>
            <tr><td>Hubungan dengan Pasien</td><td>:</td><td>' . e($lead->hubungan_dengan_pasien) . '</td></tr>
            <tr><td>Diagnosa Pasien / Anak</td><td>:</td><td>' . e($lead->deskripsi_diagnosa ?? $lead->diagnosis_awal) . '</td></tr>
        </table>
        <p>Jenis perawatan pasien highcare dengan tenaga perawat harian. Penanggung jawab dalam hal ini sebagai pengguna Paket Homecare Perawatan Pasien, selanjutnya dalam perjanjian ini disebut sebagai <strong>PIHAK KEDUA</strong>.</p>
        <p>Dengan ini PIHAK KEDUA menyatakan setuju untuk menggunakan jasa Perawat Homecare dari PIHAK PERTAMA dengan ketentuan sebagai berikut:</p>
        <ol>
            <li>Membayar biaya Admin pengambilan perawat senilai ' . $this->rupiah($lead->biaya_admin) . ', berlaku selama pasien masih membutuhkan perawat, dengan jaminan garansi tukar perawat yang tidak terbatas.</li>
            <li>Membayar Harga Perawat Homecare senilai ' . $this->rupiah($lead->honor_mitra) . ' /hari.</li>
            <li>Pembayaran wajib ditransfer melalui rekening PT Mikala Global Medika a.n. Muji Mulyaningsih, No. Rekening BCA: 6330713192.</li>
            <li>Pembayaran kedua dan selanjutnya dilakukan PIHAK KEDUA sehari sebelum tanggal jatuh tempo (pembayaran di depan sebelum perawatan homecare pasien berjalan).</li>
            <li>PIHAK KEDUA berjanji tidak akan mengambil alih Tenaga Homecare dari PIHAK PERTAMA tanpa sepengetahuan PIHAK PERTAMA; bila terjadi maka akan dikenakan sanksi sebesar Rp50.000.000,-.</li>
            <li>Segala tindakan yang melanggar hukum dari Tenaga Homecare menjadi tanggung jawab pribadi Tenaga Homecare. PIHAK KEDUA berkewajiban menjaga keamanan penyimpanan barang-barang berharga dengan baik.</li>
        </ol>
        <table class="dt" cellpadding="2" cellspacing="0">
            <tr><td width="60%">Biaya Administrasi</td><td>' . $this->rupiah($lead->biaya_admin) . '</td></tr>
            <tr><td>Harga Perawat Homecare / hari</td><td>' . $this->rupiah($lead->honor_mitra) . '</td></tr>
            <tr><td>Biaya Transportasi Pengantaran (jika ada)</td><td>' . $this->rupiah($lead->biaya_transport) . '</td></tr>
            <tr><td><strong>TOTAL BIAYA AWAL</strong></td><td><strong>' . $this->rupiah($total) . '</strong></td></tr>
        </table>
        ' . $revisiHtml . '
        <p>PIHAK KEDUA menyatakan setuju untuk menggunakan Perawat Homecare dari PIHAK PERTAMA dan memahami serta menyetujui syarat dan ketentuan layanan perawat homecare yang tercantum di atas. Demikian surat perjanjian ini dibuat atas dasar permohonan permintaan layanan jasa perawat homecare oleh PIHAK KEDUA kepada PIHAK PERTAMA dan dibuat dalam rangkap dua serta disetujui oleh para pihak tanpa unsur paksaan dari pihak manapun.</p>
        <p>Bekasi, ' . $now->day . ' ' . $bulanIndo[(int)$now->format('n')] . ' ' . $now->year . '</p>
        <br>
        <table cellpadding="0" cellspacing="0" width="100%">
            <tr>
                <td width="50%" style="text-align:center;">Pihak Pertama</td>
                <td width="50%" style="text-align:center;">Pihak Kedua</td>
            </tr>
            <tr><td><br><br><br></td><td></td></tr>
            <tr>
                <td style="text-align:center;">Muji Mulyaningsih<br>PT Mikala Global Medika</td>
                <td style="text-align:center;">' . e($namaCust) . '<br>Penanggungjawab Pasien</td>
            </tr>
        </table>';
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
