<?php

namespace App\Http\Controllers\Mitra;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class MitraProfileController extends Controller
{
    /**
     * Show mitra profile
     */
    public function show(Request $request)
    {
        try {
            $user = $request->user();
            $mitra = $user->mitra()->with(['trainings', 'orders'])->first();

            if (!$mitra) {
                return response()->json([
                    'success' => false,
                    'message' => 'Mitra profile not found'
                ], 404);
            }

            // kelurahan/kecamatan dipecah dari alamat gabungan -> field terpisah di apps mitra
            $mitra->setAttribute('alamat_parts', \App\Models\Mitra::pecahAlamat($mitra->alamat, $mitra->kota, $mitra->provinsi));

            return response()->json([
                'success' => true,
                'data' => [
                    'user' => $user,
                    'mitra' => $mitra,
                    'stats' => [
                        'total_orders' => $mitra->orders()->count(),
                        'completed_orders' => $mitra->orders()->where('status', 'completed')->count(),
                        'active_orders' => $mitra->orders()->whereIn('status', ['confirmed', 'in_progress', 'active'])->count(),
                        'total_trainings' => $mitra->trainings()->count(),
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
     * Update mitra profile
     */
    public function update(Request $request)
    {
        $request->validate([
            'name' => 'sometimes|string|max:255',
            'phone' => 'sometimes|string|max:20',
            'alamat' => 'nullable|string',
            'tanggal_lahir' => 'nullable|date',
            'jenis_kelamin' => 'nullable|in:L,P',
            'pendidikan_terakhir' => 'nullable|string',
            'pengalaman' => 'nullable|string',
            'alamat_jalan'      => 'nullable|string',
            'kelurahan'         => 'nullable|string|max:100',
            'kecamatan'         => 'nullable|string|max:100',
            'vaksin'            => 'nullable|string|max:255',
            'kota'              => 'nullable|string|max:100',
            'provinsi'          => 'nullable|string|max:100',
            'bank_name'         => 'nullable|string|max:100',
            'bank_account'      => 'nullable|string|max:50',
            'bank_account_name' => 'nullable|string|max:255',
            'foto_url'          => 'nullable|string',
            'cv_file'           => 'nullable|string',
            'current_password' => 'required_with:new_password',
            'new_password' => 'nullable|min:8|confirmed',
        ]);

        try {
            $user = $request->user();
            $mitra = $user->mitra;

            // Update user data
            if ($request->has('name') || $request->has('phone')) {
                $user->update($request->only(['name', 'phone']));
            }

            // Update password if provided
            if ($request->has('new_password')) {
                if (!Hash::check($request->current_password, $user->password)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Password lama salah'
                    ], 400);
                }
                $user->password = Hash::make($request->new_password);
                $user->save();
            }

            // Update mitra profile
            $data = $request->only([
                'alamat', 'kota', 'provinsi', 'tanggal_lahir', 'jenis_kelamin',
                'pendidikan_terakhir', 'pengalaman', 'vaksin',
                'bank_name', 'bank_account', 'bank_account_name', 'foto_url', 'cv_file'
            ]);
            // Apps versi baru kirim jalan/kelurahan/kecamatan terpisah -> susun ulang alamat
            // gabungan dgn format yg sama spt form Edit Mitra di admin (supaya tetap sinkron).
            // Apps versi lama masih kirim `alamat` utuh -> disimpan apa adanya.
            if ($request->hasAny(['alamat_jalan', 'kelurahan', 'kecamatan'])) {
                $lama = \App\Models\Mitra::pecahAlamat($mitra->alamat, $mitra->kota, $mitra->provinsi);
                $data['alamat'] = \App\Models\Mitra::gabungAlamat(
                    $request->input('alamat_jalan', $lama['alamat_jalan']),
                    $request->input('kelurahan', $lama['kelurahan']),
                    $request->input('kecamatan', $lama['kecamatan']),
                    $request->input('kota', $mitra->kota),
                    $request->input('provinsi', $mitra->provinsi),
                );
            }
            $mitra->update($data);
            $mitra = $mitra->fresh();
            $mitra->setAttribute('alamat_parts', \App\Models\Mitra::pecahAlamat($mitra->alamat, $mitra->kota, $mitra->provinsi));

            return response()->json([
                'success' => true,
                'message' => 'Profile updated successfully',
                'data' => [
                    'user' => $user->fresh(),
                    'mitra' => $mitra
                ]
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update profile: ' . $e->getMessage()
            ], 500);
        }
    }
}
