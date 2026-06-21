<?php

use Illuminate\Support\Facades\Route;

// Public Website MGM & MGA
Route::prefix('public/mgm')->group(function () {
    Route::get('/layanan', [MGMController::class, 'layanan']);
    Route::get('/about', [MGMController::class, 'about']);
    Route::post('/leads', [MGMController::class, 'submitLeads']);
});

Route::prefix('public/mga')->group(function () {
    Route::get('/programs', [MGAController::class, 'programPelatihan']);
    Route::post('/register', [MGAController::class, 'daftarPelatihan']);
});

// ============================================================================
// PROTECTED ROUTES (Require Authentication)
// ============================================================================

// CMS Public Routes (untuk frontend MGM)
Route::prefix('cms')->group(function () {
    Route::get('artikel', [CmsController::class, 'indexArtikel']);
    Route::get('artikel/{slug}', [CmsController::class, 'showArtikel']);
    Route::get('layanan', [CmsController::class, 'indexLayanan']);
    Route::get('galeri', [CmsController::class, 'indexGaleri']);
    Route::get('testimoni', [CmsController::class, 'indexTestimoni']);
    Route::get('settings', [CmsController::class, 'getSettings']);
    Route::post('testimoni', [CmsController::class, 'storeTestimoni']);
});

Route::middleware('auth:sanctum')->group(function () {

    // Auth routes (logged-in users)
    Route::prefix('auth')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/refresh', [AuthController::class, 'refresh']);
    });

    // ========================================================================
    // INTERNAL ROUTES (Staff Only)
    // ========================================================================
    Route::middleware('internal')->prefix('internal')->group(function () {
        Route::middleware('role:manajemen')->group(function () {
            Route::get('settings', [SettingController::class, 'index']);
            Route::patch('settings', [SettingController::class, 'update']);
            // User management
            Route::get('users', [SettingController::class, 'indexUsers']);
            Route::post('users', [SettingController::class, 'storeUser']);
            Route::patch('users/{id}', [SettingController::class, 'updateUser']);
            Route::delete('users/{id}', [SettingController::class, 'deleteUser']);
        });

        // Dashboard
        Route::prefix('dashboard')->group(function () {
            Route::get('/summary', [DashboardController::class, 'summary']);
            Route::get('/notifikasi', [DashboardController::class, 'notifikasi']);
        });

        // Upload
        Route::post('upload', [UploadController::class, 'upload']);
        Route::post('upload/base64', [UploadController::class, 'uploadBase64']);

        // Shared - semua divisi internal bisa akses
        Route::get('klien-list', function(\Illuminate\Http\Request $request) {
            $klien = \App\Models\Klien::with('user')->orderBy('created_at','desc')->get();
            return response()->json(['success' => true, 'data' => $klien]);
        });
        Route::get('mitra-list', function(\Illuminate\Http\Request $request) {
            $query = \App\Models\Mitra::with('user')->orderBy('created_at','desc');
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }
            $mitra = $query->get();
            return response()->json(['success' => true, 'data' => $mitra]);
        });

        // Rekrutmen
        Route::middleware('role:manajemen,rekrutmen')->prefix('rekrutmen')->group(function () {
            Route::apiResource('mitra', RekrutmenController::class);
            Route::get('report', [RekrutmenController::class, 'report']);
            Route::get('report/mitra-baru', [RekrutmenController::class, 'reportMitraBaru']);
            Route::get('report/mitra-keluar', [RekrutmenController::class, 'reportMitraKeluar']);
            Route::get('report/agen-institusi', [RekrutmenController::class, 'reportAgenInstitusi']);
        });

        // Lembaga (Rekrutmen & Manajemen)
        Route::middleware('role:manajemen,rekrutmen')->prefix('lembaga')->group(function () {
            Route::get('/',               [\App\Http\Controllers\Internal\LembagaController::class, 'index']);
            Route::post('/',              [\App\Http\Controllers\Internal\LembagaController::class, 'store']);
            Route::get('/{id}',           [\App\Http\Controllers\Internal\LembagaController::class, 'show']);
            Route::put('/{id}',           [\App\Http\Controllers\Internal\LembagaController::class, 'update']);
            Route::delete('/{id}',        [\App\Http\Controllers\Internal\LembagaController::class, 'destroy']);
            Route::get('/fee/list',       [\App\Http\Controllers\Internal\LembagaController::class, 'feeList']);
            Route::post('/fee/{id}/bayar',[\App\Http\Controllers\Internal\LembagaController::class, 'bayarFee']);
        });

        // Training Center
        Route::middleware('role:manajemen,training_center')->prefix('training')->group(function () {
            Route::get('mitra', [TrainingController::class, 'indexMitra']);
            Route::get('mitra/{id}/progress',              [TrainingController::class, 'mitraProgress']);
            Route::post('mitra/{id}/checklist/{materiId}',  [TrainingController::class, 'toggleChecklist']);
            Route::post('mitra/{id}/sertifikat',              [TrainingController::class, 'terbitkanSertifikat']);
            Route::get('mitra/{id}', [TrainingController::class, 'showMitra']);
            Route::post('mitra/{id}/checklist', [TrainingController::class, 'updateChecklist']);
            Route::post('mitra/{id}/feedback', [TrainingController::class, 'submitFeedback']);
            Route::patch('mitra/{id}/status', [TrainingController::class, 'updateStatus']);
            Route::get('feedback', [TrainingController::class, 'indexFeedback']);
            Route::get('report', [TrainingController::class, 'report']);
            Route::get('report/available', [TrainingController::class, 'reportAvailable']);
            Route::get('report/on-job', [TrainingController::class, 'reportOnJob']);
            Route::get('report/re-training', [TrainingController::class, 'reportReTraining']);
            Route::get('pricing', [TrainingController::class, 'indexPricing']);
            Route::patch('pricing/{id}', [TrainingController::class, 'updatePricing']);
        });

        // Customer Care
        Route::middleware('role:manajemen,customer_care')->prefix('cc')->group(function () {
            Route::post('registrasi/klien', [CustomerCareController::class, 'registrasiKlien']);
            Route::post('registrasi/pasien', [CustomerCareController::class, 'registerPasien']);
            Route::get('klien', [CustomerCareController::class, 'indexKlien']);
            Route::get('klien/{id}', [CustomerCareController::class, 'showKlien']);
            Route::patch('klien/{id}', [CustomerCareController::class, 'updateKlien']);
            Route::get('mitra', [CustomerCareController::class, 'indexMitra']);
            Route::get('mitra/{id}', [CustomerCareController::class, 'showMitra']);
            Route::get('layanan', [CustomerCareController::class, 'indexLayanan']);
            Route::post('layanan', [CustomerCareController::class, 'storeLayanan']);
            Route::patch('layanan/{id}/status', [CustomerCareController::class, 'updateLayananStatus']);
            Route::get('feedback', [CustomerCareController::class, 'indexFeedback']);
            Route::post('feedback', [CustomerCareController::class, 'submitFeedback']);
            Route::patch('layanan/{id}/assign', [CustomerCareController::class, 'assignMitra']);
            Route::get('report', [CustomerCareController::class, 'report']);
            Route::get('report/handling', [CustomerCareController::class, 'reportHandling']);
            Route::get('report/deal', [CustomerCareController::class, 'reportDeal']);
            Route::get('report/loss', [CustomerCareController::class, 'reportLoss']);
        });

        // Finance
        Route::middleware('role:manajemen,finance')->prefix('finance')->group(function () {
            Route::get('tagihan', [FinanceController::class, 'indexTagihan']);
            Route::post('tagihan', [FinanceController::class, 'storeTagihan']);
            Route::get('tagihan/{id}', [FinanceController::class, 'showTagihan']);
            Route::patch('tagihan/{id}/status', [FinanceController::class, 'updateStatusTagihan']);
            Route::get('payroll', [FinanceController::class, 'indexPayroll']);
            // Cuti management
            Route::get('cuti',                  [FinanceController::class, 'indexCuti']);
            Route::patch('cuti/{id}/approve',   [FinanceController::class, 'approveCuti']);
            // Payroll workflow
            Route::patch('payroll/{id}/adjust', [FinanceController::class, 'adjustPayroll']);
            Route::patch('payroll/{id}/approve',[FinanceController::class, 'approvePayroll']);
            Route::patch('payroll/{id}/paid',   [FinanceController::class, 'markPaid']);
            // Settings
            Route::get('payroll-settings',    [FinanceController::class, 'getPayrollSettings']);
            Route::patch('payroll-settings',  [FinanceController::class, 'updatePayrollSettings']);

            Route::post('payroll/generate', [FinanceController::class, 'generatePayroll']);
            Route::get('payroll/{id}', [FinanceController::class, 'showPayroll']);
            Route::patch('payroll/{id}/status', [FinanceController::class, 'updateStatusPayroll']);
            Route::get('jurnal', [FinanceController::class, 'indexJurnal']);
            Route::post('jurnal', [FinanceController::class, 'storeJurnal']);
            Route::get('jurnal/balancing', [FinanceController::class, 'balancing']);
            Route::get('report/income', [FinanceController::class, 'reportIncome']);
            Route::get('report/outcome', [FinanceController::class, 'reportOutcome']);
            Route::get('report/saldo', [FinanceController::class, 'reportSaldo']);
            Route::get('report/piutang', [FinanceController::class, 'reportPiutang']);
            Route::get('report/utang', [FinanceController::class, 'reportUtang']);
        });

        // CMS Management
        Route::middleware('role:manajemen,marketing')->prefix('cms')->group(function () {
            Route::get('artikel', [CmsController::class, 'indexArtikel']);
            Route::post('artikel', [CmsController::class, 'storeArtikel']);
            Route::patch('artikel/{id}', [CmsController::class, 'updateArtikel']);
            Route::delete('artikel/{id}', [CmsController::class, 'deleteArtikel']);
            Route::get('layanan', [CmsController::class, 'indexLayanan']);
            Route::post('layanan', [CmsController::class, 'storeLayanan']);
            Route::patch('layanan/{id}', [CmsController::class, 'updateLayanan']);
            Route::delete('layanan/{id}', [CmsController::class, 'deleteLayanan']);
            Route::get('galeri', [CmsController::class, 'indexGaleri']);
            Route::post('galeri', [CmsController::class, 'storeGaleri']);
            Route::delete('galeri/{id}', [CmsController::class, 'deleteGaleri']);
            Route::get('testimoni', [CmsController::class, 'indexTestimoni']);
            Route::patch('testimoni/{id}', [CmsController::class, 'updateTestimoni']);
            Route::get('settings', [CmsController::class, 'getSettings']);
            Route::post('settings', [CmsController::class, 'updateSettings']);
        });

        // Marketing
        Route::middleware('role:manajemen,marketing')->prefix('marketing')->group(function () {
            Route::get('leads', [MarketingController::class, 'indexLeads']);
            Route::post('leads', [MarketingController::class, 'storeLeads']);
            Route::get('leads/{id}', [MarketingController::class, 'showLeads']);
            Route::patch('leads/{id}/status', [MarketingController::class, 'updateLeadsStatus']);
            Route::get('kerjasama', [MarketingController::class, 'indexKerjasama']);
            Route::post('kerjasama', [MarketingController::class, 'storeKerjasama']);
            Route::get('kerjasama/{id}', [MarketingController::class, 'showKerjasama']);
            Route::get('report/order-in', [MarketingController::class, 'reportOrderIn']);
            Route::get('report/deal', [MarketingController::class, 'reportDeal']);
            Route::get('report/gap-analysis', [MarketingController::class, 'reportGapAnalysis']);
            Route::patch('leads/{id}/status', [MarketingController::class, 'updateLeadsStatus']);
            Route::get('kerjasama', [MarketingController::class, 'indexKerjasama']);
            Route::post('kerjasama', [MarketingController::class, 'storeKerjasama']);
            Route::get('kerjasama/{id}', [MarketingController::class, 'showKerjasama']);
            Route::get('report/order-in', [MarketingController::class, 'reportOrderIn']);
            Route::get('report/deal', [MarketingController::class, 'reportDeal']);
            Route::get('report/gap-analysis', [MarketingController::class, 'reportGapAnalysis']);
        });
    });

    // ========================================================================
    // MITRA ROUTES
    // ========================================================================
    Route::middleware('role:mitra')->prefix('mitra')->group(function () {
        Route::post('upload', [UploadController::class, 'upload']);
        Route::get('dashboard', function(\Illuminate\Http\Request $request) {
            try {
                $user = $request->user();
                $mitra = $user->mitra;
                if (!$mitra) return response()->json(['success' => false, 'message' => 'Mitra not found'], 404);

                $activeJobs    = \App\Models\Order::where('mitra_id', $mitra->id)->whereIn('status', ['confirmed','in_progress'])->count();
                $completedJobs = \App\Models\Order::where('mitra_id', $mitra->id)->where('status', 'completed')->count();
                $totalEarnings = \App\Models\Payroll::where('mitra_id', $mitra->id)->where('status', 'paid')->sum('total');
                $recentJobs    = \App\Models\Order::where('mitra_id', $mitra->id)->with(['klien.user','pasien'])->orderBy('created_at','desc')->limit(3)->get();

                return response()->json(['success' => true, 'data' => [
                    'active_jobs'    => $activeJobs,
                    'completed_jobs' => $completedJobs,
                    'total_earnings' => $totalEarnings,
                    'recent_jobs'    => $recentJobs,
                ]]);
            } catch (\Exception $e) {
                return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
            }
        });
        Route::get('profile', [MitraProfileController::class, 'show']);
        Route::patch('profile', [MitraProfileController::class, 'update']);
        Route::get('jobs', [MitraJobController::class, 'index']);
        Route::get('jobs/{id}', [MitraJobController::class, 'show']);
        Route::patch('jobs/{id}/status', [MitraJobController::class, 'updateStatus']);
        Route::get('payroll', [MitraPayrollController::class, 'index']);
        Route::get('payroll/{id}', [MitraPayrollController::class, 'show']);
        Route::get('notifikasi', [NotifikasiController::class, 'index']);
    });

    // ========================================================================
    // KLIEN ROUTES
    // ========================================================================
    Route::get('payment-settings', [SettingController::class, 'publicPayment']);
    Route::middleware('role:klien')->prefix('klien')->group(function () {
        Route::get('profile', [KlienProfileController::class, 'show']);
        Route::get('dashboard', [\App\Http\Controllers\Klien\KlienOrderController::class, 'dashboard']);

        // Layanan/Order management
        Route::get('layanan',                [\App\Http\Controllers\Klien\KlienOrderController::class, 'index']);
        Route::get('layanan/{id}',           [\App\Http\Controllers\Klien\KlienOrderController::class, 'show']);
        Route::patch('layanan/{id}/cancel',  [\App\Http\Controllers\Klien\KlienOrderController::class, 'cancel']);
        Route::get('order/active',           [\App\Http\Controllers\Klien\KlienOrderController::class, 'activeOrders']);
        Route::patch('profile', [KlienProfileController::class, 'update']);
        Route::get('pasien', [KlienLayananController::class, 'indexPasien']);
        Route::post('pasien', [KlienLayananController::class, 'storePasien']);
        Route::patch('pasien/{id}', [KlienLayananController::class, 'updatePasien']);
        Route::get('layanan', [KlienLayananController::class, 'index']);
        Route::post('layanan', [KlienLayananController::class, 'store']);
        Route::get('layanan/{id}', [KlienLayananController::class, 'show']);
        Route::get('tagihan', [KlienBillingController::class, 'index']);
        Route::get('tagihan/{id}', [KlienBillingController::class, 'show']);
        Route::post('tagihan/{id}/bayar', [KlienBillingController::class, 'bayar']);
        Route::get('mitra', [KlienLayananController::class, 'indexMitra']);
        Route::post('layanan/{orderId}/feedback', [KlienLayananController::class, 'submitFeedback']);
        Route::get('notifikasi', [NotifikasiController::class, 'index']);
    });

    // ========================================================================
    // SHARED ROUTES (Accessible by authenticated users)
    // ========================================================================
    Route::prefix('notifikasi')->group(function () {
        Route::get('/', [NotifikasiController::class, 'index']);
        Route::get('/unread-count', [NotifikasiController::class, 'unreadCount']);
        Route::patch('/{id}/read', [NotifikasiController::class, 'markAsRead']);
        Route::post('/mark-all-read', [NotifikasiController::class, 'markAllAsRead']);
    });
});

