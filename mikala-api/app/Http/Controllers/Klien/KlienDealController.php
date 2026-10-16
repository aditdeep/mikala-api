<?php

namespace App\Http\Controllers\Klien;

use App\Http\Controllers\Controller;
use App\Models\CmsLayanan;
use App\Models\Feedback;
use App\Models\Lead;
use App\Models\LeadExchange;
use App\Models\Mitra;
use App\Models\Pasien;
use App\Services\NotifikasiService;
use Illuminate\Http\Request;

/**
 * Isi apps Klien (dashboard, layanan, pasien, feedback) dari data Deal CC (cc_leads), bukan
 * tabel orders lama -- semua layanan berjalan (termasuk hasil migrasi PJ) ada di Deal.
 * Bentuk respons sengaja mirip respons Order lama (tipe_layanan, pasien.nama_lengkap,
 * mitra.user.name, tanggal_mulai, ...) supaya APK yg sudah terpasang tetap bisa membacanya.
 *
 * Catatan internal CC (kolom catatan, alasan_status, honor mitra, dll) TIDAK dikirim ke klien.
 */
class KlienDealController extends Controller
{
    // status Lead -> status gaya Order lama (dibaca frontend)
    private const STATUS = [
        Lead::STATUS_PROSES  => ['pending', 'Menunggu Konfirmasi'],
        Lead::STATUS_GANTUNG => ['pending', 'Menunggu Konfirmasi'],
        Lead::STATUS_DEAL    => ['active', 'Sedang Berjalan'],
        Lead::STATUS_STOP    => ['completed', 'Selesai'],
        Lead::STATUS_BATAL   => ['cancelled', 'Dibatalkan'],
    ];

    // staff yg menerima notif dari apps klien (akun manajemen/admin juga, bukan cuma CC)
    private const PENERIMA_CC = ['customer_care', 'manajemen'];

    private function klien(Request $request)
    {
        $klien = $request->user()->klien;
        abort_if(!$klien, 404, 'Profil klien tidak ditemukan');
        return $klien;
    }

    private function leads($klien)
    {
        return Lead::with(['layanan:id,nama', 'mitra:id,user_id,nama_lengkap,foto_url,tipe_pekerjaan', 'mitra.user:id,name'])
            ->where('klien_id', $klien->id);
    }

    /** Urutan tampil: berjalan dulu, lalu menunggu, lalu selesai/batal; terbaru di atas. */
    private function urut($query)
    {
        return $query->orderByRaw('CASE status WHEN 1 THEN 0 WHEN 0 THEN 1 WHEN 3 THEN 1 ELSE 2 END')
            ->orderByRaw('COALESCE(deal_at, created_at) DESC');
    }

    private function format(Lead $l, bool $detail = false): array
    {
        [$status, $label] = self::STATUS[(int) $l->status] ?? ['pending', 'Menunggu Konfirmasi'];
        $layanan = trim(($l->layanan->nama ?? '') . ($l->tier_nama ? ' · ' . $l->tier_nama : ''));
        $punyaMitra = in_array((int) $l->status, [Lead::STATUS_DEAL, Lead::STATUS_STOP], true) && $l->mitra;

        $data = [
            'id'              => $l->id,
            'order_number'    => $l->nomor_deal ?: $l->nomor,
            'tipe_layanan'    => $layanan ?: ($l->jasa_diminta ?: 'Layanan Perawatan'),
            'layanan_nama'    => $l->layanan->nama ?? null,
            'tier_nama'       => $l->tier_nama,
            'status'          => $status,
            'status_label'    => $label,
            'tanggal_mulai'   => $l->deal_at?->toDateString(),
            'tanggal_selesai' => (int) $l->status === Lead::STATUS_STOP ? $l->stop_at?->toDateString()
                               : ((int) $l->status === Lead::STATUS_BATAL ? $l->batal_at?->toDateString() : null),
            'pasien'          => ['id' => 'deal-' . $l->id, 'nama_lengkap' => $l->nama_pasien ?: '-'],
            'mitra'           => $punyaMitra ? [
                'id'             => $l->mitra->id,
                'nama_lengkap'   => $l->mitra->nama_lengkap,
                'foto_url'       => $l->mitra->foto_url,
                'tipe_pekerjaan' => $l->mitra->tipe_pekerjaan,
                'user'           => ['name' => $l->mitra->nama_lengkap],
            ] : null,
            'created_at'      => $l->created_at?->toDateTimeString(),
        ];

        if ($detail) {
            $data['alamat_layanan'] = $l->alamat_klien;
            $data['riwayat_mitra']  = $punyaMitra ? $this->riwayatMitra($l) : [];
            $data['sudah_feedback'] = Feedback::where('lead_id', $l->id)->exists();
            $data['bisa_feedback']  = $punyaMitra && !$data['sudah_feedback'];
        }
        return $data;
    }

