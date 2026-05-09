<?php

namespace App\Services;

use App\Models\Notifikasi;
use App\Models\User;

class NotifikasiService
{
    /**
     * Send notification to user(s)
     */
    public function send(
        $userIds,
        string $title,
        string $message,
        string $type = 'info',
        ?string $relatedType = null,
        ?int $relatedId = null,
        ?string $actionUrl = null,
        bool $sendPush = true,
        bool $sendEmail = false
    ): void {
        // Normalize userIds to array
        $userIds = is_array($userIds) ? $userIds : [$userIds];

        foreach ($userIds as $userId) {
            $notif = Notifikasi::create([
                'user_id' => $userId,
                'title' => $title,
                'message' => $message,
                'type' => $type,
                'related_type' => $relatedType,
                'related_id' => $relatedId,
                'action_url' => $actionUrl,
                'is_sent_push' => $sendPush,
                'is_sent_email' => $sendEmail,
                'sent_at' => now(),
            ]);

            // TODO: Integrate Firebase FCM for push notifications
            if ($sendPush) {
                $this->sendPushNotification($notif);
            }

            // TODO: Integrate email sending
            if ($sendEmail) {
                $this->sendEmailNotification($notif);
            }
        }
    }

    /**
     * Send push notification via FCM (placeholder)
     */
    protected function sendPushNotification(Notifikasi $notif): void
    {
        $user = $notif->user;
        
        if (!$user->fcm_token) {
            return;
        }

        // TODO: Implement FCM integration
        // Example:
        // $fcm = new FCM();
        // $fcm->send($user->fcm_token, $notif->title, $notif->message, $notif->action_url);
    }

    /**
     * Send email notification (placeholder)
     */
    protected function sendEmailNotification(Notifikasi $notif): void
    {
        // TODO: Implement email sending
        // Mail::to($notif->user->email)->send(new NotificationMail($notif));
    }

    /**
     * Send notification to all users with specific role
     */
    public function sendToRole(
        string $role,
        string $title,
        string $message,
        string $type = 'info',
        ?string $relatedType = null,
        ?int $relatedId = null
    ): void {
        $users = User::where('role', $role)->where('status', 'active')->pluck('id')->toArray();
        $this->send($users, $title, $message, $type, $relatedType, $relatedId);
    }
}
