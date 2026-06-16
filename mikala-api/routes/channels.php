<?php

use Illuminate\Support\Facades\Broadcast;

// Private channel notifikasi.{userId} — hanya user dengan ID itu yang boleh listen
Broadcast::channel('notifikasi.{userId}', function ($user, $userId) {
    return (int) $user->id === (int) $userId;
});
