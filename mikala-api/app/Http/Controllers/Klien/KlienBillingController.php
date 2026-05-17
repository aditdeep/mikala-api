<?php

namespace App\Http\Controllers\Klien;

use App\Http\Controllers\Controller;
use App\Models\Notifikasi;
use App\Models\Tagihan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class KlienBillingController extends Controller
{
    /**
     * Get current klien's invoices (tagihan)
     */
    public function index(Request $request)
    {
        try {
            $klien = auth()->user()->klien;

            if (!$klien) {
                return response()->json([
                    'success' => false,
                    'message' => 'Klien profile not found'
                ], 404);
            }

            $query = Tagihan::where('klien_id', $klien->id)
                ->with(['order', 'order.pasien']);

            // Filter by status
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            // Filter overdue
            if ($request->has('overdue') && $request->overdue == 'true') {
                $query->overdue();
            }

            // Order by most recent first
            $query->orderBy('created_at', 'desc');

            $tagihan = $query->paginate(15);

            return response()->json([
                'success' => true,
                'data' => $tagihan
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve invoices',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get single invoice detail
     */
    public function show(Request $request, $id)
    {
        try {
            $klien = auth()->user()->klien;

            if (!$klien) {
                return response()->json([
                    'success' => false,
                    'message' => 'Klien profile not found'
                ], 404);
            }

            $tagihan = Tagihan::with(['order', 'order.pasien', 'order.mitra', 'order.mitra.user'])
                ->where('id', $id)
                ->where('klien_id', $klien->id)
                ->first();

            if (!$tagihan) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invoice not found or unauthorized'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $tagihan
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve invoice',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Process payment for invoice
     */
    public function bayar(Request $request, $id)
    {
        try {
            $request->validate([
                'method'           => 'nullable|string',
                'metode_pembayaran'=> 'nullable|string',
            ]);
            // Support both 'method' and 'metode_pembayaran'
            $metode = $request->method ?? $request->metode_pembayaran ?? 'transfer';

            $klien = auth()->user()->klien;

            if (!$klien) {
                return response()->json([
                    'success' => false,
                    'message' => 'Klien profile not found'
                ], 404);
            }

            $tagihan = Tagihan::where('id', $id)
                ->where('klien_id', $klien->id)
                ->first();

            if (!$tagihan) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invoice not found or unauthorized'
                ], 404);
            }

            if ($tagihan->status === 'paid') {
                return response()->json([
                    'success' => false,
                    'message' => 'Invoice already paid'
                ], 400);
            }

            DB::beginTransaction();

            // Handle file upload
            $buktiPath = null;
            if ($request->hasFile('bukti_transfer')) {
                $file = $request->file('bukti_transfer');
                $fileName = 'payment_' . $tagihan->invoice_number . '_' . time() . '.' . $file->getClientOriginalExtension();
                $buktiPath = $file->storeAs('payments', $fileName, 'public');
            }

            // Update tagihan
            $tagihan->update([
                'metode_pembayaran' => $metode,
                'bukti_transfer' => $buktiPath,
                'status' => 'paid',
                'jumlah_bayar' => $tagihan->total,
                'sisa' => 0,
                'paid_at' => now(),
            ]);

            // Create notification for finance team
            $financeUsers = \App\Models\User::where('role', 'finance')->get();
            foreach ($financeUsers as $user) {
                Notifikasi::create([
                    'user_id' => $user->id,
                    'title' => 'Payment Received',
                    'message' => "Payment received for invoice {$tagihan->invoice_number} from {$klien->nama_lengkap}",
                    'type' => 'payment',
                    'related_type' => 'App\Models\Tagihan',
                    'related_id' => $tagihan->id,
                    'is_read' => false,
                ]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Payment submitted successfully',
                'data' => $tagihan->fresh()
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to process payment',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
