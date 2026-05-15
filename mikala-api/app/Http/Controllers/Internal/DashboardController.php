<?php

namespace App\Http\Controllers\Internal;

use App\Http\Controllers\Controller;
use App\Models\Mitra;
use App\Models\Klien;
use App\Models\Order;
use App\Models\Tagihan;
use App\Models\Notifikasi;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    /**
     * Dashboard summary statistics
     */
    public function summary(Request $request)
    {
        try {
            $stats = [
                'total_mitra' => Mitra::count(),
                'mitra_active' => Mitra::where('status', 'available')->count(),
                'mitra_training' => Mitra::where('status', 'on_job')->count(),
                'mitra_available' => Mitra::where('status', 'available')->count(),
                
                'total_klien' => Klien::count(),
                'klien_active' => Klien::where('status', 'active')->count(),
                
                'total_orders' => Order::count(),
                'orders_active' => Order::where('status', 'active')->count(),
                'orders_completed' => Order::where('status', 'completed')->count(),
                'orders_pending' => Order::where('status', 'pending')->count(),
                
                'total_revenue' => Tagihan::where('status', 'paid')->sum('total'),
                'pending_revenue' => Tagihan::where('status', 'pending')->sum('total'),
                'overdue_invoices' => Tagihan::where('status', 'pending')
                    ->where('due_date', '<', now())
                    ->count(),
                
                'pending_items' => [
                    'new_applications' => Mitra::where('status', 'training')->count(),
                    'training_pending' => Mitra::where('training_status', 'pending')->count(),
                    'orders_pending_assignment' => Order::whereNull('mitra_id')->count(),
                    'unpaid_invoices' => Tagihan::where('status', 'pending')->count(),
                ],
            ];

            return response()->json([
                'success' => true,
                'message' => 'Dashboard summary retrieved successfully',
                'data' => $stats
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve dashboard summary: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get notifications for current user
     */
    public function notifikasi(Request $request)
    {
        try {
            $notifications = Notifikasi::where('user_id', $request->user()->id)
                ->where('is_read', false)
                ->orderBy('created_at', 'desc')
                ->paginate(15);

            return response()->json([
                'success' => true,
                'data' => $notifications->items(),
                'pagination' => [
                    'total' => $notifications->total(),
                    'per_page' => $notifications->perPage(),
                    'current_page' => $notifications->currentPage(),
                    'last_page' => $notifications->lastPage()
                ]
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve notifications: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mark notification as read
     */
    public function markNotificationRead(Request $request, $id)
    {
        try {
            $notification = Notifikasi::where('id', $id)
                ->where('user_id', $request->user()->id)
                ->firstOrFail();

            $notification->is_read = true;
            $notification->read_at = now();
            $notification->save();

            return response()->json([
                'success' => true,
                'message' => 'Notification marked as read',
                'data' => $notification
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to mark notification as read: ' . $e->getMessage()
            ], 404);
        }
    }
}
