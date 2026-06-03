<?php

namespace App\Http\Controllers;

use App\Models\Notifikasi;
use Illuminate\Http\Request;

class NotifikasiController extends Controller
{
    /**
     * Get current user's notifications
     */
    public function index(Request $request)
    {
        try {
            $user = auth()->user();

            $query = Notifikasi::where('user_id', $user->id);

            // Filter by type
            if ($request->has('type')) {
                $query->where('type', $request->type);
            }

            // Filter by read status
            if ($request->has('is_read')) {
                $query->where('is_read', $request->is_read === 'true' || $request->is_read === '1');
            }

            // Order by most recent first
            $query->orderBy('created_at', 'desc');

            $notifications = $query->paginate(20);

            // Get unread count
            $unreadCount = Notifikasi::where('user_id', $user->id)
                ->where('is_read', false)
                ->count();

            return response()->json([
                'success' => true,
                'data' => $notifications,
                'unread_count' => $unreadCount
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve notifications',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mark single notification as read
     */
    public function markAsRead(Request $request, $id)
    {
        try {
            $user = auth()->user();

            $notification = Notifikasi::where('id', $id)
                ->where('user_id', $user->id)
                ->first();

            if (!$notification) {
                return response()->json([
                    'success' => false,
                    'message' => 'Notification not found or unauthorized'
                ], 404);
            }

            $notification->markAsRead();

            return response()->json([
                'success' => true,
                'message' => 'Notification marked as read',
                'data' => $notification->fresh()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to mark notification as read',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mark all user's notifications as read
     */
    public function markAllAsRead(Request $request)
    {
        try {
            $user = auth()->user();

            $updated = Notifikasi::where('user_id', $user->id)
                ->where('is_read', false)
                ->update([
                    'is_read' => true,
                    'read_at' => now()
                ]);

            return response()->json([
                'success' => true,
                'message' => 'All notifications marked as read',
                'updated_count' => $updated
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to mark all notifications as read',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get unread count only
     */
    public function unreadCount()
    {
        try {
            $user = auth()->user();

            $count = Notifikasi::where('user_id', $user->id)
                ->where('is_read', false)
                ->count();

            return response()->json([
                'success' => true,
                'unread_count' => $count
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get unread count',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Simpan Expo Push Token dari device
     */
    public function saveExpoPushToken(Request $request)
    {
        $request->validate(['expo_token' => 'required|string']);
        $user = auth()->user();
        $user->update(['fcm_token' => $request->expo_token]);
        return response()->json(['success' => true, 'message' => 'Push token saved']);
    }

    /**
     * Kirim push notification ke user tertentu (internal use)
     */
    public static function sendPush($userId, string $title, string $body, array $data = [])
    {
        try {
            $user = \App\Models\User::find($userId);
            if (!$user || !$user->fcm_token) return false;
            if (!str_starts_with($user->fcm_token, 'ExponentPushToken')) return false;

            $response = \Illuminate\Support\Facades\Http::post('https://exp.host/--/api/v2/push/send', [
                'to'    => $user->fcm_token,
                'title' => $title,
                'body'  => $body,
                'data'  => $data,
                'sound' => 'default',
                'badge' => 1,
            ]);

            // Simpan ke tabel notifikasi juga
            \App\Models\Notifikasi::create([
                'user_id'    => $userId,
                'title'      => $title,
                'body'       => $body,
                'type'       => $data['type'] ?? 'general',
                'data'       => json_encode($data),
                'is_read'    => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return $response->successful();
        } catch (\Exception $e) {
            \Log::error('Push notification error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Kirim push ke semua mitra (broadcast)
     */
    public function broadcastToMitra(Request $request)
    {
        $request->validate(['title' => 'required', 'body' => 'required']);
        $mitras = \App\Models\User::where('role', 'mitra')
            ->whereNotNull('fcm_token')
            ->where('fcm_token', 'like', 'ExponentPushToken%')
            ->get();

        $sent = 0;
        foreach ($mitras as $user) {
            $success = self::sendPush($user->id, $request->title, $request->body, ['type' => $request->type ?? 'broadcast']);
            if ($success) $sent++;
        }
        return response()->json(['success' => true, 'sent_to' => $sent]);
    }

}