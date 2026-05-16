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
}