    /** Urutan mitra yg pernah bertugas di Deal ini (dari data exchange/ganti mitra). */
    private function riwayatMitra(Lead $l): array
    {
        $ex = LeadExchange::where('lead_id', $l->id)->orderBy('exchanged_at')->get();
        $segmen = [];
        $mitraNow = $ex->first()->mitra_lama_id ?? $l->mitra_id;
        $mulai = $l->deal_at;
        foreach ($ex as $e) {
            $segmen[] = [$mitraNow, $mulai, $e->exchanged_at];
            $mitraNow = $e->mitra_baru_id;
            $mulai = $e->exchanged_at;
        }
        $berjalan = (int) $l->status === Lead::STATUS_DEAL;
        $segmen[] = [$mitraNow, $mulai, $berjalan ? null : $l->stop_at];

        $nama = Mitra::whereIn('id', array_filter(array_column($segmen, 0)))->pluck('nama_lengkap', 'id');
        $tgl = fn ($d) => $d ? \Carbon\Carbon::parse($d)->toDateString() : null;
        return array_reverse(array_map(fn ($s, $i) => [
            'nama'     => $nama[$s[0]] ?? '-',
            'mulai'    => $tgl($s[1]),
            'selesai'  => $tgl($s[2]),
            'berjalan' => $berjalan && $i === count($segmen) - 1,
        ], $segmen, array_keys($segmen)));
    }

    // GET /klien/dashboard
    public function dashboard(Request $request)
    {
        $klien = $this->klien($request);
        $recent = $this->urut($this->leads($klien))->limit(5)->get()->map(function ($l) {
            $f = $this->format($l);
            return ['id' => $f['id'], 'service_type' => $f['tipe_layanan'], 'tipe_layanan' => $f['tipe_layanan'],
                    'patient_name' => $f['pasien']['nama_lengkap'], 'status' => $f['status_label'], 'status_code' => $f['status']];
        });

        return response()->json(['success' => true, 'data' => [
            'active_services' => Lead::where('klien_id', $klien->id)->where('status', Lead::STATUS_DEAL)->count(),
            'total_patients'  => count($this->daftarPasien($klien)),
            'recent_services' => $recent,
        ]]);
    }

    // GET /klien/layanan
    public function index(Request $request)
    {
        $klien = $this->klien($request);
        $data = $this->urut($this->leads($klien))->get()->map(fn ($l) => $this->format($l))->values();
        return response()->json(['success' => true, 'data' => $data]);
    }

    // GET /klien/layanan/{id}
    public function show(Request $request, $id)
    {
        $klien = $this->klien($request);
        $lead = $this->leads($klien)->where('id', $id)->first();
        if (!$lead) return response()->json(['success' => false, 'message' => 'Layanan tidak ditemukan'], 404);
        return response()->json(['success' => true, 'data' => $this->format($lead, true)]);
    }