// ── REKRUTMEN: Verifikasi routes (injected) ──────────────────────────────────
Route::middleware(['auth:sanctum','internal','role:manajemen,rekrutmen'])->prefix('internal/rekrutmen')->group(function () {
    Route::post('mitra/{id}/terima',       [RekrutmenController::class, 'terima']);
    Route::post('mitra/{id}/tolak',        [RekrutmenController::class, 'tolak']);
    Route::post('mitra/{id}/interview',    [RekrutmenController::class, 'buatJadwalInterview']);
    Route::post('mitra/{id}/price-rate',    [RekrutmenController::class, 'setPriceRate']);
    Route::get('interview/list',           [RekrutmenController::class, 'jadwalInterviewList']);
    Route::post('interview/{id}/selesai',  [RekrutmenController::class, 'selesaiInterview']);
    Route::get('kredit',                   [RekrutmenController::class, 'kreditPelatihanList']);
    Route::put('kredit/{id}',              [RekrutmenController::class, 'updateKredit']);
});

// ── MITRA: Status Rekrutmen & Data ──────────────────────────────────────────
Route::middleware(['auth:sanctum','role:mitra'])->prefix('mitra')->group(function () {
    Route::get('status-rekrutmen', function () {
        $mitra = auth()->user()->mitra;
        $jadwal = $mitra?->jadwalInterview()->where('status','scheduled')->orderBy('jadwal_at')->first();
        return response()->json(['success'=>true,'data'=>[
            'status_rekrutmen'=>$mitra?->status_rekrutmen,
            'price_rate'=>$mitra?->price_rate,
            'payment_type'=>$mitra?->payment_type,
            'jadwal_interview'=>$jadwal,
            'catatan'=>$mitra?->catatan_rekrutmen,
        ]]);
    });

    // Cuti — Mitra
    Route::get('cuti',  [\App\Http\Controllers\Mitra\CutiController::class, 'index']);
    Route::post('cuti', [\App\Http\Controllers\Mitra\CutiController::class, 'store']);
    Route::get('kredit-pelatihan', function () {
        $mitra = auth()->user()->mitra;
        $kredit = $mitra?->kreditPelatihan()->with('potongan.order:id,kode_order,created_at')->first();
        return response()->json(['success'=>true,'data'=>$kredit]);
    });
    Route::get('jadwal-interview', function () {
        $mitra = auth()->user()->mitra;
        $jadwal = $mitra?->jadwalInterview()->with('interviewer:id,name')->orderBy('jadwal_at','desc')->get();
        return response()->json(['success'=>true,'data'=>$jadwal]);
    });
});

