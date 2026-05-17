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


// TEMPORARY - Fix orders nullable columns
Route::get('/fix-orders-nullable', function() {
    try {
        $cols = ['pasien_id', 'mitra_id', 'tanggal_selesai', 'catatan', 'lokasi',
                 'tipe_layanan', 'layanan_type', 'deskripsi', 'durasi_shift', 'harga_per_shift',
                 'total_shift', 'total_amount', 'rating', 'feedback', 'alamat_layanan',
                 'kebutuhan_khusus', 'harga_per_jam', 'total_harga', 'jam_per_hari'];
        foreach ($cols as $col) {
            try {
                \Illuminate\Support\Facades\DB::statement("ALTER TABLE orders ALTER COLUMN {$col} DROP NOT NULL");
            } catch (\Exception $e) {}
        }
        return response()->json(['success' => true, 'message' => 'Orders columns nullable!']);
    } catch (\Exception $e) {
        return response()->json(['success' => false, 'message' => $e->getMessage()]);
    }
});


// TEMPORARY - Fix orders harga_per_hari nullable
Route::get('/fix-orders-nullable2', function() {
    try {
        $cols = ['harga_per_hari', 'harga_per_shift', 'total_shift', 'total_amount',
                 'total_harga', 'jam_per_hari', 'durasi_hari', 'alamat_layanan',
                 'kebutuhan_khusus', 'rating', 'feedback', 'catatan', 'lokasi'];
        foreach ($cols as $col) {
            try {
                \Illuminate\Support\Facades\DB::statement("ALTER TABLE orders ALTER COLUMN {$col} DROP NOT NULL");
            } catch (\Exception $e) {}
        }
        return response()->json(['success' => true, 'message' => 'Done!']);
    } catch (\Exception $e) {
        return response()->json(['success' => false, 'message' => $e->getMessage()]);
    }
});


