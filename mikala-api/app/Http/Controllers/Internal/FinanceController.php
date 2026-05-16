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
            $query = Tagihan::with(['order.klien.user']);

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
                $tagihan->paid_at = now();
                $tagihan->payment_method = $request->payment_method;
                $tagihan->payment_proof = $request->payment_proof;

                // Create journal entry for income
                JurnalKeuangan::create([
                    'tanggal' => now(),
                    'kategori' => 'income',
                    'deskripsi' => "Payment received for invoice #{$tagihan->id}",
                    'debit' => $tagihan->total_amount,
                    'kredit' => 0,
                    'saldo' => JurnalKeuangan::getCurrentBalance() + $tagihan->total_amount,
                    'reference_type' => 'App\Models\Tagihan',
                    'reference_id' => $tagihan->id,
                ]);
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
        $request->validate([
            'periode' => 'required|date_format:Y-m',
        ]);

        try {
            [$tahun, $bulan] = explode('-', $request->periode);
            $periodeStart = \Carbon\Carbon::createFromDate($tahun, $bulan, 1)->startOfMonth();
            $periodeEnd   = \Carbon\Carbon::createFromDate($tahun, $bulan, 1)->endOfMonth();

            // Get semua mitra yang punya order in_progress di periode ini
            $orders = \App\Models\Order::whereIn('status', ['in_progress','completed'])
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

                // Hitung hari kerja di periode ini
                $mulai  = max(\Carbon\Carbon::parse($order->tanggal_mulai), $periodeStart);
                $selesai = $order->tanggal_selesai
                    ? min(\Carbon\Carbon::parse($order->tanggal_selesai), $periodeEnd)
                    : $periodeEnd;
                $jumlahHari = max(1, $mulai->diffInDays($selesai) + 1);

                // Hitung tarif - fallback ke berbagai field
                $tarifPerHari = floatval(
                    $order->harga_per_hari ??
                    $order->harga_per_shift ??
                    ($order->subtotal ? $order->subtotal / max(1, $jumlahHari) : 0)
                );
                // Kalau masih 0, pakai default 150000/hari
                if ($tarifPerHari == 0) $tarifPerHari = 150000;
                $gajiPokok = $tarifPerHari * $jumlahHari;
                $total     = $gajiPokok * 0.8;

                $payrollNumber = 'PAY-'.date('Ym').'-'.str_pad(\App\Models\Payroll::count()+1, 4, '0', STR_PAD_LEFT);

                $payroll = \App\Models\Payroll::updateOrCreate(
                    ['order_id' => $order->id, 'mitra_id' => $mitra->id, 'periode_mulai' => $periodeStart],
                    [
                        'payroll_number'    => $payrollNumber,
                        'periode_selesai'   => $periodeEnd,
                        'jumlah_hari_kerja' => $jumlahHari,
                        'tarif_per_hari'    => $tarifPerHari,
                        'gaji_pokok'        => $gajiPokok,
                        'bonus'             => 0,
                        'potongan'          => 0,
                        'total'             => $total,
                        'status'            => 'pending',
                        'catatan'           => 'Generate periode '.$request->periode.' - Order #'.$order->order_number,
                    ]
                );
                $generated[] = $payroll;
            }

            return response()->json([
                'success' => true,
                'message' => 'Payroll generated successfully',
                'data' => [
                    'total_generated' => count($generated),
                    
                    'payrolls' => $generated
                ]
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate payroll: ' . $e->getMessage()
            ], 500);
        }
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
            'tanggal' => 'required|date',
            'kategori' => 'required|in:income,outcome',
            'deskripsi' => 'required|string',
            'debit' => 'required|numeric|min:0',
            'kredit' => 'required|numeric|min:0',
        ]);

        try {
            $currentBalance = JurnalKeuangan::getCurrentBalance();
            $newBalance = $currentBalance + $request->debit - $request->kredit;

            $jurnal = JurnalKeuangan::create([
                'tanggal' => $request->tanggal,
                'kategori' => $request->kategori,
                'deskripsi' => $request->deskripsi,
                'debit' => $request->debit,
                'kredit' => $request->kredit,
                'saldo' => $newBalance,
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
            $currentBalance = JurnalKeuangan::getCurrentBalance();

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
            $currentBalance = JurnalKeuangan::getCurrentBalance();
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
                    'total_piutang' => $piutang->sum('total_amount'),
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
                    'total_utang' => $utang->sum('net_salary'),
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
