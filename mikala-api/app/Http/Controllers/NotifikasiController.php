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
                $query->where('is_read', ($request->is_read === 'true' || $request->is_read === '1') ? 1 : 0);
            }

            // Order by most recent first
            $query->orderBy('created_at', 'desc');

            $notifications = $query->paginate(20);

            // Get unread count
            $unreadCount = Notifikasi::where('user_id', $user->id)
                ->whereRaw('is_read = 0')
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
                ->whereRaw('is_read = 0')
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
                ->whereRaw('is_read = 0')
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
        // 1 HP = 1 akun: lepas token ini dari akun lain yg pernah login di HP yg sama
        \App\Models\User::where('fcm_token', $request->expo_token)->where('id', '!=', $user->id)->update(['fcm_token' => null]);
        $user->update(['fcm_token' => $request->expo_token]);
        return response()->json(['success' => true, 'message' => 'Push token saved']);
    }

    /**
     * Kirim push notification Expo ke HP user (tanpa menyimpan ke tabel notifikasi --
     * penyimpanan lewat NotifikasiService::send(), yang juga memanggil fungsi ini).
     * Sebelumnya fungsi ini juga insert Notifikasi pakai kolom 'body'/'data' yang tidak ada,
     * sehingga insert selalu gagal dan fungsi selalu return false.
     */
    public static function sendPush($userId, string $title, string $body, array $data = []): bool
    {
        try {
            $user = \App\Models\User::find($userId);
            if (!$user || !$user->fcm_token) return false;
            if (!str_starts_with($user->fcm_token, 'ExponentPushToken')) return false;

            $type = $data['type'] ?? 'info';
            $channel = match (true) {
                in_array($type, ['order', 'job', 'order_assigned', 'penempatan'], true) => 'jobs',
                in_array($type, ['payroll', 'gaji', 'kasbon', 'billing'], true)        => 'gaji',
                default                                                                 => 'default',
            };

            $response = \Illuminate\Support\Facades\Http::timeout(5)->post('https://exp.host/--/api/v2/push/send', [
                'to'        => $user->fcm_token,
                'title'     => $title,
                'body'      => $body,
                'data'      => $data,
                'sound'     => 'default',
                'channelId' => $channel,
                'priority'  => 'high',
            ]);

            $ticket = $response->json('data');
            if (($ticket['status'] ?? null) === 'error') {
                \Log::warning('Expo push ticket error', ['user_id' => $userId, 'ticket' => $ticket]);
                // Token sudah tidak valid (app di-uninstall / login di HP lain) -> bersihkan
                if (($ticket['details']['error'] ?? null) === 'DeviceNotRegistered') {
                    $user->forceFill(['fcm_token' => null])->save();
                }
                return false;
            }
            return $response->successful();
        } catch (\Throwable $e) {
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
            $notif = \App\Services\NotifikasiService::send($user->id, $request->type ?? 'info', $request->title, $request->body);
            if ($notif->is_sent_push) $sent++;
        }
        return response()->json(['success' => true, 'sent_to' => $sent]);
    }

}