    /**
     * POST /klien/layanan -- permintaan layanan baru dari klien -> masuk Leads CC (status Proses).
     * Terima payload lama (service_type) maupun baru (cms_layanan_id).
     */
    public function store(Request $request)
    {
        $request->validate([
            'pasien_id'      => 'required',
            'cms_layanan_id' => 'nullable|exists:cms_layanan,id',
            'service_type'   => 'nullable|string|max:100',
            'tanggal_mulai'  => 'nullable|date',
            'catatan'        => 'nullable|string|max:2000',
        ]);
        $klien = $this->klien($request);
        $user = $request->user();

        // pasien: dari tabel pasien (id angka) atau dari Deal sebelumnya ("deal-<id>")
        $p = null; $asal = null;
        if (preg_match('/^deal-(\d+)$/', (string) $request->pasien_id, $m)) {
            $asal = Lead::where('klien_id', $klien->id)->find($m[1]);
        } else {
            $p = Pasien::where('klien_id', $klien->id)->find($request->pasien_id);
        }
        if (!$p && !$asal) return response()->json(['success' => false, 'message' => 'Pasien tidak ditemukan'], 422);

        $cms = $request->cms_layanan_id ? CmsLayanan::find($request->cms_layanan_id) : null;
        // APK lama kirim kode (homecare_harian, ...) -> jadikan label yg kebaca CC
        $jasa = $cms->nama ?? ($request->service_type ? ucwords(str_replace('_', ' ', $request->service_type)) : null);
        $catatan = 'Permintaan dari Apps Klien'
            . ($request->tanggal_mulai ? '. Rencana mulai: ' . \Carbon\Carbon::parse($request->tanggal_mulai)->translatedFormat('d F Y') : '')
            . ($request->catatan ? '. Catatan klien: ' . $request->catatan : '');

        $lead = Lead::create([
            'nomor'               => Lead::generateNomor(),
            'klien_id'            => $klien->id,
            'cms_layanan_id'      => $cms?->id,
            'nama_leads'          => $user->name,
            'kontak'              => $user->phone,
            'email_cust_pj'       => $user->email,
            'alamat_cust_pj'      => $klien->alamat !== '-' ? $klien->alamat : null,
            'nama_pasien'         => $p->nama_lengkap ?? $asal->nama_pasien,
            'alamat_klien'        => $p->alamat ?? $asal->alamat_klien,
            'tanggal_lahir_klien' => $p->tanggal_lahir ?? $asal->tanggal_lahir_klien,
            'jenis_kelamin_klien' => $p->jenis_kelamin ?? $asal->jenis_kelamin_klien,
            'diagnosis_awal'      => $p->riwayat_penyakit ?? $asal->diagnosis_awal,
            'hubungan_dengan_pasien' => $asal->hubungan_dengan_pasien ?? null,
            'jasa_diminta'        => $jasa,
            'sumber'              => 'Apps Klien',
            'catatan'             => $catatan,
            'status'              => Lead::STATUS_PROSES,
            'created_by'          => $user->id,
        ]);

        try {
            NotifikasiService::sendToRoles(self::PENERIMA_CC, 'order', 'Permintaan Layanan dari Apps Klien 📥',
                "{$user->name} ({$user->phone}) minta layanan " . ($jasa ?: '-') . " untuk pasien {$lead->nama_pasien}. Cek tab Leads.",
                ['related_type' => 'lead', 'related_id' => $lead->id]);
        } catch (\Throwable $e) { \Log::warning('notif leads apps klien gagal: ' . $e->getMessage()); }
        try {
            // konfirmasi ke klien sendiri (muncul di lonceng / daftar notifikasi apps klien)
            NotifikasiService::send($user->id, 'layanan', 'Permintaan Layanan Diterima 📨',
                'Permintaan layanan ' . ($jasa ?: 'perawatan') . " untuk {$lead->nama_pasien} sudah kami terima. Tim Customer Care Mikala akan segera menghubungi Anda.",
                ['related_type' => 'cc_lead', 'related_id' => $lead->id]);
        } catch (\Throwable $e) { \Log::warning('notif konfirmasi klien gagal: ' . $e->getMessage()); }

        return response()->json(['success' => true, 'message' => 'Permintaan layanan terkirim. Tim Customer Care akan segera menghubungi Anda.',
            'data' => $this->format($lead->fresh(['layanan', 'mitra.user']))], 201);
    }

