<?php
namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Mitra;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

class MitraRegisterController extends Controller
{
    public function register(Request $request)
    {
        $request->validate([
            'name'         => 'required|string|max:255',
            'email'        => 'required|email|unique:users,email',
            'password'     => 'required|min:8',
            'phone'        => 'required|string',
            'nik'          => 'required|string',
            'alamat'       => 'required|string',
            'kota'         => 'required|string',
            'payment_type' => 'required|in:cash,kredit',
            'contract_agreed' => 'required|accepted',
        ]);

        DB::beginTransaction();
        try {
            $user = User::create([
                'name'     => $request->name,
                'email'    => strtolower($request->email),
                'phone'    => $request->phone,
                'password' => Hash::make($request->password),
                'role'     => 'mitra',
                'status'   => 'active',
            ]);

            Mitra::create([
                'user_id'             => $user->id,
                'nik'                 => $request->nik,
                'nama_lengkap'        => $request->name,
                'alamat'              => $request->alamat,
                'kota'                => $request->kota,
                'provinsi'            => $request->provinsi ?? '-',
                'tanggal_lahir'       => $request->tanggal_lahir,
                'jenis_kelamin'       => $request->jenis_kelamin ?? 'P',
                'pendidikan_terakhir' => $request->pendidikan,
                'pengalaman'          => $request->pengalaman,
                'status'              => 'training',
                'training_status'     => 'pending',
                'is_verified'         => false,
                'status_rekrutmen'    => 'pending',
                'payment_type'        => $request->payment_type,
                'contract_agreed_at'  => now(),
            ]);

            DB::commit();
            return response()->json([
                'success' => true,
                'message' => 'Pendaftaran berhasil! Tim rekrutmen akan menghubungi Anda segera.',
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }
}
