<?php

namespace App\Http\Controllers\Internal;

use App\Http\Controllers\Controller;
use App\Models\Mitra;
use App\Models\Agen;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

class RekrutmenController extends Controller
{
    public function index(Request $request)
    {
        try {
            $query = Mitra::with('user');
            if ($request->has('status')) $query->where('status', $request->status);
            if ($request->has('search')) {
                $search = $request->search;
                $query->whereHas('user', function($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%");
                });
            }
            $mitra = $query->orderBy('created_at', 'desc')->paginate(15);
            return response()->json(['success' => true, 'data' => $mitra->items(), 'pagination' => ['total' => $mitra->total(), 'per_page' => $mitra->perPage(), 'current_page' => $mitra->currentPage(), 'last_page' => $mitra->lastPage()]], 200);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'phone' => 'required|string|max:20',
            'password' => 'required|min:8',
            'nik' => 'nullable|string',
            'alamat' => 'nullable|string',
            'tanggal_lahir' => 'nullable|date',
            'jenis_kelamin' => 'nullable|in:L,P',
            'pendidikan' => 'nullable|string',
            'pengalaman' => 'nullable|string',
        ]);

        DB::beginTransaction();
        try {
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'phone' => $request->phone,
                'password' => Hash::make($request->password),
                'role' => 'mitra',
                'status' => 'active',
            ]);

            $mitra = Mitra::create([
                'user_id' => $user->id,n                'nik' => $request->nik ?? 'NIK-' . time(),
                'nama_lengkap' => $request->name,
                'alamat' => $request->alamat,
                'tanggal_lahir' => $request->tanggal_lahir,
                'jenis_kelamin' => $request->jenis_kelamin,
                'pendidikan_terakhir' => $request->pendidikan,
                'pengalaman' => $request->pengalaman,
                'status' => 'training',
                'training_status' => 'pending',
                'is_verified' => false,
            ]);

            DB::commit();
            return response()->json(['success' => true, 'message' => 'Mitra berhasil didaftarkan', 'data' => $mitra->load('user')], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function show($id)
    {
        try {
            $mitra = Mitra::with(['user'])->findOrFail($id);
            return response()->json(['success' => true, 'data' => $mitra], 200);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Mitra not found'], 404);
        }
    }

    public function update(Request $request, $id)
    {
        DB::beginTransaction();
        try {
            $mitra = Mitra::with('user')->findOrFail($id);
            if ($request->has('name') || $request->has('email') || $request->has('phone')) {
                $mitra->user->update($request->only(['name', 'email', 'phone']));
            }
            $mitra->update(array_filter([
                'alamat' => $request->alamat,
                'tanggal_lahir' => $request->tanggal_lahir,
                'jenis_kelamin' => $request->jenis_kelamin,
                'pendidikan_terakhir' => $request->pendidikan,
                'pengalaman' => $request->pengalaman,
                'status' => $request->status,
                'training_status' => $request->training_status,
            ], fn($v) => !is_null($v)));
            DB::commit();
            return response()->json(['success' => true, 'data' => $mitra->load('user')], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function destroy($id)
    {
        try {
            $mitra = Mitra::findOrFail($id);
            $mitra->update(['status' => 'keluar']);
            $mitra->user->update(['status' => 'inactive']);
            return response()->json(['success' => true, 'message' => 'Mitra dinonaktifkan'], 200);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function report(Request $request)
    {
        try {
            $total = Mitra::count();
            $pending = Mitra::where('status', 'pending')->count();
            $aktif = Mitra::where('status', 'aktif')->count();
            return response()->json(['success' => true, 'data' => compact('total', 'pending', 'aktif')], 200);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function reportMitraBaru(Request $request)
    {
        try {
            $start = $request->input('start_date', now()->startOfMonth());
            $end = $request->input('end_date', now()->endOfMonth());
            $mitra = Mitra::whereBetween('created_at', [$start, $end])->with('user')->get();
            return response()->json(['success' => true, 'data' => ['total' => $mitra->count(), 'mitra' => $mitra]], 200);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function reportMitraKeluar(Request $request)
    {
        try {
            $start = $request->input('start_date', now()->startOfMonth());
            $end = $request->input('end_date', now()->endOfMonth());
            $mitra = Mitra::where('status', 'keluar')->whereBetween('updated_at', [$start, $end])->with('user')->get();
            return response()->json(['success' => true, 'data' => ['total' => $mitra->count(), 'mitra' => $mitra]], 200);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function reportAgenInstitusi(Request $request)
    {
        try {
            $agen = Agen::with('user')->select('institution_name', DB::raw('count(*) as total'))->groupBy('institution_name')->get();
            return response()->json(['success' => true, 'data' => $agen], 200);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }
}