// ── PUBLIC: Register Mitra (dari Apps Mitra) ─────────────────────────────────
use App\Http\Controllers\Auth\MitraRegisterController;
Route::post('/auth/mitra/register', [MitraRegisterController::class, 'register']);

// Public register mitra
Route::post('/auth/mitra/register', [\App\Http\Controllers\Auth\MitraRegisterController::class, 'register']);

// Public routes untuk form register mitra
Route::get('/public/lembaga', function() {
    $data = \App\Models\Lembaga::where('status','aktif')
        ->select('id','nama','tipe','kota')
        ->orderBy('nama')->get();
    return response()->json(['success'=>true,'data'=>$data]);
});

Route::get('/public/mitra-list', function() {
    $data = \App\Models\Mitra::where('status_rekrutmen','verified')
        ->whereNotIn('status',['inactive','keluar'])
        ->select('id','nama_lengkap','kota')
        ->orderBy('nama_lengkap')->get();
    return response()->json(['success'=>true,'data'=>$data]);
});

// Set fee referral manual (rekrutmen/finance)
Route::middleware(['auth:sanctum','internal','role:manajemen,rekrutmen,finance'])->put('internal/referral/{id}/set-fee', function(\Illuminate\Http\Request $req, $id) {
    $req->validate(['fee_amount'=>'required|numeric|min:0']);
    $ref = \App\Models\MitraReferral::findOrFail($id);
    $ref->update(['fee_amount'=>$req->fee_amount]);
    return response()->json(['success'=>true,'data'=>$ref]);
});