    /**
     * POST /klien/layanan/{id}/feedback -- rating mitra utk Deal berjalan/selesai, 1x per Deal.
     * Terima payload app lama ({rating, catatan}) maupun lengkap (rating_kualitas, dst).
     */
    public function feedback(Request $request, $id)
    {
        $request->validate([
            'rating'                 => 'required_without:rating_kualitas|nullable|integer|min:1|max:5',
            'rating_kualitas'        => 'nullable|integer|min:1|max:5',
            'rating_profesionalisme' => 'nullable|integer|min:1|max:5',
            'rating_komunikasi'      => 'nullable|integer|min:1|max:5',
            'catatan'                => 'nullable|string|max:1000',
            'komentar'               => 'nullable|string|max:1000',
            'saran'                  => 'nullable|string|max:1000',
        ]);
        $klien = $this->klien($request);
        $lead = Lead::where('klien_id', $klien->id)->find($id);
        if (!$lead || !$lead->mitra_id || !in_array((int) $lead->status, [Lead::STATUS_DEAL, Lead::STATUS_STOP], true)) {
            return response()->json(['success' => false, 'message' => 'Layanan ini belum bisa diberi rating'], 400);
        }
        if (Feedback::where('lead_id', $lead->id)->exists()) {
            return response()->json(['success' => false, 'message' => 'Anda sudah memberi rating untuk layanan ini'], 400);
        }

        $r = (int) ($request->rating ?? $request->rating_kualitas);
        $fb = Feedback::create([
            'lead_id'                => $lead->id,
            'klien_id'               => $klien->id,
            'mitra_id'               => $lead->mitra_id,
            'rating_kualitas'        => $request->rating_kualitas ?? $r,
            'rating_profesionalisme' => $request->rating_profesionalisme ?? $r,
            'rating_komunikasi'      => $request->rating_komunikasi ?? $r,
            'komentar'               => $request->komentar ?? $request->catatan,
            'saran'                  => $request->saran,
            'is_published'           => true,
        ]);
        $lead->mitra?->updateRating();

        try {
            NotifikasiService::sendToRoles(self::PENERIMA_CC, 'feedback', 'Rating Baru dari Klien ⭐',
                "{$klien->nama_lengkap} memberi rating {$fb->rating_average} untuk mitra " . ($lead->mitra->nama_lengkap ?? '-') . " (pasien {$lead->nama_pasien}).",
                ['related_type' => 'lead', 'related_id' => $lead->id]);
        } catch (\Throwable $e) { \Log::warning('notif feedback gagal: ' . $e->getMessage()); }

        return response()->json(['success' => true, 'message' => 'Terima kasih, rating Anda sudah terkirim', 'data' => $fb]);
    }

    /**
     * Pasien = data di tabel pasien (yg didaftarkan klien sendiri) + pasien dari Deal yg belum
     * ada di tabel pasien (mis. hasil migrasi; tanpa tgl lahir/jenis kelamin -> read-only,
     * id "deal-<id>").
     */
    private function daftarPasien($klien): array
    {
        $tabel = Pasien::where('klien_id', $klien->id)->orderBy('nama_lengkap')->get()
            ->map(fn ($p) => array_merge($p->toArray(), ['sumber' => 'pasien', 'bisa_edit' => true]))->all();
        $ada = array_map(fn ($p) => mb_strtolower(trim($p['nama_lengkap'])), $tabel);

        $dariDeal = [];
        $leads = Lead::where('klien_id', $klien->id)->whereNotNull('nama_pasien')
            ->whereIn('status', [Lead::STATUS_DEAL, Lead::STATUS_STOP, Lead::STATUS_PROSES, Lead::STATUS_GANTUNG])
            ->orderByRaw('CASE status WHEN 1 THEN 0 ELSE 1 END')->orderByDesc('deal_at')->get();
        foreach ($leads as $l) {
            $key = mb_strtolower(trim($l->nama_pasien));
            if ($key === '' || in_array($key, $ada, true)) continue;
            $ada[] = $key;
            $dariDeal[] = [
                'id'               => 'deal-' . $l->id,
                'nama_lengkap'     => $l->nama_pasien,
                'tanggal_lahir'    => $l->tanggal_lahir_klien?->toDateString(),
                'jenis_kelamin'    => in_array($l->jenis_kelamin_klien, ['L', 'P'], true) ? $l->jenis_kelamin_klien : null,
                'alamat'           => $l->alamat_klien,
                'riwayat_penyakit' => $l->diagnosis_awal,
                'kontak_darurat_nama'  => $l->nama_leads,
                'kontak_darurat_phone' => $l->kontak,
                'kontak_darurat_relasi'=> $l->hubungan_dengan_pasien,
                'status'           => 'active',
                'sumber'           => 'deal',
                'bisa_edit'        => false,
            ];
        }
        return array_merge($dariDeal, $tabel);
    }

    // GET /klien/pasien
    public function indexPasien(Request $request)
    {
        return response()->json(['success' => true, 'data' => $this->daftarPasien($this->klien($request))]);
    }
}
