<?php

namespace App\Http\Controllers\Internal;

use App\Http\Controllers\Controller;
use App\Models\Mitra;
use App\Models\Training;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TrainingController extends Controller
{
    /**
     * List mitra with training status
     */
    public function indexMitra(Request $request)
    {
        try {
            $query = Mitra::with(['user'])
                ->whereIn('status_rekrutmen', ['verified']);

            if ($request->has('training_status')) {
                $query->where('training_status', $request->training_status);
            }

            if ($request->has('search')) {
                $search = $request->search;
                $query->where(function($q) use ($search) {
                    $q->where('nama_lengkap', 'like', "%{$search}%")
                      ->orWhereHas('user', fn($u) => $u->where('name', 'like', "%{$search}%"));
                });
            }

            $mitra = $query->orderBy('nama_lengkap')->paginate(20);
            $total = \App\Models\TrainingMateri::where('is_active', true)->count();

            $items = collect($mitra->items())->map(function($m) use ($total) {
                $selesai = \App\Models\TrainingChecklist::where('mitra_id', $m->id)->count();
                return array_merge($m->toArray(), [
                    'training_total'   => $total,
                    'training_selesai' => $selesai,
                    'training_persen'  => $total > 0 ? round($selesai / $total * 100) : 0,
                ]);
            });

            return response()->json([
                'success' => true,
                'data'    => $items,
                'pagination' => [
                    'total'        => $mitra->total(),
                    'per_page'     => $mitra->perPage(),
                    'current_page' => $mitra->currentPage(),
                    'last_page'    => $mitra->lastPage(),
                ],
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve mitra list: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Show training detail for mitra
     */
    public function showMitra($id)
    {
        try {
            $mitra = Mitra::with(['user'])->findOrFail($id);

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
     * Submit training checklist item
     */
    public function submitChecklist(Request $request, $id)
    {
        $request->validate([
            'checklist_item' => 'required|integer',
            'done' => 'required|boolean',
        ]);

        try {
            $training = Training::findOrFail($id);
            $checklist = $training->checklist ?? [];

            // Update specific checklist item
            if (isset($checklist[$request->checklist_item])) {
                $checklist[$request->checklist_item]['done'] = $request->done;
                if ($request->done) {
                    $checklist[$request->checklist_item]['completed_at'] = now()->toDateTimeString();
                }
            }

            $training->checklist = $checklist;
            $training->save();
            $training->updateChecklistProgress();

            // Auto-complete training if all checklist done
            $completed = collect($checklist)->where('done', true)->count();
            if ($completed === count($checklist) && $training->status !== 'completed') {
                $training->update([
                    'status' => 'completed',
                    'completed_at' => now(),
                ]);
                
                // Update mitra training status
                $training->mitra->training_status = 'available';
                $training->mitra->save();
            }

            return response()->json([
                'success' => true,
                'message' => 'Checklist updated successfully',
                'data' => $training
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update checklist: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Submit training feedback
     */
    public function submitFeedback(Request $request, $id)
    {
        $request->validate([
            'score' => 'required|integer|min:0|max:100',
            'feedback' => 'required|string',
        ]);

        try {
            // $id adalah mitra_id, cari atau buat training record
            $mitra = \App\Models\Mitra::findOrFail($id);

            $training = Training::firstOrCreate(
                ['mitra_id' => $id],
                [
                    'tipe'         => 'initial',
                    'program_name' => 'Training Awal',
                    'status'       => 'in_progress',
                    'tanggal_mulai'=> now(),
                ]
            );

            $training->update([
                'score'       => $request->score,
                'feedback'    => $request->feedback,
                'rekomendasi' => $request->rekomendasi ?? 'lanjut',
            ]);

            // Update mitra training_score juga
            try { $mitra->update(['training_score' => $request->score]); } catch (\Exception $e) {}

            return response()->json([
                'success' => true,
                'message' => 'Feedback berhasil disimpan',
                'data'    => $training
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to submit feedback: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update training status
     */
    public function updateStatus(Request $request, $id)
    {
        $request->validate([
            'training_status' => 'required|in:pending,in_progress,completed,failed',
        ]);

        try {
            $mitra = Mitra::with('user')->findOrFail($id);
            $mitra->training_status = $request->training_status;

            if ($request->training_status === 'completed') {
                $mitra->training_completed_at = now();
                $mitra->status = 'available';
            }

            $mitra->save();

            return response()->json([
                'success' => true,
                'message' => 'Status training berhasil diupdate',
                'data' => $mitra
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update training status: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create new training for mitra
     */
    public function store(Request $request)
    {
        $request->validate([
            'mitra_id' => 'required|exists:mitras,id',
            'tipe' => 'required|string',
            'program_name' => 'required|string',
            'deskripsi' => 'nullable|string',
            'tanggal_mulai' => 'required|date',
            'tanggal_selesai' => 'required|date|after:tanggal_mulai',
            'durasi_hari' => 'required|integer',
            'biaya' => 'required|numeric',
            'trainer_id' => 'nullable|exists:users,id',
        ]);

        try {
            $training = Training::create([
                'mitra_id' => $request->mitra_id,
                'tipe' => $request->tipe,
                'program_name' => $request->program_name,
                'deskripsi' => $request->deskripsi,
                'tanggal_mulai' => $request->tanggal_mulai,
                'tanggal_selesai' => $request->tanggal_selesai,
                'durasi_hari' => $request->durasi_hari,
                'biaya' => $request->biaya,
                'trainer_id' => $request->trainer_id,
                'status' => 'pending',
                'checklist' => [], // Default empty checklist
                'checklist_completed' => 0,
                'checklist_total' => 0,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Training created successfully',
                'data' => $training->load('mitra.user')
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create training: ' . $e->getMessage()
            ], 500);
        }
    }

    // ========== REPORTS ==========


    /**
     * Report: General training summary
     */
    public function report(Request $request)
    {
        try {
            $total = Mitra::count();
            $byStatus = Mitra::selectRaw('training_status, count(*) as total')
                ->groupBy('training_status')
                ->pluck('total', 'training_status');

            return response()->json([
                'success' => true,
                'data' => [
                    'total' => $total,
                    'by_status' => $byStatus,
                ]
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Report: Available mitra count
     */
    public function reportAvailable(Request $request)
    {
        try {
            $available = Mitra::where('training_status', 'available')
                ->with('user')
                ->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'total' => $available->count(),
                    'mitra' => $available
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
     * Report: On-job mitra count
     */
    public function reportOnJob(Request $request)
    {
        try {
            $onJob = Mitra::where('status', 'on_job')->with('user')->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'total' => $onJob->count(),
                    'mitra' => $onJob
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
     * Report: Re-training mitra
     */
    public function reportReTraining(Request $request)
    {
        try {
            $reTraining = Mitra::where('training_status', 're-training')
                ->with('user')
                ->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'total' => $reTraining->count(),
                    'mitra' => $reTraining
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
     * List training pricing
     */

    public function indexFeedback(Request $request)
    {
        try {
            $trainings = Training::with(['mitra.user'])
                ->whereNotNull('feedback')
                ->where('feedback', '!=', '')
                ->orderBy('updated_at', 'desc')
                ->get();
            return response()->json(['success' => true, 'data' => $trainings]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function indexPricing(Request $request)
    {
        try {
            $tipeLayanan = ['homecare_harian','homecare_live_in','medical_checkup','konsultasi','fisioterapi','perawatan_luka','vaksinasi','lainnya'];
            $pricing = collect($tipeLayanan)->map(function($t) {
                $perJam  = \App\Models\Setting::where('key', 'pricing_'.$t.'_per_jam')->first();
                $perHari = \App\Models\Setting::where('key', 'pricing_'.$t.'_per_hari')->first();
                return [
                    'id'             => $t,
                    'tipe_layanan'   => $t,
                    'harga_per_jam'  => $perJam  ? floatval($perJam->value)  : 0,
                    'harga_per_hari' => $perHari ? floatval($perHari->value) : 0,
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $pricing
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve pricing: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update training pricing (placeholder)
     */
    public function updatePricing(Request $request, $id)
    {
        $request->validate([
            'harga_per_jam'  => 'nullable|numeric|min:0',
            'harga_per_hari' => 'nullable|numeric|min:0',
            'biaya'          => 'nullable|numeric|min:0',
        ]);

        try {
            // Update setting per tipe layanan
            $tipe = $id;
            \App\Models\Setting::updateOrCreate(
                ['key' => 'pricing_'.$tipe.'_per_jam'],
                ['value' => $request->harga_per_jam ?? 0]
            );
            \App\Models\Setting::updateOrCreate(
                ['key' => 'pricing_'.$tipe.'_per_hari'],
                ['value' => $request->harga_per_hari ?? 0]
            );

            return response()->json([
                'success' => true,
                'message' => 'Pricing berhasil diupdate',
                'data'    => ['tipe_layanan' => $tipe, 'harga_per_jam' => $request->harga_per_jam, 'harga_per_hari' => $request->harga_per_hari]
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update pricing: ' . $e->getMessage()
            ], 500);
        }
    }


    // ── Checklist Materi ──────────────────────────────────────────────────────
    public function materiList() {
        $materi = \App\Models\TrainingMateri::where('is_active',true)
            ->orderBy('kategori')->orderBy('urutan')->get();
        return response()->json(['success'=>true,'data'=>$materi]);
    }

    public function mitraProgress($mitraId) {
        $mitra   = \App\Models\Mitra::with('user')->findOrFail($mitraId);
        $materi  = \App\Models\TrainingMateri::where('is_active',true)->orderBy('kategori')->orderBy('urutan')->get();
        $checks  = \App\Models\TrainingChecklist::where('mitra_id',$mitraId)->with('checker:id,name')->get()->keyBy('materi_id');
        $total   = $materi->count();
        $selesai = $checks->count();
        $byKat   = $materi->groupBy('kategori')->map(fn($items,$kat)=>[
            'kategori'=>$kat,'total'=>$items->count(),
            'selesai'=>$items->filter(fn($m)=>isset($checks[$m->id]))->count(),
            'persen'=>$items->count()>0?round($items->filter(fn($m)=>isset($checks[$m->id]))->count()/$items->count()*100):0,
        ])->values();
        $result = $materi->map(fn($m)=>[
            'id'=>$m->id,'kode'=>$m->kode,'nama'=>$m->nama,'kategori'=>$m->kategori,
            'parent_kode'=>$m->parent_kode,'urutan'=>$m->urutan,
            'checked'=>isset($checks[$m->id]),
            'tanggal_dapat'=>$checks[$m->id]?->tanggal_dapat?->format('Y-m-d'),
            'pengajar'=>$checks[$m->id]?->pengajar,
            'checked_by'=>$checks[$m->id]?->checker?->name,
        ]);
        return response()->json([
            'success'=>true,'mitra'=>['id'=>$mitra->id,'nama'=>$mitra->nama_lengkap,'foto'=>$mitra->foto_url],
            'total'=>$total,'selesai'=>$selesai,'persen'=>$total>0?round($selesai/$total*100):0,
            'by_kategori'=>$byKat,'materi'=>$result,
        ]);
    }

    public function toggleChecklist(\Illuminate\Http\Request $request, $mitraId, $materiId) {
        $request->validate(['tanggal_dapat'=>'required|date','pengajar'=>'required|string|max:100','catatan'=>'nullable|string']);
        $existing = \App\Models\TrainingChecklist::where('mitra_id',$mitraId)->where('materi_id',$materiId)->first();
        if ($existing) { $existing->delete(); return response()->json(['success'=>true,'action'=>'unchecked']); }
        \App\Models\TrainingChecklist::create([
            'mitra_id'=>$mitraId,'materi_id'=>$materiId,
            'tanggal_dapat'=>$request->tanggal_dapat,'pengajar'=>$request->pengajar,
            'catatan'=>$request->catatan,'checked_by'=>auth()->id(),'checked_at'=>now(),
        ]);
        return response()->json(['success'=>true,'action'=>'checked']);
    }

}