// Mitra: fee referral saya
Route::middleware(['auth:sanctum','role:mitra'])->get('/mitra/fee-saya', function() {
    $mitra = auth()->user()->mitra;
    if (!$mitra) return response()->json(['success'=>false,'message'=>'Mitra not found'],404);

    $feeReferrer = \App\Models\MitraReferral::where('referrer_mitra_id', $mitra->id)
        ->with('mitra:id,nama_lengkap,foto_url')
        ->get();

    return response()->json([
        'success'       => true,
        'referral'      => $mitra->referral,
        'fee_referrer'  => $feeReferrer,
        'total_pending' => $feeReferrer->where('fee_status','pending')->sum('fee_amount'),
        'total_paid'    => $feeReferrer->where('fee_status','paid')->sum('fee_amount'),
    ]);
});

// ── TRAINING: Checklist Materi ────────────────────────────────────────────────
Route::middleware(['auth:sanctum','internal','role:manajemen,training_center'])->prefix('internal/training')->group(function() {
    Route::get('/materi',                           [\App\Http\Controllers\Internal\TrainingController::class, 'materiList']);
    Route::get('/mitra/{id}/progress',              [\App\Http\Controllers\Internal\TrainingController::class, 'mitraProgress']);
    Route::post('/mitra/{id}/checklist/{materiId}', [\App\Http\Controllers\Internal\TrainingController::class, 'toggleChecklist']);
});

