<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Internal\DashboardController;
use App\Http\Controllers\Internal\RekrutmenController;
use App\Http\Controllers\Internal\TrainingController;
use App\Http\Controllers\Internal\CustomerCareController;
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

        // Marketing
        Route::middleware('role:manajemen,marketing')->prefix('marketing')->group(function () {
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


// TEMPORARY MIGRATE ROUTE - DELETE AFTER USE
Route::get('/migrate-settings', function() {
    try {
        if (!\Illuminate\Support\Facades\Schema::hasTable('settings')) {
            \Illuminate\Support\Facades\Schema::create('settings', function ($table) {
                $table->id();
                $table->string('key')->unique();
                $table->text('value')->nullable();
                $table->string('group')->default('general');
                $table->timestamps();
            });

            \Illuminate\Support\Facades\DB::table('settings')->insert([
                ['key' => 'bank_name',         'value' => 'BCA',                     'group' => 'payment', 'created_at' => now(), 'updated_at' => now()],
                ['key' => 'bank_account',      'value' => '1234567890',              'group' => 'payment', 'created_at' => now(), 'updated_at' => now()],
                ['key' => 'bank_account_name', 'value' => 'PT Mikala Global Medika', 'group' => 'payment', 'created_at' => now(), 'updated_at' => now()],
                ['key' => 'xendit_enabled',    'value' => 'false',                   'group' => 'payment', 'created_at' => now(), 'updated_at' => now()],
                ['key' => 'xendit_secret_key', 'value' => '',                        'group' => 'payment', 'created_at' => now(), 'updated_at' => now()],
                ['key' => 'xendit_public_key', 'value' => '',                        'group' => 'payment', 'created_at' => now(), 'updated_at' => now()],
            ]);

            return response()->json(['success' => true, 'message' => 'Tabel settings berhasil dibuat & di-seed!']);
        }
        return response()->json(['success' => true, 'message' => 'Tabel settings sudah ada, skip.']);
    } catch (\Exception $e) {
        return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
    }
});


// TEMPORARY - Reset password mitra
Route::get('/reset-mitra-password', function() {
    try {
        $email = request('email');
        $password = request('password', 'password123');
        $user = \App\Models\User::whereRaw('LOWER(email) = ?', [strtolower($email)])->first();
        if (!$user) return response()->json(['success' => false, 'message' => 'User tidak ditemukan']);
        $user->update(['password' => \Illuminate\Support\Facades\Hash::make($password)]);
        return response()->json(['success' => true, 'message' => 'Password berhasil direset', 'email' => $email, 'password' => $password]);
    } catch (\Exception $e) {
        return response()->json(['success' => false, 'message' => $e->getMessage()]);
    }
});


// TEMPORARY - List semua user mitra
Route::get('/list-mitra-users', function() {
    $users = \App\Models\User::where('role', 'mitra')->select('id','name','email','status','created_at')->get();
    return response()->json(['total' => $users->count(), 'data' => $users]);
});


// TEMPORARY - List internal users & roles
Route::get('/list-internal-users', function() {
    $users = \App\Models\User::whereNotIn('role', ['mitra','klien'])
        ->select('id','name','email','role','status')
        ->get();
    return response()->json(['total' => $users->count(), 'data' => $users]);
});


// TEMPORARY - Setup user per divisi untuk testing
Route::get('/setup-divisi', function() {
    $divisi = [
        ['role' => 'rekrutmen',       'phone' => '08100000001'],
        ['role' => 'training_center', 'phone' => '08100000002'],
        ['role' => 'customer_care',   'phone' => '08100000003'],
        ['role' => 'finance',         'phone' => '08100000004'],
        ['role' => 'marketing',       'phone' => '08100000005'],
    ];
    $created = [];
    foreach ($divisi as $d) {
        $user = \App\Models\User::firstOrCreate(
            ['email' => $d['role'].'@mikala.com'],
            [
                'name' => ucfirst(str_replace('_', ' ', $d['role'])),
                'phone' => $d['phone'],
                'password' => \Illuminate\Support\Facades\Hash::make('password'),
                'role' => $d['role'],
                'status' => 'active',
            ]
        );
        $created[] = ['email' => $user->email, 'role' => $user->role, 'password' => 'password'];
    }
    return response()->json(['success' => true, 'users' => $created]);
});


// TEMPORARY - Check mitra table
Route::get('/check-mitra-table', function() {
    $mitra = \App\Models\Mitra::with('user')->get()->map(function($m) {
        return [
            'id' => $m->id,
            'user_id' => $m->user_id,
            'name' => $m->user?->name,
            'status' => $m->status,
            'training_status' => $m->training_status,
        ];
    });
    return response()->json(['total' => $mitra->count(), 'data' => $mitra]);
});


// TEMPORARY - Check & migrate trainings table
Route::get('/migrate-trainings', function() {
    try {
        $tables = \Illuminate\Support\Facades\DB::select("SELECT table_name FROM information_schema.tables WHERE table_schema = 'public'");
        $tableNames = array_map(fn($t) => $t->table_name, $tables);

        if (!in_array('trainings', $tableNames)) {
            \Illuminate\Support\Facades\Artisan::call('migrate', ['--path' => 'database/migrations/2024_01_01_000009_create_trainings_table.php', '--force' => true]);
            return response()->json(['success' => true, 'message' => 'Tabel trainings berhasil dibuat!', 'tables' => $tableNames]);
        }
        return response()->json(['success' => true, 'message' => 'Tabel trainings sudah ada', 'tables' => $tableNames]);
    } catch (\Exception $e) {
        return response()->json(['success' => false, 'message' => $e->getMessage()]);
    }
});


// TEMPORARY - Test training query
Route::get('/test-training-query', function() {
    try {
        $mitra = \App\Models\Mitra::with(['user', 'trainings'])->paginate(15);
        return response()->json(['success' => true, 'total' => $mitra->total(), 'data' => $mitra->items()]);
    } catch (\Exception $e) {
        return response()->json(['success' => false, 'message' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
    }
});


// TEMPORARY - Make feedback order_id nullable
Route::get('/fix-feedback-nullable', function() {
    try {
        \Illuminate\Support\Facades\DB::statement('ALTER TABLE feedback ALTER COLUMN order_id DROP NOT NULL');
        return response()->json(['success' => true, 'message' => 'order_id sekarang nullable!']);
    } catch (\Exception $e) {
        return response()->json(['success' => false, 'message' => $e->getMessage()]);
    }
});


// TEMPORARY - Check klien table ids
Route::get('/check-klien-ids', function() {
    $klien = \App\Models\Klien::with('user')->get()->map(fn($k) => ['id'=>$k->id,'user_id'=>$k->user_id,'nama'=>$k->nama_lengkap]);
    return response()->json(['data' => $klien]);
});


// TEMPORARY - Fix feedback nullable columns
Route::get('/fix-feedback-all-nullable', function() {
    try {
        $cols = ['order_id', 'mitra_id', 'klien_id', 'from_user_id', 'to_user_id'];
        foreach ($cols as $col) {
            try {
                \Illuminate\Support\Facades\DB::statement("ALTER TABLE feedback ALTER COLUMN {$col} DROP NOT NULL");
            } catch (\Exception $e) {}
        }
        return response()->json(['success' => true, 'message' => 'Semua kolom feedback sekarang nullable!']);
    } catch (\Exception $e) {
        return response()->json(['success' => false, 'message' => $e->getMessage()]);
    }
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
