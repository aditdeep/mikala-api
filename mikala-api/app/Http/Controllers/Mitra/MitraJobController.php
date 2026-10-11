<?php

namespace App\Http\Controllers\Mitra;

use App\Http\Controllers\Controller;
use App\Models\Lead;
use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * Tugas / penempatan Mitra.
 *
 * Sebelumnya membaca model Order (marketplace booking lama) yang sudah tidak dipakai sejak alur
 * pindah ke CC Leads/Deal, sehingga menu Tugas di app mitra selalu kosong walau mitra sudah
 * ditempatkan lewat "Tandai Deal". Sekarang sumbernya cc_leads (status Deal = sedang berjalan,
 * Stop = selesai). Bentuk respons sengaja dibuat kompatibel dgn field yang dipakai UI lama
 * (status/tipe_layanan/tanggal_mulai/pasien/klien/estimasi_earning).
 *
 * Status penempatan diatur CC dari panel internal (Tandai Deal / Stop), bukan oleh mitra.
 */
class MitraJobController extends Controller
{
    private function baseQuery(Request $request)
    {
        $mitra = $request->user()->mitra;
        if (!$mitra) return null;

        return Lead::where('mitra_id', $mitra->id)
            ->whereIn('status', [Lead::STATUS_DEAL, Lead::STATUS_STOP])
            ->with('layanan:id,nama');
    }

    private function present(Lead $lead): array
    {
        $aktif = $lead->status === Lead::STATUS_DEAL;
        $mulai = $lead->deal_at;
        $akhir = $lead->stop_at ?? now();
        $hari = $mulai ? max(0, Carbon::parse($mulai)->startOfDay()->diffInDays(Carbon::parse($akhir)->startOfDay()) + 1) : 0;

        // Sama dgn FinanceController::generatePayroll(): gaji/bulan = honor mitra + management fee
        $gajiBulanan = (float) ($lead->honor_mitra ?? 0) + (float) ($lead->management_fee ?? 0);
        $layanan = trim(($lead->layanan->nama ?? 'Layanan') . ($lead->tier_nama ? ' · ' . $lead->tier_nama : ''));
        $alamat = trim(implode(', ', array_filter([$lead->alamat_klien, $lead->alamat_klien_2])));

        return [
            'id'               => $lead->id,
            'sumber'           => 'deal',
            'kode_order'       => $lead->nomor_deal ?: $lead->nomor,
            'status'           => $aktif ? 'in_progress' : 'completed',
            'tipe_layanan'     => $layanan,
            'deskripsi_layanan'=> $lead->jasa_disetujui,
            'tanggal_mulai'    => $mulai?->toDateString(),
            'tanggal_selesai'  => $lead->stop_at?->toDateString(),
            'alamat_layanan'   => $alamat ?: null,
            'catatan'          => $lead->catatan,
            'model_gaji'       => 'bulanan',
            'gaji_bulanan'     => $gajiBulanan,
            'total_hari'       => $hari,
            // estimasi kasar: gaji/bulan diprorata 30 hari. Angka resmi tetap di menu Gaji (payroll).
            'estimasi_earning' => round($gajiBulanan / 30 * $hari),
            'pasien' => [
                'nama_lengkap'          => $lead->nama_pasien,
                'alamat'                => $alamat ?: null,
                'jenis_kelamin'         => $lead->jenis_kelamin_klien,
                'tanggal_lahir'         => $lead->tanggal_lahir_klien?->toDateString(),
                'riwayat_penyakit'      => $lead->diagnosis_awal ?: $lead->deskripsi_diagnosa,
                'deskripsi_diagnosa'    => $lead->deskripsi_diagnosa,
                'alat_medis'            => $lead->alat_medis,
                'mobilisasi'            => $lead->mobilisasi,
                'kontak_darurat_nama'   => $lead->nama_leads,
                'kontak_darurat_phone'  => $lead->kontak,
            ],
            'klien' => [
                // Penanggung jawab pasien (Cust/PJ) yg jadi kontak mitra di lapangan
                'nama_lengkap'        => $lead->nama_leads,
                'hubungan_dengan_pasien' => $lead->hubungan_dengan_pasien,
                'phone'               => $lead->kontak,
                'alamat'              => $lead->alamat_cust_pj,
            ],
        ];
    }

    public function index(Request $request)
    {
        $query = $this->baseQuery($request);
        if (!$query) {
            return response()->json(['success' => false, 'message' => 'Mitra profile not found'], 404);
        }

        // filter lama pakai status Order: in_progress (aktif) / completed (selesai)
        if ($request->status === 'in_progress') $query->where('status', Lead::STATUS_DEAL);
        elseif ($request->status === 'completed') $query->where('status', Lead::STATUS_STOP);

        $leads = $query->orderByRaw('status asc')->orderBy('deal_at', 'desc')->paginate(30);

        return response()->json([
            'success' => true,
            'data' => collect($leads->items())->map(fn ($l) => $this->present($l))->values(),
            'pagination' => [
                'total'        => $leads->total(),
                'per_page'     => $leads->perPage(),
                'current_page' => $leads->currentPage(),
                'last_page'    => $leads->lastPage(),
            ],
        ]);
    }

    public function show(Request $request, $id)
    {
        $query = $this->baseQuery($request);
        if (!$query) {
            return response()->json(['success' => false, 'message' => 'Mitra profile not found'], 404);
        }
        $lead = $query->where('id', $id)->first();
        if (!$lead) {
            return response()->json(['success' => false, 'message' => 'Tugas tidak ditemukan'], 404);
        }
        return response()->json(['success' => true, 'data' => $this->present($lead)]);
    }

    public function updateStatus(Request $request, $id)
    {
        return response()->json([
            'success' => false,
            'message' => 'Status penempatan diatur oleh tim Customer Care. Hubungi admin bila ada perubahan.',
        ], 422);
    }

    /**
     * Ringkasan utk Beranda mitra (dipakai route /mitra/dashboard).
     */
    public function summary(Request $request)
    {
        $query = $this->baseQuery($request);
        if (!$query) {
            return response()->json(['success' => false, 'message' => 'Mitra not found'], 404);
        }
        $mitra = $request->user()->mitra;
        $all = (clone $query)->orderByRaw('status asc')->orderBy('deal_at', 'desc')->get();

        return response()->json(['success' => true, 'data' => [
            'active_jobs'    => $all->where('status', Lead::STATUS_DEAL)->count(),
            'completed_jobs' => $all->where('status', Lead::STATUS_STOP)->count(),
            'total_earnings' => (float) \App\Models\Payroll::where('mitra_id', $mitra->id)->where('status', 'paid')->sum('total'),
            'recent_jobs'    => $all->take(3)->map(fn ($l) => $this->present($l) + ['patient_name' => $l->nama_pasien])->values(),
        ]]);
    }
}
