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
    // ========== MITRA MANAGEMENT ==========
    
    /**
     * List all mitra with filters
     */
    public function indexMitra(Request $request)
    {
        try {
            $query = Mitra::with('user');

            // Filters
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }
            if ($request->has('training_status')) {
                $query->where('training_status', $request->training_status);
            }
            if ($request->has('search')) {
                $search = $request->search;
                $query->whereHas('user', function($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%");
                });
            }

            $mitra = $query->orderBy('created_at', 'desc')->paginate(15);

            return response()->json([
                'success' => true,
                'data' => $mitra->items(),
                'pagination' => [
                    'total' => $mitra->total(),
                    'per_page' => $mitra->perPage(),
                    'current_page' => $mitra->currentPage(),
                    'last_page' => $mitra->lastPage()
                ]
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve mitra list: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create new mitra application
     */
    public function storeMitra(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'phone' => 'required|string|max:20',
            'password' => 'required|min:8',
            'nik' => 'required|string|unique:mitras,nik',
            'alamat' => 'nullable|string',
            'tanggal_lahir' => 'nullable|date',
            'jenis_kelamin' => 'nullable|in:L,P',
            'pendidikan' => 'nullable|string',
            'pengalaman' => 'nullable|string',
        ]);

        DB::beginTransaction();
        try {
            // Create user account
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'phone' => $request->phone,
                'password' => Hash::make($request->password),
                'role' => 'mitra',
                'status' => 'active',
            ]);

            // Create mitra profile
            $mitra = Mitra::create([
                'user_id' => $user->id,
                'nik' => $request->nik,
                'alamat' => $request->alamat,
                'tanggal_lahir' => $request->tanggal_lahir,
                'jenis_kelamin' => $request->jenis_kelamin,
                'pendidikan' => $request->pendidikan,
                'pengalaman' => $request->pengalaman,
                'status' => 'pending',
                'training_status' => 'pending',
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Mitra application created successfully',
                'data' => $mitra->load('user')
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to create mitra application: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Show mitra detail
     */
    public function showMitra($id)
    {
        try {
            $mitra = Mitra::with(['user', 'orders', 'trainings'])->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $mitra
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Mitra not found'
            ], 404);
        }
    }

    /**
     * Update mitra data/status
     */
    public function updateMitra(Request $request, $id)
    {
        $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|unique:users,email,' . $id,
            'phone' => 'sometimes|string|max:20',
            'alamat' => 'nullable|string',
            'tanggal_lahir' => 'nullable|date',
            'jenis_kelamin' => 'nullable|in:L,P',
            'pendidikan' => 'nullable|string',
            'pengalaman' => 'nullable|string',
            'status' => 'sometimes|in:pending,aktif,nonaktif,keluar',
            'training_status' => 'sometimes|in:pending,on-job,available,re-training',
        ]);

        DB::beginTransaction();
        try {
            $mitra = Mitra::with('user')->findOrFail($id);

            // Update user data
            if ($request->has('name') || $request->has('email') || $request->has('phone')) {
                $mitra->user->update($request->only(['name', 'email', 'phone']));
            }

            // Update mitra profile
            $mitra->update($request->only([
                'alamat', 'tanggal_lahir', 'jenis_kelamin', 
                'pendidikan', 'pengalaman', 'status', 'training_status'
            ]));

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Mitra updated successfully',
                'data' => $mitra->load('user')
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to update mitra: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Soft delete mitra
     */
    public function destroyMitra($id)
    {
        try {
            $mitra = Mitra::findOrFail($id);
            $mitra->status = 'keluar';
            $mitra->save();

            // Deactivate user account
            $mitra->user->status = 'inactive';
            $mitra->user->save();

            return response()->json([
                'success' => true,
                'message' => 'Mitra deactivated successfully'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to deactivate mitra: ' . $e->getMessage()
            ], 500);
        }
    }

    // ========== AGEN MANAGEMENT ==========

    /**
     * List all agen
     */
    public function indexAgen(Request $request)
    {
        try {
            $query = Agen::with('user');

            if ($request->has('search')) {
                $search = $request->search;
                $query->where('institution_name', 'like', "%{$search}%")
                      ->orWhereHas('user', function($q) use ($search) {
                          $q->where('name', 'like', "%{$search}%");
                      });
            }

            $agen = $query->orderBy('created_at', 'desc')->paginate(15);

            return response()->json([
                'success' => true,
                'data' => $agen->items(),
                'pagination' => [
                    'total' => $agen->total(),
                    'per_page' => $agen->perPage(),
                    'current_page' => $agen->currentPage(),
                    'last_page' => $agen->lastPage()
                ]
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve agen list: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create new agen
     */
    public function storeAgen(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'phone' => 'required|string|max:20',
            'password' => 'required|min:8',
            'institution_name' => 'required|string|max:255',
            'institution_address' => 'nullable|string',
        ]);

        DB::beginTransaction();
        try {
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'phone' => $request->phone,
                'password' => Hash::make($request->password),
                'role' => 'agen',
                'status' => 'active',
            ]);

            $agen = Agen::create([
                'user_id' => $user->id,
                'institution_name' => $request->institution_name,
                'institution_address' => $request->institution_address,
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Agen created successfully',
                'data' => $agen->load('user')
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to create agen: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Show agen detail
     */
    public function showAgen($id)
    {
        try {
            $agen = Agen::with('user')->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $agen
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Agen not found'
            ], 404);
        }
    }

    /**
     * Update agen
     */
    public function updateAgen(Request $request, $id)
    {
        $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email',
            'phone' => 'sometimes|string|max:20',
            'institution_name' => 'sometimes|string|max:255',
            'institution_address' => 'nullable|string',
        ]);

        DB::beginTransaction();
        try {
            $agen = Agen::with('user')->findOrFail($id);

            if ($request->has('name') || $request->has('email') || $request->has('phone')) {
                $agen->user->update($request->only(['name', 'email', 'phone']));
            }

            $agen->update($request->only(['institution_name', 'institution_address']));

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Agen updated successfully',
                'data' => $agen->load('user')
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to update agen: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete agen
     */
    public function destroyAgen($id)
    {
        try {
            $agen = Agen::findOrFail($id);
            $agen->user->status = 'inactive';
            $agen->user->save();
            $agen->delete();

            return response()->json([
                'success' => true,
                'message' => 'Agen deleted successfully'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete agen: ' . $e->getMessage()
            ], 500);
        }
    }

    // ========== REPORTS ==========

    /**
     * Report: New mitra by period
     */
    public function reportMitraBaru(Request $request)
    {
        try {
            $startDate = $request->input('start_date', now()->startOfMonth());
            $endDate = $request->input('end_date', now()->endOfMonth());

            $newMitra = Mitra::whereBetween('created_at', [$startDate, $endDate])
                ->with('user')
                ->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'period' => ['start' => $startDate, 'end' => $endDate],
                    'total' => $newMitra->count(),
                    'mitra' => $newMitra
                ]
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate report: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Report: Exited mitra
     */
    public function reportMitraKeluar(Request $request)
    {
        try {
            $startDate = $request->input('start_date', now()->startOfMonth());
            $endDate = $request->input('end_date', now()->endOfMonth());

            $exitedMitra = Mitra::where('status', 'keluar')
                ->whereBetween('updated_at', [$startDate, $endDate])
                ->with('user')
                ->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'period' => ['start' => $startDate, 'end' => $endDate],
                    'total' => $exitedMitra->count(),
                    'mitra' => $exitedMitra
                ]
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate report: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Report: Agen by institution
     */
    public function reportAgenInstitusi(Request $request)
    {
        try {
            $agen = Agen::with('user')
                ->select('institution_name', DB::raw('count(*) as total'))
                ->groupBy('institution_name')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $agen
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate report: ' . $e->getMessage()
            ], 500);
        }
    }
}