// ── MITRA: Progress pelatihan ─────────────────────────────────────────────────
Route::middleware(['auth:sanctum','role:mitra'])->get('/mitra/pelatihan-saya', function() {
    $mitra = auth()->user()->mitra;
    if (!$mitra) return response()->json(['success'=>false],404);
    $materi  = \App\Models\TrainingMateri::where('is_active',true)->orderBy('kategori')->orderBy('urutan')->get();
    $checks  = \App\Models\TrainingChecklist::where('mitra_id',$mitra->id)->get()->keyBy('materi_id');
    $total   = $materi->count();
    $selesai = $checks->count();
    $nilaiRata = $checks->count() > 0 ? round($checks->avg('rating'), 2) : 0;

    $byKat = $materi->groupBy('kategori')->map(function($items, $kat) use ($checks) {
        $selesai = $items->filter(fn($m)=>$checks->has($m->id))->count();
        $checkedItems = $items->filter(fn($m)=>$checks->has($m->id));
        $avgRating = $checkedItems->count() > 0
            ? round($checkedItems->avg(fn($m)=>$checks->get($m->id)->rating ?? 0), 2)
            : 0;
        return [
            'kategori' => $kat,
            'total'    => $items->count(),
            'selesai'  => $selesai,
            'persen'   => $items->count()>0 ? round($selesai/$items->count()*100) : 0,
            'rating_rata' => $avgRating,
            'materi'   => $items->map(fn($m)=>[
                'id' => $m->id, 'kode' => $m->kode, 'nama' => $m->nama,
                'parent_kode' => $m->parent_kode,
                'checked' => $checks->has($m->id),
                'rating' => $checks->get($m->id)?->rating ?? 0,
                'tanggal_dapat' => $checks->get($m->id)?->tanggal_dapat?->format('Y-m-d'),
                'pengajar' => $checks->get($m->id)?->pengajar,
            ])->values(),
        ];
    })->values();

    $sertifikat = \DB::table('sertifikat_mitra')->where('mitra_id', $mitra->id)->first();

    return response()->json([
        'success' => true,
        'total' => $total, 'selesai' => $selesai,
        'persen' => $total>0 ? round($selesai/$total*100) : 0,
        'nilai_rata' => $nilaiRata,
        'status_lulus' => $mitra->status_lulus ?? 'training',
        'sertifikat' => $sertifikat,
        'by_kategori' => $byKat,
    ]);
});

