<?php

namespace App\Http\Controllers\Internal;

use App\Http\Controllers\Controller;
use App\Models\Leads;
use App\Models\Kerjasama;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MarketingController extends Controller
{
    /**
     * List marketing leads
     */
    public function indexLeads(Request $request)
    {
        try {
            $query = Leads::query();

            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            if ($request->has('source')) {
                $query->where('source', $request->source);
            }

            if ($request->has('search')) {
                $search = $request->search;
                $query->where(function($q) use ($search) {
                    $q->where('nama', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%")
                      ->orWhere('phone', 'like', "%{$search}%");
                });
            }

            $leads = $query->orderBy('created_at', 'desc')->paginate(15);

            return response()->json([
                'success' => true,
                'data' => $leads->items(),
                'pagination' => [
                    'total' => $leads->total(),
                    'per_page' => $leads->perPage(),
                    'current_page' => $leads->currentPage(),
                    'last_page' => $leads->lastPage()
                ]
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve leads: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create lead from inquiry
     */
    public function storeLeads(Request $request)
    {
        $request->validate([
            'nama'          => 'required|string|max:255',
            'email'         => 'nullable|email',
            'phone'         => 'required|string|max:20',
            'source'        => 'nullable|string',
            'tipe_layanan'  => 'nullable|string',
            'pesan'         => 'nullable|string',
        ]);

        try {
            $lead = Leads::create([
                'nama'         => $request->nama,
                'email'        => $request->email,
                'phone'        => $request->phone,
                'source'       => $request->source ?? 'website_mgm',
                'tipe_layanan' => $request->tipe_layanan ?? $request->layanan_interest,
                'pesan'        => $request->pesan ?? $request->message,
                'status'       => 'new',
                'contacted_at' => null,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Lead created successfully',
                'data' => $lead
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create lead: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Show lead detail
     */
    public function showLeads($id)
    {
        try {
            $lead = Leads::findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $lead
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lead not found'
            ], 404);
        }
    }

    /**
     * Update lead status
     */
    public function updateLeadsStatus(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|in:new,contacted,qualified,deal,lost',
            'notes' => 'nullable|string',
        ]);

        try {
            $lead = Leads::findOrFail($id);
            $lead->status = $request->status;
            
            if ($request->status === 'contacted' && !$lead->contacted_at) {
                $lead->contacted_at = now();
            }

            if ($request->status === 'deal') {
                $lead->converted_at = now();
            }

            if ($request->has('notes')) {
                $lead->notes = $request->notes;
            }

            $lead->save();

            return response()->json([
                'success' => true,
                'message' => 'Lead status updated successfully',
                'data' => $lead
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update lead status: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * List partnerships/collaborations
     */
    public function indexKerjasama(Request $request)
    {
        try {
            $data = Kerjasama::orderBy('created_at','desc')->get();
            return response()->json(['success' => true, 'data' => $data]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function indexKerjasama_old(Request $request)
    {
        try {
            // This is a placeholder - would need a Kerjasama model
            // For now, returning empty structure
            return response()->json([
                'success' => true,
                'data' => [],
                'message' => 'Partnership feature coming soon'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve partnerships: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create partnership
     */
    public function storeKerjasama(Request $request)
    {
        $request->validate([
            'partner_name' => 'required|string|max:255',
            'partner_type' => 'required|string',
            'contact_person' => 'required|string',
            'email' => 'required|email',
            'phone' => 'required|string',
        ]);

        try {
            $kerjasama = Kerjasama::create([
                'partner_name'   => $request->partner_name,
                'partner_type'   => $request->partner_type ?? $request->tipe,
                'contact_person' => $request->contact_person,
                'phone'          => $request->phone,
                'email'          => $request->email,
                'notes'          => $request->notes ?? $request->catatan,
                'status'         => 'active',
            ]);
            return response()->json([
                'success' => true,
                'message' => 'Kerjasama berhasil disimpan',
                'data'    => $kerjasama
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create partnership: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Show partnership detail
     */
    public function showKerjasama($id)
    {
        try {
            // Placeholder
            return response()->json([
                'success' => true,
                'data' => ['id' => $id],
                'message' => 'Partnership feature coming soon'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Partnership not found'
            ], 404);
        }
    }

    // ========== REPORTS ==========

    /**
     * Report: Orders coming in
     */
    public function reportOrderIn(Request $request)
    {
        try {
            $startDate = $request->input('start_date', now()->startOfMonth());
            $endDate = $request->input('end_date', now()->endOfMonth());

            $orders = Order::whereBetween('created_at', [$startDate, $endDate])
                ->with(['klien.user'])
                ->get();

            $bySource = Leads::whereBetween('created_at', [$startDate, $endDate])
                ->where('status', 'deal')
                ->select('source', DB::raw('count(*) as count'))
                ->groupBy('source')
                ->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'period' => ['start' => $startDate, 'end' => $endDate],
                    'total_orders' => $orders->count(),
                    'total_value' => $orders->sum('total_amount'),
                    'by_source' => $bySource,
                    'orders' => $orders
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
     * Report: Deals closed
     */
    public function reportDeal(Request $request)
    {
        try {
            $startDate = $request->input('start_date', now()->startOfMonth());
            $endDate = $request->input('end_date', now()->endOfMonth());

            $deals = Leads::where('status', 'deal')
                ->whereBetween('converted_at', [$startDate, $endDate])
                ->get();

            $conversionRate = 0;
            $totalLeads = Leads::whereBetween('created_at', [$startDate, $endDate])->count();
            if ($totalLeads > 0) {
                $conversionRate = ($deals->count() / $totalLeads) * 100;
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'period' => ['start' => $startDate, 'end' => $endDate],
                    'total_deals' => $deals->count(),
                    'total_leads' => $totalLeads,
                    'conversion_rate' => round($conversionRate, 2) . '%',
                    'deals' => $deals
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
     * Report: Gap analysis
     */
    public function reportGapAnalysis(Request $request)
    {
        try {
            $startDate = $request->input('start_date', now()->startOfMonth());
            $endDate = $request->input('end_date', now()->endOfMonth());

            $stats = [
                'new_leads' => Leads::whereBetween('created_at', [$startDate, $endDate])
                    ->where('status', 'new')->count(),
                'contacted' => Leads::whereBetween('created_at', [$startDate, $endDate])
                    ->where('status', 'contacted')->count(),
                'qualified' => Leads::whereBetween('created_at', [$startDate, $endDate])
                    ->where('status', 'qualified')->count(),
                'deals' => Leads::whereBetween('created_at', [$startDate, $endDate])
                    ->where('status', 'deal')->count(),
                'lost' => Leads::whereBetween('created_at', [$startDate, $endDate])
                    ->where('status', 'lost')->count(),
            ];

            // Calculate drop-off rates
            $total = array_sum($stats);
            $gaps = [
                'new_to_contacted' => $stats['new_leads'] > 0 ? 
                    round((($stats['new_leads'] - $stats['contacted']) / $stats['new_leads']) * 100, 2) : 0,
                'contacted_to_qualified' => $stats['contacted'] > 0 ? 
                    round((($stats['contacted'] - $stats['qualified']) / $stats['contacted']) * 100, 2) : 0,
                'qualified_to_deal' => $stats['qualified'] > 0 ? 
                    round((($stats['qualified'] - $stats['deals']) / $stats['qualified']) * 100, 2) : 0,
            ];

            return response()->json([
                'success' => true,
                'data' => [
                    'period' => ['start' => $startDate, 'end' => $endDate],
                    'funnel_stats' => $stats,
                    'drop_off_rates' => $gaps,
                    'recommendations' => $this->getGapRecommendations($gaps)
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
     * Helper: Get recommendations based on gap analysis
     */
    private function getGapRecommendations($gaps)
    {
        $recommendations = [];

        if ($gaps['new_to_contacted'] > 50) {
            $recommendations[] = 'High drop-off from new to contacted. Increase follow-up speed and frequency.';
        }

        if ($gaps['contacted_to_qualified'] > 40) {
            $recommendations[] = 'Many contacted leads not qualifying. Review lead qualification criteria or improve pitch.';
        }

        if ($gaps['qualified_to_deal'] > 30) {
            $recommendations[] = 'Qualified leads not converting. Review pricing, service offering, or closing techniques.';
        }

        if (empty($recommendations)) {
            $recommendations[] = 'Conversion funnel is performing well. Continue current strategies.';
        }

        return $recommendations;
    }
}
