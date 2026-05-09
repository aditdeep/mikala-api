<?php

namespace App\Http\Controllers\Mitra;

use App\Http\Controllers\Controller;
use App\Models\Payroll;
use Illuminate\Http\Request;

class MitraPayrollController extends Controller
{
    /**
     * List mitra payroll history
     */
    public function index(Request $request)
    {
        try {
            $user = $request->user();
            $mitra = $user->mitra;

            if (!$mitra) {
                return response()->json([
                    'success' => false,
                    'message' => 'Mitra profile not found'
                ], 404);
            }

            $query = Payroll::where('mitra_id', $mitra->id);

            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            if ($request->has('periode')) {
                $query->where('periode', $request->periode);
            }

            $payrolls = $query->orderBy('periode', 'desc')->paginate(15);

            return response()->json([
                'success' => true,
                'data' => $payrolls->items(),
                'pagination' => [
                    'total' => $payrolls->total(),
                    'per_page' => $payrolls->perPage(),
                    'current_page' => $payrolls->currentPage(),
                    'last_page' => $payrolls->lastPage()
                ]
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve payroll: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Show payroll detail
     */
    public function show(Request $request, $id)
    {
        try {
            $user = $request->user();
            $mitra = $user->mitra;

            $payroll = Payroll::where('id', $id)
                ->where('mitra_id', $mitra->id)
                ->firstOrFail();

            return response()->json([
                'success' => true,
                'data' => $payroll
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Payroll not found or access denied'
            ], 404);
        }
    }
}
