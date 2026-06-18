<?php

namespace App\Http\Controllers\Internal;

use App\Http\Controllers\Controller;
use App\Models\Tagihan;
use App\Models\Payroll;
use App\Models\JurnalKeuangan;
use App\Models\Order;
use App\Models\Mitra;
use App\Services\BillingService;
use App\Services\PayrollService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FinanceController extends Controller
{
    protected $billingService;
    protected $payrollService;

    public function __construct(BillingService $billingService, PayrollService $payrollService)
    {
        $this->billingService = $billingService;
        $this->payrollService = $payrollService;
    }

    // ========== TAGIHAN (INVOICES) ==========

    /**
     * List invoices
     */
    public function indexTagihan(Request $request)
    {
        try {
            $query = Tagihan::with(['klien.user', 'order.klien.user']);

            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            if ($request->has('overdue') && $request->overdue) {
                $query->where('status', 'pending')
                    ->where('due_date', '<', now());
            }

            $tagihan = $query->orderBy('created_at', 'desc')->paginate(15);

            return response()->json([
                'success' => true,
                'data' => $tagihan->items(),
                'pagination' => [
                    'total' => $tagihan->total(),
                    'per_page' => $tagihan->perPage(),
                    'current_page' => $tagihan->currentPage(),
                    'last_page' => $tagihan->lastPage()
                ]
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve invoices: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generate invoice for order
     */
    public function storeTagihan(Request $request)
    {
        $request->validate([
            'klien_id' => 'nullable|exists:klien,id',
            'tanggal_jatuh_tempo' => 'required|date',
            'subtotal' => 'required|numeric|min:0',
            'pajak' => 'nullable|numeric|min:0',
            'diskon' => 'nullable|numeric|min:0',
            'catatan' => 'nullable|string',
        ]);

        try {
            $subtotal = $request->subtotal;
            $pajak = $request->pajak ?? 0;
            $diskon = $request->diskon ?? 0;
            $total = $subtotal + $pajak - $diskon;

            $tagihan = Tagihan::create([
                'invoice_number' => 'INV-' . date('Ymd') . '-' . str_pad(Tagihan::count() + 1, 4, '0', STR_PAD_LEFT),
                'klien_id' => $request->klien_id ?? 1,
                'order_id' => null,
                'tanggal_invoice' => now()->toDateString(),
                'tanggal_jatuh_tempo' => $request->tanggal_jatuh_tempo,
                'subtotal' => $subtotal,
                'pajak' => $pajak,
                'diskon' => $diskon,
                'total' => $total,
                'jumlah_bayar' => 0,
                'sisa' => $total,
                'status' => 'unpaid',
                'catatan' => $request->catatan,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Tagihan berhasil dibuat',
                'data' => $tagihan
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal membuat tagihan: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Show invoice detail
     */
    public function showTagihan($id)
    {
        try {
            $tagihan = Tagihan::with(['order.klien.user', 'order.mitra.user'])->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $tagihan
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Invoice not found'
            ], 404);
        }
    }

    /**
     * Update invoice status
     */
    public function updateStatusTagihan(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|in:pending,paid,overdue,cancelled',
            'payment_method' => 'nullable|string',
            'payment_proof' => 'nullable|string',
        ]);

        try {
            $tagihan = Tagihan::findOrFail($id);
            $tagihan->status = $request->status;
            
            if ($request->status === 'paid') {
                try { $tagihan->paid_at = now(); } catch (\Exception $e) {}
                try { $tagihan->metode_pembayaran = $request->payment_method ?? 'transfer'; } catch (\Exception $e) {}

                // Create journal entry for income
                $kode = 'JRN-'.date('Ymd').'-'.str_pad(JurnalKeuangan::count()+1, 4, '0', STR_PAD_LEFT);
                try {
                    JurnalKeuangan::create([
                        'kode_transaksi' => $kode,
                        'tanggal'    => now(),
                        'tipe'       => 'income',
                        'kategori'   => 'tagihan',
                        'jumlah'     => $tagihan->total ?? $tagihan->total_amount ?? 0,
                        'deskripsi'  => "Pembayaran tagihan #{$tagihan->id}",
                        'created_by' => $request->user()->id,
                        'related_type' => 'App\Models\Tagihan',
                        'related_id'   => $tagihan->id,
                    ]);
                } catch (\Exception $je) {}
            }
            
            $tagihan->save();

            return response()->json([
                'success' => true,
                'message' => 'Invoice status updated successfully',
                'data' => $tagihan
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update invoice status: ' . $e->getMessage()
            ], 500);
        }
    }

    // ========== PAYROLL ==========

    /**
     * List payroll
     */
    public function indexPayroll(Request $request)
    {
        try {
            $query = Payroll::with(['mitra.user']);

            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            if ($request->has('periode')) {
                $query->where('periode_mulai', 'like', $request->periode.'%');
            }

            $payroll = $query->orderBy('periode_mulai', 'desc')->paginate(15);

            return response()->json([
                'success' => true,
                'data' => $payroll->items(),
                'pagination' => [
                    'total' => $payroll->total(),
                    'per_page' => $payroll->perPage(),
                    'current_page' => $payroll->currentPage(),
                    'last_page' => $payroll->lastPage()
                ]
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve payroll: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generate monthly payroll
     */
    public function generatePayroll(Request $request)
    {
        $request->validate(['periode' => 'required|date_format:Y-m']);

        try {
            [$tahun, $bulan] = explode('-', $request->periode);
            $periodeStart = \Carbon\Carbon::createFromDate($tahun, $bulan, 1)->startOfMonth();
            $periodeEnd   = \Carbon\Carbon::createFromDate($tahun, $bulan, 1)->endOfMonth();
            $hariDiBulan  = $periodeStart->daysInMonth;

            // Settings dari payroll_settings
            $rateCutiDefault = floatval(\DB::table('payroll_settings')->where('key','rate_cuti_default')->value('value') ?? 500000);
            $maxCutiPerBulan = intval(\DB::table('payroll_settings')->where('key','max_cuti_per_bulan')->value('value') ?? 2);

            // Order aktif di periode
            $orders = \App\Models\Order::whereIn('status', ['in_progress','completed','active'])
                ->where('tanggal_mulai', '<=', $periodeEnd)
                ->where(function($q) use ($periodeStart) {
                    $q->whereNull('tanggal_selesai')
                      ->orWhere('tanggal_selesai', '>=', $periodeStart);
                })
                ->whereNotNull('mitra_id')
                ->with('mitra')
                ->get();

            $generated = [];
            foreach ($orders as $order) {
                $mitra = $order->mitra;
                if (!$mitra) continue;

                // Hari kerja prorata
                $mulai   = max(\Carbon\Carbon::parse($order->tanggal_mulai), $periodeStart);
                $selesai = $order->tanggal_selesai
                    ? min(\Carbon\Carbon::parse($order->tanggal_selesai), $periodeEnd)
                    : $periodeEnd;
                $jumlahHari = max(0, $mulai->diffInDays($selesai) + 1);
                if ($jumlahHari == 0) continue;

                // Rate bulanan: price_rate = harga/bulan → bagi jumlah hari di bulan tsb
                $priceRateBulanan = floatval($mitra->price_rate ?? 0);
                $tarifPerHari = $hariDiBulan > 0 ? ($priceRateBulanan / $hariDiBulan) : 0;
                if ($tarifPerHari == 0) $tarifPerHari = 150000; // fallback

                $gajiPokok = $tarifPerHari * $jumlahHari;

                // Hitung cuti approved di periode
                $hariCuti = \App\Models\Cuti::where('mitra_id', $mitra->id)
                    ->where('status', 'approved')
                    ->whereBetween('tanggal_mulai', [$periodeStart, $periodeEnd])
                    ->sum('jumlah_hari');
                $hariCuti = min($hariCuti, $maxCutiPerBulan);
                $uangCuti = $hariCuti * $rateCutiDefault;

                // Potongan kasbon — yang approved & belum dipotong
                $kasbonAktif = \DB::table('mitra_kasbon')
                    ->where('mitra_id', $mitra->id)
                    ->where('status', 'approved')
                    ->whereNull('paid_at')
                    ->sum('jumlah');
                $potonganKasbon = floatval($kasbonAktif);

                // Potongan kredit pelatihan
                $kredit = \DB::table('mitra_kredit_pelatihan')
                    ->where('mitra_id', $mitra->id)
                    ->where('status', 'active')
                    ->first();
                $potonganKredit = 0;
                if ($kredit) {
                    $cicilan = floatval($kredit->cicilan_per_job ?? 0);
                    $sisa    = floatval($kredit->sisa_tagihan ?? 0);
                    $potonganKredit = min($cicilan, $sisa);
                }

                $gajiKotor = $gajiPokok + $uangCuti;
                $totalPotongan = $potonganKasbon + $potonganKredit;
                $total = $gajiKotor - $totalPotongan;

                $payrollNumber = 'PAY-'.date('Ym').'-'.str_pad(\App\Models\Payroll::count()+1, 4, '0', STR_PAD_LEFT);

                $payroll = \App\Models\Payroll::updateOrCreate(
                    ['order_id' => $order->id, 'mitra_id' => $mitra->id, 'periode_mulai' => $periodeStart],
                    [
                        'payroll_number'    => $payrollNumber,
                        'periode_selesai'   => $periodeEnd,
                        'jumlah_hari_kerja' => $jumlahHari,
                        'tarif_per_hari'    => $tarifPerHari,
                        'gaji_pokok'        => $gajiPokok,
                        'hari_cuti'         => $hariCuti,
                        'rate_cuti'         => $rateCutiDefault,
                        'uang_cuti'         => $uangCuti,
                        'bonus'             => 0,
                        'potongan_kasbon'   => $potonganKasbon,
                        'potongan_kredit'   => $potonganKredit,
                        'adjustment'        => 0,
                        'potongan'          => $totalPotongan,
                        'total'             => $total,
                        'status'            => 'draft',
                        'catatan'           => 'Periode '.$request->periode.' - Order #'.($order->order_number ?? $order->id),
                    ]
                );
                $generated[] = $payroll;

                // Notif realtime ke mitra: payroll baru di-generate
                if ($mitra->user_id) {
                    \App\Services\NotifikasiService::send(
                        $mitra->user_id,
                        'payroll',
                        'Slip Gaji Tersedia 💰',
                        "Slip gaji periode " . $request->periode . " sudah dibuat. Total: Rp " . number_format($total, 0, ',', '.') . ". Status: menunggu approval.",
                        ['related_type' => 'payroll', 'related_id' => $payroll->id]
                    );
                }
            }

            return response()->json([
                'success' => true,
                'message' => count($generated).' payroll generated',
                'data' => ['total_generated' => count($generated), 'payrolls' => $generated],
            ], 201);
        } catch (\Exception $e) {
            return response()->json(['success'=>false,'message'=>'Failed: '.$e->getMessage()], 500);
        }
    }

    /**
     * Adjust payroll manual (Finance only)
     */
    public function adjustPayroll(Request $request, $id)
    {
        $request->validate([
            'jumlah_hari_kerja' => 'nullable|integer|min:0',
            'hari_cuti'         => 'nullable|integer|min:0',
            'rate_cuti'         => 'nullable|numeric|min:0',
            'tarif_per_hari'    => 'nullable|numeric|min:0',
            'bonus'             => 'nullable|numeric|min:0',
            'potongan_kasbon'   => 'nullable|numeric|min:0',
            'potongan_kredit'   => 'nullable|numeric|min:0',
            'adjustment'        => 'nullable|numeric',
            'catatan_adjustment'=> 'nullable|string|max:500',
        ]);

        $payroll = \App\Models\Payroll::findOrFail($id);
        if (in_array($payroll->status, ['paid'])) {
            return response()->json(['success'=>false,'message'=>'Payroll yang sudah dibayar tidak bisa diubah'], 400);
        }

        $data = $request->only([
            'jumlah_hari_kerja','hari_cuti','rate_cuti','tarif_per_hari',
            'bonus','potongan_kasbon','potongan_kredit','adjustment','catatan_adjustment'
        ]);

        // Recalculate
        $merged = array_merge($payroll->toArray(), array_filter($data, fn($v) => $v !== null));
        $gajiPokok = floatval($merged['tarif_per_hari'] ?? 0) * intval($merged['jumlah_hari_kerja'] ?? 0);
        $uangCuti  = intval($merged['hari_cuti'] ?? 0) * floatval($merged['rate_cuti'] ?? 0);
        $gajiKotor = $gajiPokok + $uangCuti + floatval($merged['bonus'] ?? 0);
        $totalPotongan = floatval($merged['potongan_kasbon'] ?? 0) + floatval($merged['potongan_kredit'] ?? 0);
        $total = $gajiKotor - $totalPotongan + floatval($merged['adjustment'] ?? 0);

        $data['gaji_pokok'] = $gajiPokok;
        $data['uang_cuti']  = $uangCuti;
        $data['potongan']   = $totalPotongan;
        $data['total']      = $total;

        $payroll->update($data);

        return response()->json(['success'=>true,'data'=>$payroll->fresh()]);
    }

    /**
     * Approve payroll
     */
    public function approvePayroll($id)
    {
        $payroll = \App\Models\Payroll::findOrFail($id);
        if ($payroll->status !== 'draft') {
            return response()->json(['success'=>false,'message'=>'Hanya draft yang bisa di-approve'], 400);
        }
        $payroll->update([
            'status'      => 'approved',
            'approved_by' => auth()->id(),
            'approved_at' => now(),
        ]);
        return response()->json(['success'=>true,'data'=>$payroll->fresh()]);
    }

    /**
     * Mark as paid
     */
    public function markPaid($id)
    {
        $payroll = \App\Models\Payroll::findOrFail($id);
        if ($payroll->status !== 'approved') {
            return response()->json(['success'=>false,'message'=>'Harus approved dulu'], 400);
        }
        $payroll->update([
            'status'  => 'paid',
            'paid_at' => now(),
        ]);

        // Notif realtime ke mitra: gaji sudah dibayar
        $payroll->loadMissing('mitra');
        if ($payroll->mitra && $payroll->mitra->user_id) {
            \App\Services\NotifikasiService::send(
                $payroll->mitra->user_id,
                'payroll',
                'Gaji Sudah Dibayar ✅',
                "Gaji Anda " . $payroll->payroll_number . " sebesar Rp " . number_format($payroll->total, 0, ',', '.') . " telah ditransfer.",
                ['related_type' => 'payroll', 'related_id' => $payroll->id]
            );
        }

        // Mark kasbon as paid jika ada potongan
        if (floatval($payroll->potongan_kasbon) > 0) {
            \DB::table('mitra_kasbon')
                ->where('mitra_id', $payroll->mitra_id)
                ->where('status', 'approved')
                ->whereNull('paid_at')
                ->update(['paid_at' => now(), 'status' => 'paid', 'updated_at' => now()]);
        }

        // Update kredit pelatihan
        if (floatval($payroll->potongan_kredit) > 0) {
            $kredit = \DB::table('mitra_kredit_pelatihan')
                ->where('mitra_id', $payroll->mitra_id)
                ->where('status', 'active')
                ->first();
            if ($kredit) {
                $newTerbayar = floatval($kredit->total_terbayar) + floatval($payroll->potongan_kredit);
                $newSisa     = max(0, floatval($kredit->total_biaya) - $newTerbayar);
                $newStatus   = $newSisa == 0 ? 'lunas' : 'active';
                \DB::table('mitra_kredit_pelatihan')->where('id', $kredit->id)->update([
                    'total_terbayar' => $newTerbayar,
                    'sisa_tagihan'   => $newSisa,
                    'status'         => $newStatus,
                    'updated_at'     => now(),
                ]);
            }
        }

        return response()->json(['success'=>true,'data'=>$payroll->fresh()]);
    }

    /**
     * Cuti management — Finance side
     */
    public function indexCuti(Request $request)
    {
        $query = \App\Models\Cuti::with(['mitra:id,nama_lengkap,foto_url', 'approver:id,name']);
        if ($request->has('status')) $query->where('status', $request->status);
        $cuti = $query->orderBy('created_at','desc')->paginate(20);
        return response()->json(['success'=>true,'data'=>$cuti]);
    }

    public function approveCuti(Request $request, $id)
    {
        $request->validate([
            'status'        => 'required|in:approved,rejected',
            'catatan_admin' => 'nullable|string|max:500',
        ]);
        $cuti = \App\Models\Cuti::with('mitra.user')->findOrFail($id);
        $cuti->update([
            'status'        => $request->status,
            'approved_by'   => auth()->id(),
            'approved_at'   => now(),
            'catatan_admin' => $request->catatan_admin,
        ]);

        // Broadcast notifikasi realtime ke mitra
        $userId = $cuti->mitra?->user_id;
        if ($userId) {
            $isApproved = $request->status === 'approved';
            \App\Services\NotifikasiService::send(
                $userId,
                'cuti',
                $isApproved ? 'Cuti Disetujui ✅' : 'Cuti Ditolak ❌',
                $isApproved
                    ? "Pengajuan cuti Anda tanggal {$cuti->tanggal_mulai->format('d M Y')} - {$cuti->tanggal_selesai->format('d M Y')} disetujui"
                    : "Pengajuan cuti Anda ditolak. " . ($request->catatan_admin ? "Catatan: {$request->catatan_admin}" : ''),
                [
                    'related_type' => 'cuti',
                    'related_id'   => $cuti->id,
                ]
            );
        }

        return response()->json(['success'=>true,'data'=>$cuti->fresh()]);
    }

    /**
     * Payroll settings
     */
    public function getPayrollSettings()
    {
        $settings = \DB::table('payroll_settings')->get()->keyBy('key');
        return response()->json(['success'=>true,'data'=>$settings]);
    }

    public function updatePayrollSettings(Request $request)
    {
        $request->validate(['settings' => 'required|array']);
        foreach ($request->settings as $key => $value) {
            \DB::table('payroll_settings')->updateOrInsert(
                ['key' => $key],
                ['value' => $value, 'updated_at' => now()]
            );
        }
        return response()->json(['success'=>true,'message'=>'Settings updated']);
    }

    /**
     * Show payroll detail
     */
    public function showPayroll($id)
    {
        try {
            $payroll = Payroll::with(['mitra.user'])->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $payroll
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Payroll not found'
            ], 404);
        }
    }

    /**
     * Update payroll status
     */
    public function updateStatusPayroll(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|in:pending,processed,paid,cancelled',
            'payment_method' => 'nullable|string',
        ]);

        try {
            $payroll = Payroll::findOrFail($id);
            $payroll->status = $request->status;
            
            if ($request->status === 'paid') {
                $payroll->paid_at = now();
                $payroll->metode_pembayaran = $request->payment_method ?? 'transfer';
                $payroll->approved_at = now();
                $payroll->approved_by = $request->user()->id;

                // Create journal entry
                try {
                    \App\Models\JurnalKeuangan::create([
                        'tanggal'    => now(),
                        'tipe'       => 'outcome',
                        'kategori'   => 'payroll',
                        'deskripsi'  => 'Payroll #'.$payroll->payroll_number.' - Mitra #'.$payroll->mitra_id,
                        'jumlah'     => $payroll->total,
                        'created_by' => $request->user()->id,
                    ]);
                } catch (\Exception $je) {
                    // Jurnal gagal tidak batalkan update status
                }
            }
            
            $payroll->save();

            return response()->json([
                'success' => true,
                'message' => 'Payroll status updated successfully',
                'data' => $payroll
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update payroll status: ' . $e->getMessage()
            ], 500);
        }
    }

    // ========== JOURNAL ==========

    /**
     * List financial journal entries
     */
    public function indexJurnal(Request $request)
    {
        try {
            $query = JurnalKeuangan::query();

            if ($request->has('kategori')) {
                $query->where('kategori', $request->kategori);
            }

            if ($request->has('start_date') && $request->has('end_date')) {
                $query->whereBetween('tanggal', [$request->start_date, $request->end_date]);
            }

            $jurnal = $query->orderBy('tanggal', 'desc')->paginate(15);

            return response()->json([
                'success' => true,
                'data' => $jurnal->items(),
                'pagination' => [
                    'total' => $jurnal->total(),
                    'per_page' => $jurnal->perPage(),
                    'current_page' => $jurnal->currentPage(),
                    'last_page' => $jurnal->lastPage()
                ]
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve journal: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create journal entry
     */
    public function storeJurnal(Request $request)
    {
        $request->validate([
            'tanggal'   => 'required|date',
            'tipe'      => 'required|in:income,outcome',
            'kategori'  => 'required|string|max:100',
            'deskripsi' => 'nullable|string',
            'jumlah'    => 'required|numeric|min:0',
        ]);

        try {
            $kode = 'JRN-'.date('Ymd').'-'.str_pad(\App\Models\JurnalKeuangan::count()+1, 4, '0', STR_PAD_LEFT);

            $jurnal = JurnalKeuangan::create([
                'kode_transaksi' => $kode,
                'tanggal'        => $request->tanggal,
                'tipe'           => $request->tipe,
                'kategori'       => $request->kategori,
                'jumlah'         => $request->jumlah,
                'deskripsi'      => $request->deskripsi,
                'created_by'     => $request->user()->id,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Journal entry created successfully',
                'data' => $jurnal
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create journal entry: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Check balance
     */
    public function balancing(Request $request)
    {
        try {
            $income = JurnalKeuangan::where('kategori', 'income')->sum('debit');
            $outcome = JurnalKeuangan::where('kategori', 'outcome')->sum('kredit');
            $currentBalance = JurnalKeuangan::where("tipe","income")->sum("jumlah") - JurnalKeuangan::where("tipe","outcome")->sum("jumlah");

            return response()->json([
                'success' => true,
                'data' => [
                    'total_income' => $income,
                    'total_outcome' => $outcome,
                    'current_balance' => $currentBalance,
                    'calculated_balance' => $income - $outcome,
                    'is_balanced' => abs($currentBalance - ($income - $outcome)) < 0.01
                ]
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to check balance: ' . $e->getMessage()
            ], 500);
        }
    }

    // ========== REPORTS ==========

    /**
     * Income report
     */
    public function reportIncome(Request $request)
    {
        try {
            $startDate = $request->input('start_date', now()->startOfMonth());
            $endDate = $request->input('end_date', now()->endOfMonth());

            $income = JurnalKeuangan::where('kategori', 'income')
                ->whereBetween('tanggal', [$startDate, $endDate])
                ->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'period' => ['start' => $startDate, 'end' => $endDate],
                    'total_income' => $income->sum('debit'),
                    'entries' => $income
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
     * Outcome report
     */
    public function reportOutcome(Request $request)
    {
        try {
            $startDate = $request->input('start_date', now()->startOfMonth());
            $endDate = $request->input('end_date', now()->endOfMonth());

            $outcome = JurnalKeuangan::where('kategori', 'outcome')
                ->whereBetween('tanggal', [$startDate, $endDate])
                ->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'period' => ['start' => $startDate, 'end' => $endDate],
                    'total_outcome' => $outcome->sum('kredit'),
                    'entries' => $outcome
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
     * Current balance report
     */
    public function reportSaldo(Request $request)
    {
        try {
            $currentBalance = JurnalKeuangan::where("tipe","income")->sum("jumlah") - JurnalKeuangan::where("tipe","outcome")->sum("jumlah");
            $lastEntry = JurnalKeuangan::orderBy('tanggal', 'desc')->first();

            return response()->json([
                'success' => true,
                'data' => [
                    'current_balance' => $currentBalance,
                    'last_updated' => $lastEntry?->tanggal,
                    'last_entry' => $lastEntry
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
     * Receivables report (unpaid invoices)
     */
    public function reportPiutang(Request $request)
    {
        try {
            $piutang = Tagihan::where('status', 'pending')
                ->with(['order.klien.user'])
                ->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'total_piutang' => $piutang->sum('total'),
                    'count' => $piutang->count(),
                    'invoices' => $piutang
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
     * Payables report (unpaid payroll)
     */
    public function reportUtang(Request $request)
    {
        try {
            $utang = Payroll::where('status', 'pending')
                ->with(['mitra.user'])
                ->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'total_utang' => $utang->sum('total'),
                    'count' => $utang->count(),
                    'payrolls' => $utang
                ]
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate report: ' . $e->getMessage()
            ], 500);
        }
    }
}
