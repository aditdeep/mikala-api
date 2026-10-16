<?php

namespace App\Http\Controllers\Klien;

use App\Http\Controllers\Controller;
use App\Models\Notifikasi;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class KlienProfileController extends Controller
{
    public function show(Request $request)
    {
        try {
            $user = $request->user();
            $klien = $user->klien()->first();
            if (!$klien) {
                return response()->json(['success' => false, 'message' => 'Profile not found'], 404);
            }

            // Hitung stats real-time
            // layanan = Deal CC; pasien = pasien terdaftar + pasien dari Deal (lihat KlienDealController)
            $totalPasien  = \App\Models\Lead::where('klien_id', $klien->id)->whereNotNull('nama_pasien')
                ->pluck('nama_pasien')->map(fn ($n) => mb_strtolower(trim($n)))
                ->merge($klien->pasien()->pluck('nama_lengkap')->map(fn ($n) => mb_strtolower(trim($n))))
                ->filter()->unique()->count();
            $totalOrders  = \App\Models\Lead::where('klien_id', $klien->id)
                ->whereIn('status', [\App\Models\Lead::STATUS_DEAL, \App\Models\Lead::STATUS_STOP])->count();
            $totalTagihan = $klien->tagihan()->sum('total');

            // Sisipkan stats ke object klien (dibaca frontend) + tetap di stats
            $klien->total_pasien  = $totalPasien;
            $klien->total_patients = $totalPasien;
            $klien->total_orders  = $totalOrders;
            $klien->total_tagihan = $totalTagihan;

            return response()->json([
                'success' => true,
                'data' => [
                    'user' => $user,
                    'klien' => $klien,
                    'stats' => [
                        'total_orders'   => $totalOrders,
                        'total_patients' => $totalPasien,
                        'total_pasien'   => $totalPasien,
                        'total_tagihan'  => $totalTagihan,
                    ]
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function update(Request $request)
    {
        $request->validate([
            'name'              => 'sometimes|string|max:255',
            'phone'             => 'sometimes|string|max:20',
            'alamat'            => 'nullable|string',
            'kota'              => 'nullable|string|max:100',
            'provinsi'          => 'nullable|string|max:100',
            'phone_secondary'   => 'nullable|string|max:20',
            'bank_name'         => 'nullable|string|max:100',
            'bank_account'      => 'nullable|string|max:50',
            'bank_account_name' => 'nullable|string|max:255',
            'current_password'  => 'required_with:new_password',
            'new_password'      => 'nullable|min:6|confirmed', // PIN 6 angka juga boleh
        ]);

        try {
            $user = $request->user();
            $klien = $user->klien;

            if ($request->has('phone')) {
                // No HP dipakai buat login -> harus valid & belum dipakai akun lain
                $hp = \App\Support\NomorHp::normal($request->phone);
                if (!$hp) return response()->json(['success' => false, 'message' => 'No HP tidak valid'], 422);
                if (\App\Support\NomorHp::cariUser($hp)->where('id', '!=', $user->id)->isNotEmpty()) {
                    return response()->json(['success' => false, 'message' => 'No HP sudah dipakai akun lain'], 422);
                }
                $user->update(['phone' => $hp]);
            }
            if ($request->has('name')) $user->update(['name' => $request->name]);

            if ($request->filled('new_password')) {
                if (!Hash::check($request->current_password, $user->password)) {
                    return response()->json(['success' => false, 'message' => 'Password saat ini salah'], 400);
                }
                $user->update(['password' => Hash::make($request->new_password)]);
            }

            $klien->update($request->only([
                'alamat', 'kota', 'provinsi', 'phone_secondary',
                'bank_name', 'bank_account', 'bank_account_name'
            ]));

            return response()->json([
                'success' => true,
                'message' => 'Profil berhasil diperbarui',
                'data' => ['user' => $user->fresh(), 'klien' => $klien->fresh()]
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function notifikasi(Request $request)
    {
        try {
            $notifications = Notifikasi::where('user_id', $request->user()->id)
                ->orderBy('created_at', 'desc')->paginate(15);
            return response()->json(['success' => true, 'data' => $notifications]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }
}