// TEMPORARY - Fix ALL orders nullable
Route::get('/fix-orders-all', function() {
    try {
        $result = \Illuminate\Support\Facades\DB::select("
            SELECT column_name, is_nullable
            FROM information_schema.columns
            WHERE table_name = 'orders'
            AND table_schema = 'public'
            AND is_nullable = 'NO'
            AND column_name NOT IN ('id', 'order_number', 'klien_id', 'status', 'created_at', 'updated_at')
        ");
        $fixed = [];
        foreach ($result as $col) {
            try {
                \Illuminate\Support\Facades\DB::statement("ALTER TABLE orders ALTER COLUMN {$col->column_name} DROP NOT NULL");
                $fixed[] = $col->column_name;
            } catch (\Exception $e) {}
        }
        return response()->json(['success' => true, 'fixed' => $fixed]);
    } catch (\Exception $e) {
        return response()->json(['success' => false, 'message' => $e->getMessage()]);
    }
});


// TEMPORARY - Debug generate payroll
Route::get('/debug-payroll', function() {
    $periode = request('periode', '2026-04');
    [$tahun, $bulan] = explode('-', $periode);
    $start = \Carbon\Carbon::createFromDate($tahun, $bulan, 1)->startOfMonth();
    $end   = \Carbon\Carbon::createFromDate($tahun, $bulan, 1)->endOfMonth();

    $orders = \App\Models\Order::whereIn('status', ['in_progress','completed'])
        ->where('tanggal_mulai', '<=', $end)
        ->where(function($q) use ($start) {
            $q->whereNull('tanggal_selesai')->orWhere('tanggal_selesai', '>=', $start);
        })
        ->whereNotNull('mitra_id')
        ->get(['id','order_number','status','mitra_id','tanggal_mulai','tanggal_selesai','harga_per_hari']);

    return response()->json([
        'periode' => $periode,
        'start' => $start,
        'end' => $end,
        'orders_found' => $orders->count(),
        'orders' => $orders
    ]);
});


// TEMPORARY - Debug payroll create
Route::get('/debug-payroll-create', function() {
    $existing = \App\Models\Payroll::where('order_id', 13)->get(['id','order_id','mitra_id','periode_mulai','total','status']);
    return response()->json(['existing' => $existing, 'count' => $existing->count()]);
});


// TEMPORARY - Fix payroll total
Route::get('/fix-payroll-total', function() {
    $payroll = \App\Models\Payroll::find(1);
    if (!$payroll) return response()->json(['error' => 'not found']);
    
    $order = \App\Models\Order::find($payroll->order_id);
    $mulai   = \Carbon\Carbon::parse($order->tanggal_mulai);
    $selesai = \Carbon\Carbon::parse($order->tanggal_selesai ?? now());
    $hari    = $mulai->diffInDays($selesai) + 1;
    $tarif   = floatval($order->harga_per_hari ?? $order->harga_per_shift ?? 150000);
    $gaji    = $tarif * $hari;
    $total   = $gaji * 0.8;

    $payroll->update([
        'jumlah_hari_kerja' => $hari,
        'tarif_per_hari'    => $tarif,
        'gaji_pokok'        => $gaji,
        'total'             => $total,
        'catatan'           => 'Fixed - '.$hari.' hari x Rp '.number_format($tarif),
    ]);

    return response()->json(['success' => true, 'hari' => $hari, 'tarif' => $tarif, 'total' => $total, 'payroll' => $payroll->fresh()]);
});


// TEMPORARY - Check mitra table columns
Route::get('/check-mitra-columns', function() {
    $cols = \Illuminate\Support\Facades\DB::select("
        SELECT column_name FROM information_schema.columns
        WHERE table_name = 'mitra' AND table_schema = 'public'
        ORDER BY column_name
    ");
    return response()->json(['columns' => array_column($cols, 'column_name')]);
});


// TEMPORARY - Add bank columns to mitra table
Route::get('/add-mitra-bank-columns', function() {
    try {
        \Illuminate\Support\Facades\DB::statement("ALTER TABLE mitra ADD COLUMN IF NOT EXISTS bank_name VARCHAR(100)");
        \Illuminate\Support\Facades\DB::statement("ALTER TABLE mitra ADD COLUMN IF NOT EXISTS bank_account VARCHAR(50)");
        \Illuminate\Support\Facades\DB::statement("ALTER TABLE mitra ADD COLUMN IF NOT EXISTS bank_account_name VARCHAR(255)");
        return response()->json(['success' => true, 'message' => 'Bank columns added!']);
    } catch (\Exception $e) {
        return response()->json(['success' => false, 'message' => $e->getMessage()]);
    }
});


// TEMPORARY - Fix jurnal_keuangan constraints
Route::get('/fix-jurnal-constraints', function() {
    try {
        \Illuminate\Support\Facades\DB::statement("ALTER TABLE jurnal_keuangan DROP CONSTRAINT IF EXISTS jurnal_keuangan_kategori_check");
        \Illuminate\Support\Facades\DB::statement("ALTER TABLE jurnal_keuangan DROP CONSTRAINT IF EXISTS jurnal_keuangan_tipe_check");
        \Illuminate\Support\Facades\DB::statement("ALTER TABLE jurnal_keuangan ALTER COLUMN kategori TYPE VARCHAR(100)");
        return response()->json(['success' => true, 'message' => 'Constraints removed!']);
    } catch (\Exception $e) {
        return response()->json(['success' => false, 'message' => $e->getMessage()]);
    }
});


// TEMPORARY - Fix tagihan constraints
Route::get('/fix-tagihan-constraints', function() {
    try {
        \Illuminate\Support\Facades\DB::statement("ALTER TABLE tagihan DROP CONSTRAINT IF EXISTS tagihan_metode_pembayaran_check");
        \Illuminate\Support\Facades\DB::statement("ALTER TABLE tagihan DROP CONSTRAINT IF EXISTS tagihan_status_check");
        \Illuminate\Support\Facades\DB::statement("ALTER TABLE tagihan ALTER COLUMN metode_pembayaran TYPE VARCHAR(50)");
        \Illuminate\Support\Facades\DB::statement("ALTER TABLE tagihan ALTER COLUMN status TYPE VARCHAR(50)");
        return response()->json(['success' => true, 'message' => 'Tagihan constraints fixed!']);
    } catch (\Exception $e) {
        return response()->json(['success' => false, 'message' => $e->getMessage()]);
    }
});


// TEMPORARY - Fix notifikasi type constraint
Route::get('/fix-notifikasi-constraints', function() {
    try {
        \Illuminate\Support\Facades\DB::statement("ALTER TABLE notifikasi DROP CONSTRAINT IF EXISTS notifikasi_type_check");
        \Illuminate\Support\Facades\DB::statement("ALTER TABLE notifikasi ALTER COLUMN type TYPE VARCHAR(50)");
        return response()->json(['success' => true, 'message' => 'Notifikasi constraints fixed!']);
    } catch (\Exception $e) {
        return response()->json(['success' => false, 'message' => $e->getMessage()]);
    }
});


// TEMPORARY - Create kerjasama table
Route::get('/migrate-kerjasama', function() {
    try {
        \Illuminate\Support\Facades\DB::statement("
            CREATE TABLE IF NOT EXISTS kerjasama (
                id BIGSERIAL PRIMARY KEY,
                partner_name VARCHAR(255) NOT NULL,
                partner_type VARCHAR(100),
                contact_person VARCHAR(255),
                phone VARCHAR(20),
                email VARCHAR(255),
                notes TEXT,
                status VARCHAR(50) DEFAULT 'active',
                created_at TIMESTAMP DEFAULT NOW(),
                updated_at TIMESTAMP DEFAULT NOW(),
                deleted_at TIMESTAMP NULL
            )
        ");
        return response()->json(['success' => true, 'message' => 'Tabel kerjasama dibuat!']);
    } catch (\Exception $e) {
        return response()->json(['success' => false, 'message' => $e->getMessage()]);
    }
});

// TEMPORARY - Fix leads tipe_layanan column
Route::get('/fix-leads-columns', function() {
    try {
        \Illuminate\Support\Facades\DB::statement("ALTER TABLE leads ADD COLUMN IF NOT EXISTS tipe_layanan VARCHAR(100)");
        \Illuminate\Support\Facades\DB::statement("ALTER TABLE leads ADD COLUMN IF NOT EXISTS pesan TEXT");
        return response()->json(['success' => true, 'message' => 'Leads columns fixed!']);
    } catch (\Exception $e) {
        return response()->json(['success' => false, 'message' => $e->getMessage()]);
    }
});


// TEMPORARY - Add rekomendasi column to trainings
Route::get('/fix-training-columns', function() {
    try {
        \Illuminate\Support\Facades\DB::statement("ALTER TABLE trainings ADD COLUMN IF NOT EXISTS rekomendasi VARCHAR(50) DEFAULT 'lanjut'");
        return response()->json(['success' => true, 'message' => 'Training columns fixed!']);
    } catch (\Exception $e) {
        return response()->json(['success' => false, 'message' => $e->getMessage()]);
    }
});


// TEMPORARY - Fix mitra status based on active orders
Route::get('/fix-mitra-status', function() {
    // Set on_job untuk mitra yang punya order aktif
    $activeOrders = \App\Models\Order::whereIn('status', ['confirmed','in_progress'])->whereNotNull('mitra_id')->get();
    foreach ($activeOrders as $order) {
        \App\Models\Mitra::where('id', $order->mitra_id)->update(['status' => 'on_job']);
    }
    // Set available untuk mitra yang tidak punya order aktif
    $activeMitraIds = $activeOrders->pluck('mitra_id')->unique();
    \App\Models\Mitra::whereNotIn('id', $activeMitraIds)->where('status', 'on_job')->update(['status' => 'available']);
    return response()->json(['success' => true, 'fixed' => $activeMitraIds]);
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
