<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Internal\DashboardController;
use App\Http\Controllers\Internal\RekrutmenController;
use App\Http\Controllers\Internal\TrainingController;
use App\Http\Controllers\Internal\CustomerCareController;
use App\Http\Controllers\Internal\CmsController;
use App\Http\Controllers\UploadController;
use App\Http\Controllers\Internal\FinanceController;
use App\Http\Controllers\Internal\MarketingController;
use App\Http\Controllers\Internal\SettingController;
use App\Http\Controllers\Mitra\MitraProfileController;
use App\Http\Controllers\Mitra\MitraJobController;
use App\Http\Controllers\Mitra\MitraPayrollController;
use App\Http\Controllers\Klien\KlienProfileController;
use App\Http\Controllers\Klien\KlienLayananController;
use App\Http\Controllers\Klien\KlienBillingController;
use App\Http\Controllers\Public\MGMController;
use App\Http\Controllers\Public\MGAController;
use App\Http\Controllers\NotifikasiController;

/*
|--------------------------------------------------------------------------
| API Routes - Mikala Management System
|--------------------------------------------------------------------------
*/

// ============================================================================
// PUBLIC ROUTES (No Authentication)
// ============================================================================

Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('/reset-password', [AuthController::class, 'resetPassword']);
});

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


// TEMPORARY - Create CMS tables
Route::get('/migrate-cms', function() {
    try {
        \Illuminate\Support\Facades\DB::statement("
            CREATE TABLE IF NOT EXISTS cms_artikel (
                id BIGSERIAL PRIMARY KEY,
                judul VARCHAR(255) NOT NULL,
                slug VARCHAR(255) UNIQUE NOT NULL,
                konten TEXT,
                excerpt TEXT,
                thumbnail TEXT,
                kategori VARCHAR(100),
                status VARCHAR(20) DEFAULT 'draft',
                author_id BIGINT,
                meta_title VARCHAR(255),
                meta_description TEXT,
                tags TEXT,
                views INT DEFAULT 0,
                created_at TIMESTAMP DEFAULT NOW(),
                updated_at TIMESTAMP DEFAULT NOW(),
                deleted_at TIMESTAMP NULL
            )
        ");
        \Illuminate\Support\Facades\DB::statement("
            CREATE TABLE IF NOT EXISTS cms_layanan (
                id BIGSERIAL PRIMARY KEY,
                nama VARCHAR(255) NOT NULL,
                deskripsi TEXT,
                deskripsi_panjang TEXT,
                icon VARCHAR(100),
                gambar TEXT,
                urutan INT DEFAULT 0,
                wa_link VARCHAR(255),
                is_active BOOLEAN DEFAULT true,
                meta_title VARCHAR(255),
                meta_description TEXT,
                created_at TIMESTAMP DEFAULT NOW(),
                updated_at TIMESTAMP DEFAULT NOW()
            )
        ");
        \Illuminate\Support\Facades\DB::statement("
            CREATE TABLE IF NOT EXISTS cms_galeri (
                id BIGSERIAL PRIMARY KEY,
                judul VARCHAR(255),
                url TEXT NOT NULL,
                thumbnail TEXT,
                kategori VARCHAR(100),
                deskripsi TEXT,
                created_at TIMESTAMP DEFAULT NOW(),
                updated_at TIMESTAMP DEFAULT NOW()
            )
        ");
        \Illuminate\Support\Facades\DB::statement("
            CREATE TABLE IF NOT EXISTS cms_testimoni (
                id BIGSERIAL PRIMARY KEY,
                nama VARCHAR(255) NOT NULL,
                layanan VARCHAR(255),
                rating INT DEFAULT 5,
                komentar TEXT,
                foto TEXT,
                status VARCHAR(20) DEFAULT 'pending',
                created_at TIMESTAMP DEFAULT NOW(),
                updated_at TIMESTAMP DEFAULT NOW()
            )
        ");
        return response()->json(['success'=>true,'message'=>'CMS tables created!']);
    } catch (\Exception $e) {
        return response()->json(['success'=>false,'message'=>$e->getMessage()]);
    }
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

        // Training Center
        Route::middleware('role:manajemen,training_center')->prefix('training')->group(function () {
            Route::get('mitra', [TrainingController::class, 'indexMitra']);
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


