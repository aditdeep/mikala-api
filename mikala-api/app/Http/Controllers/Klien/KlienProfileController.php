<?php

namespace App\Http\Controllers\Klien;

use App\Http\Controllers\Controller;
use App\Models\Pasien;
use App\Models\Notifikasi;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class KlienProfileController extends Controller
{
    /**
     * Show klien profile
     */
    public function show(Request $request)
    {
        try {
            $user = $request->user();
            $klien = $user->klien()->with(['orders'])->first();

            if (!$klien) {
                return response()->json([
                    'success' => false,
                    'message' => 'Client profile not found'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'user' => $user,
                    'profile' => $klien,
                    'stats' => [
                        'total_orders' => $klien->orders()->count(),
                        'active_orders' => $klien->orders()->where('status', 'active')->count(),
                        'completed_orders' => $klien->orders()->where('status', 'completed')->count(),
                        'total_patients' => $klien->pasien()->count(),
                    ]
                ]
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve profile: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update klien profile
     */
    public function update(Request $request)
    {
        $request->validate([
            'name' => 'sometimes|string|max:255',
            'phone' => 'sometimes|string|max:20',
            'alamat' => 'nullable|string',
            'corporate_name' => 'nullable|string',
            'corporate_pic' => 'nullable|string',
            'current_password' => 'required_with:new_password',
            'new_password' => 'nullable|min:8|confirmed',
        ]);

        try {
            $user = $request->user();
            $klien = $user->klien;

            // Update user data
            if ($request->has('name') || $request->has('phone')) {
                $user->update($request->only(['name', 'phone']));
            }

            // Update password if provided
            if ($request->has('new_password')) {
                if (!Hash::check($request->current_password, $user->password)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Current password is incorrect'
                    ], 400);
                }
                $user->password = Hash::make($request->new_password);
                $user->save();
            }

            // Update klien profile
            $klien->update($request->only([
                'alamat', 'corporate_name', 'corporate_pic'
            ]));

            return response()->json([
                'success' => true,
                'message' => 'Profile updated successfully',
                'data' => [
                    'user' => $user,
                    'profile' => $klien
                ]
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update profile: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * List klien's patients
     */
    public function indexPasien(Request $request)
    {
        try {
            $user = $request->user();
            $klien = $user->klien;

            $pasiens = Pasien::where('klien_id', $klien->id)
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $pasiens
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve patients: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Show assigned mitra
     */
    public function indexMitra(Request $request)
    {
        try {
            $user = $request->user();
            $klien = $user->klien;

            // Get mitra from active orders
            $mitras = $klien->orders()
                ->whereNotNull('mitra_id')
                ->with('mitra.user')
                ->get()
                ->pluck('mitra')
                ->unique('id')
                ->values();

            return response()->json([
                'success' => true,
                'data' => $mitras
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve mitra: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get notifications
     */
    public function notifikasi(Request $request)
    {
        try {
            $notifications = Notifikasi::where('user_id', $request->user()->id)
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
}
