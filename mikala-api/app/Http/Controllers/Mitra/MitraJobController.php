<?php

namespace App\Http\Controllers\Mitra;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;

class MitraJobController extends Controller
{
    /**
     * List assigned jobs/orders
     */
    public function index(Request $request)
    {
        try {
            $user = $request->user();
            $mitra = $user->mitra;

            if (!$mitra) {
                return response()->json([
                    'success' => false,
                    'message' => 'Mitra profile not found'
                ], 404);
            }

            $query = Order::where('mitra_id', $mitra->id)
                ->with(['klien.user', 'pasien']);

            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            $orders = $query->orderBy('tanggal_mulai', 'desc')->paginate(15);

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
                'message' => 'Failed to retrieve jobs: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Show job detail
     */
    public function show(Request $request, $id)
    {
        try {
            $user = $request->user();
            $mitra = $user->mitra;

            $order = Order::where('id', $id)
                ->where('mitra_id', $mitra->id)
                ->with(['klien.user', 'pasien', 'feedbacks'])
                ->firstOrFail();

            return response()->json([
                'success' => true,
                'data' => $order
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Job not found or access denied'
            ], 404);
        }
    }

    /**
     * Update job status (accept/complete)
     */
    public function updateStatus(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|in:active,completed',
            'notes' => 'nullable|string',
        ]);

        try {
            $user = $request->user();
            $mitra = $user->mitra;

            $order = Order::where('id', $id)
                ->where('mitra_id', $mitra->id)
                ->firstOrFail();

            // Validate status transition
            if ($order->status === 'pending' && $request->status === 'active') {
                // Accept job
                $order->status = 'active';
                $order->started_at = now();
            } elseif ($order->status === 'active' && $request->status === 'completed') {
                // Complete job
                $order->status = 'completed';
                $order->completed_at = now();
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid status transition'
                ], 400);
            }

            if ($request->has('notes')) {
                $order->mitra_notes = $request->notes;
            }

            $order->save();

            return response()->json([
                'success' => true,
                'message' => 'Job status updated successfully',
                'data' => $order
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update job status: ' . $e->getMessage()
            ], 500);
        }
    }
}