// ── MGA PUBLIC ────────────────────────────────────────────────────────────────
Route::prefix('mga')->group(function() {
    Route::get('/settings', [InternalMgaController::class, 'getSettings']);
    Route::get('/artikel', [InternalMgaController::class, 'artikelIndex']);
    Route::get('/artikel/{slug}', function($slug) {
        $a = \DB::table('mga_artikel')->where('slug',$slug)->where('status','published')->first();
        return $a ? response()->json(['success'=>true,'data'=>$a]) : response()->json(['success'=>false],404);
    });
    Route::get('/galeri', [InternalMgaController::class, 'galeriIndex']);
    Route::get('/program', [InternalMgaController::class, 'programIndex']);
    Route::get('/testimoni', [InternalMgaController::class, 'testimoniIndex']);
});

// ── MGA INTERNAL CMS ─────────────────────────────────────────────────────────
Route::middleware(['auth:sanctum','internal','role:manajemen,marketing'])->prefix('internal/mga')->group(function() {
    Route::get('/settings',         [InternalMgaController::class, 'getSettings']);
    Route::post('/settings',        [InternalMgaController::class, 'updateSettings']);
    Route::get('/artikel',          [InternalMgaController::class, 'artikelIndex']);
    Route::post('/artikel',         [InternalMgaController::class, 'artikelStore']);
    Route::put('/artikel/{id}',     [InternalMgaController::class, 'artikelUpdate']);
    Route::delete('/artikel/{id}',  [InternalMgaController::class, 'artikelDestroy']);
    Route::get('/galeri',           [InternalMgaController::class, 'galeriIndex']);
    Route::post('/galeri',          [InternalMgaController::class, 'galeriStore']);
    Route::delete('/galeri/{id}',   [InternalMgaController::class, 'galeriDestroy']);
    Route::get('/program',          [InternalMgaController::class, 'programIndex']);
    Route::post('/program',         [InternalMgaController::class, 'programStore']);
    Route::put('/program/{id}',     [InternalMgaController::class, 'programUpdate']);
    Route::delete('/program/{id}',  [InternalMgaController::class, 'programDestroy']);
    Route::get('/testimoni',        [InternalMgaController::class, 'testimoniIndex']);
    Route::post('/testimoni',       [InternalMgaController::class, 'testimoniStore']);
    Route::delete('/testimoni/{id}',[InternalMgaController::class, 'testimoniDestroy']);
});

