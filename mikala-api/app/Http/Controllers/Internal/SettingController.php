<?php

namespace App\Http\Controllers\Internal;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    public function index()
    {
        try {
            $settings = Setting::all()->pluck('value', 'key');
            // Jangan expose secret key ke frontend
            $settings['xendit_secret_key'] = $settings['xendit_secret_key'] ? '***hidden***' : '';
            return response()->json(['success' => true, 'data' => $settings]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function update(Request $request)
    {
        $request->validate([
            'bank_name'         => 'sometimes|string|max:100',
            'bank_account'      => 'sometimes|string|max:50',
            'bank_account_name' => 'sometimes|string|max:255',
            'xendit_enabled'    => 'sometimes|in:true,false',
            'xendit_secret_key' => 'sometimes|string|nullable',
            'xendit_public_key' => 'sometimes|string|nullable',
        ]);

        try {
            $fields = ['bank_name', 'bank_account', 'bank_account_name', 'xendit_enabled', 'xendit_public_key'];

            foreach ($fields as $key) {
                if ($request->has($key)) {
                    Setting::set($key, $request->input($key), 'payment');
                }
            }

            // Secret key hanya update kalau bukan placeholder
            if ($request->has('xendit_secret_key') && $request->xendit_secret_key !== '***hidden***') {
                Setting::set('xendit_secret_key', $request->xendit_secret_key, 'payment');
            }

            return response()->json(['success' => true, 'message' => 'Pengaturan berhasil disimpan']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    // Endpoint publik untuk klien (hanya rekening, tanpa key)
    public function publicPayment()
    {
        try {
            $data = [
                'bank_name'         => Setting::get('bank_name', 'BCA'),
                'bank_account'      => Setting::get('bank_account', '-'),
                'bank_account_name' => Setting::get('bank_account_name', 'PT Mikala Global Medika'),
                'xendit_enabled'    => Setting::get('xendit_enabled', 'false') === 'true',
            ];
            return response()->json(['success' => true, 'data' => $data]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function indexUsers()
    {
        try {
            $internalRoles = ['manajemen','rekrutmen','training_center','customer_care','finance','marketing'];
            $users = \App\Models\User::whereIn('role', $internalRoles)
                ->select('id','name','email','phone','role','status','created_at')
                ->orderBy('role')->orderBy('name')
                ->get();

            $grouped = $users->groupBy('role')->map(function($group) {
                return ['total' => $group->count(), 'users' => $group->values()];
            });

            return response()->json(['success' => true, 'data' => $grouped, 'total' => $users->count()]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function storeUser(\Illuminate\Http\Request $request)
    {
        $request->validate([
            'name'  => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'phone' => 'required|string|max:20|unique:users,phone',
            'role'  => 'required|in:manajemen,rekrutmen,training_center,customer_care,finance,marketing',
            'password' => 'required|min:8',
        ]);

        try {
            $user = \App\Models\User::create([
                'name'     => $request->name,
                'email'    => strtolower($request->email),
                'phone'    => $request->phone,
                'password' => \Illuminate\Support\Facades\Hash::make($request->password),
                'role'     => $request->role,
                'status'   => 'active',
            ]);
            return response()->json(['success' => true, 'message' => 'User berhasil dibuat', 'data' => $user], 201);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function updateUser(\Illuminate\Http\Request $request, $id)
    {
        $request->validate([
            'name'   => 'sometimes|string|max:255',
            'role'   => 'sometimes|in:manajemen,rekrutmen,training_center,customer_care,finance,marketing',
            'status' => 'sometimes|in:active,inactive',
            'password' => 'sometimes|min:8',
        ]);

        try {
            $user = \App\Models\User::findOrFail($id);
            $data = $request->only(['name','role','status']);
            if ($request->filled('password')) {
                $data['password'] = \Illuminate\Support\Facades\Hash::make($request->password);
            }
            $user->update($data);
            return response()->json(['success' => true, 'message' => 'User berhasil diupdate', 'data' => $user->fresh()]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function deleteUser(\Illuminate\Http\Request $request, $id)
    {
        try {
            $user = \App\Models\User::findOrFail($id);
            // Jangan hapus diri sendiri
            if ($user->id === $request->user()->id) {
                return response()->json(['success' => false, 'message' => 'Tidak bisa menghapus akun sendiri'], 400);
            }
            $user->update(['status' => 'inactive']);
            return response()->json(['success' => true, 'message' => 'User dinonaktifkan']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

}
