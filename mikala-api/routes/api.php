<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Internal\DashboardController;
use App\Http\Controllers\Internal\RekrutmenController;
use App\Http\Controllers\Internal\TrainingController;
use App\Http\Controllers\Internal\CustomerCareController;
use App\Http\Controllers\Internal\FinanceController;
use App\Http\Controllers\Internal\MarketingController;
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

        // Dashboard
        Route::prefix('dashboard')->group(function () {
            Route::get('/summary', [DashboardController::class, 'summary']);
            Route::get('/notifikasi', [DashboardController::class, 'notifikasi']);
        });

        // Rekrutmen
        Route::prefix('rekrutmen')->group(function () {
            Route::apiResource('mitra', RekrutmenController::class);
            Route::get('report', [RekrutmenController::class, 'report']);
            Route::get('report/mitra-baru', [RekrutmenController::class, 'reportMitraBaru']);
            Route::get('report/mitra-keluar', [RekrutmenController::class, 'reportMitraKeluar']);
            Route::get('report/agen-institusi', [RekrutmenController::class, 'reportAgenInstitusi']);
        });

        // Training Center
        Route::prefix('training')->group(function () {
            Route::get('mitra', [TrainingController::class, 'index']);
            Route::get('mitra/{id}', [TrainingController::class, 'show']);
            Route::post('mitra/{id}/checklist', [TrainingController::class, 'updateChecklist']);
            Route::post('mitra/{id}/feedback', [TrainingController::class, 'submitFeedback']);
            Route::patch('mitra/{id}/status', [TrainingController::class, 'updateStatus']);
            Route::get('report', [TrainingController::class, 'report']);
            Route::get('report/available', [TrainingController::class, 'reportAvailable']);
            Route::get('report/on-job', [TrainingController::class, 'reportOnJob']);
            Route::get('report/re-training', [TrainingController::class, 'reportReTraining']);
            Route::get('pricing', [TrainingController::class, 'pricing']);
            Route::patch('pricing/{id}', [TrainingController::class, 'updatePricing']);
        });

        // Customer Care
        Route::prefix('cc')->group(function () {
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
            Route::get('report', [CustomerCareController::class, 'report']);
            Route::get('report/handling', [CustomerCareController::class, 'reportHandling']);
            Route::get('report/deal', [CustomerCareController::class, 'reportDeal']);
            Route::get('report/loss', [CustomerCareController::class, 'reportLoss']);
            Route::get('report/cc-rating', [CustomerCareController::class, 'reportCCRating']);
            Route::post('feedback', [CustomerCareController::class, 'submitFeedback']);
        });

        // Finance
        Route::prefix('finance')->group(function () {
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

        // Marketing
        Route::prefix('marketing')->group(function () {
            Route::get('leads', [MarketingController::class, 'indexLeads']);
            Route::post('leads', [MarketingController::class, 'storeLeads']);
            Route::get('leads/{id}', [MarketingController::class, 'showLeads']);
            Route::put('leads/{lead}', [MarketingController::class, 'indexLeads']);
            Route::delete('leads/{lead}', [MarketingController::class, 'indexLeads']);
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
    Route::middleware('role:klien')->prefix('klien')->group(function () {
        Route::get('profile', [KlienProfileController::class, 'show']);
        Route::patch('profile', [KlienProfileController::class, 'update']);
        Route::get('pasien', [KlienLayananController::class, 'indexPasien']);
        Route::post('pasien', [KlienLayananController::class, 'storePasien']);
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

// TEMPORARY SETUP ROUTE - DELETE AFTER USE
Route::get('/setup', function() {
    $user = \App\Models\User::firstOrCreate(
        ['email' => 'admin@mikala.com'],
        [
            'name' => 'Admin Mikala',
            'phone' => '081234567890',
            'password' => \Illuminate\Support\Facades\Hash::make('password'),
            'role' => 'manajemen',
            'status' => 'active',
        ]
    );
    $mitra = \App\Models\User::firstOrCreate(
        ['email' => 'siti@example.com'],
        [
            'name' => 'Siti Nurhaliza',
            'phone' => '081298765431',
            'password' => \Illuminate\Support\Facades\Hash::make('password'),
            'role' => 'mitra',
            'status' => 'active',
        ]
    );
    $klien = \App\Models\User::firstOrCreate(
        ['email' => 'klien@example.com'],
        [
            'name' => 'Klien Test',
            'phone' => '081298765432',
            'password' => \Illuminate\Support\Facades\Hash::make('password'),
            'role' => 'klien',
            'status' => 'active',
        ]
    );
    return response()->json(['success' => true, 'admin' => $user, 'mitra' => $mitra, 'klien' => $klien]);
});