// ── MITRA: Kasbon ─────────────────────────────────────────────────────────────
Route::middleware(['auth:sanctum','role:mitra'])->get('/mitra/kasbon', function() {
    $mitra = auth()->user()->mitra;
    if (!$mitra) return response()->json(['success'=>false,'message'=>'Mitra not found'],404);
    $kasbon = \DB::table('mitra_kasbon')
        ->where('mitra_id', $mitra->id)
        ->orderBy('created_at','desc')
        ->get();
    return response()->json(['success'=>true,'data'=>$kasbon]);
});

Route::middleware(['auth:sanctum','role:mitra'])->post('/mitra/kasbon', function(\Illuminate\Http\Request $request) {
    $request->validate(['jumlah'=>'required|numeric|min:10000','keperluan'=>'required|string|max:255']);
    $mitra = auth()->user()->mitra;
    if (!$mitra) return response()->json(['success'=>false,'message'=>'Mitra not found'],404);
    $id = \DB::table('mitra_kasbon')->insertGetId([
        'mitra_id'  => $mitra->id,
        'jumlah'    => $request->jumlah,
        'keperluan' => $request->keperluan,
        'status'    => 'pending',
        'created_at'=> now(),
        'updated_at'=> now(),
    ]);
    return response()->json(['success'=>true,'data'=>\DB::table('mitra_kasbon')->find($id)],201);
});

// ── PUSH NOTIFICATION ─────────────────────────────────────────────────────────
// Simpan expo push token (mitra & klien)
Route::middleware('auth:sanctum')->post('/push-token', [NotifikasiController::class, 'saveExpoPushToken']);

// Broadcast ke semua mitra (internal only)
Route::middleware(['auth:sanctum','internal','role:manajemen'])->post('/internal/push/broadcast', [NotifikasiController::class, 'broadcastToMitra']);

// Broadcasting authentication untuk private channel (Pusher)
Route::middleware('auth:sanctum')->post('/broadcasting/auth', function (\Illuminate\Http\Request $request) {
    return \Illuminate\Support\Facades\Broadcast::auth($request);
});

