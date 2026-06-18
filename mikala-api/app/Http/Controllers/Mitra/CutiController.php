<?php

namespace App\Http\Controllers\Mitra;

use App\Http\Controllers\Controller;
use App\Models\Cuti;
use Illuminate\Http\Request;

class CutiController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $mitra = $user->mitra;
        if (!$mitra) return response()->json(['success'=>false,'message'=>'Mitra not found'],404);

        $cuti = Cuti::where('mitra_id', $mitra->id)
            ->orderBy('created_at', 'desc')
            ->get();

        // Stats bulan ini
        $bulanIni = \DB::table('payroll_settings')->where('key', 'max_cuti_per_bulan')->value('value') ?? 2;
        $cutiBulanIni = Cuti::where('mitra_id', $mitra->id)
            ->where('status', 'approved')
            ->whereMonth('tanggal_mulai', now()->month)
            ->whereYear('tanggal_mulai', now()->year)
            ->sum('jumlah_hari');

        return response()->json([
            'success' => true,
            'data'    => $cuti,
            'stats'   => [
                'max_per_bulan'    => intval($bulanIni),
                'terpakai_bulanan' => intval($cutiBulanIni),
                'sisa_bulanan'     => max(0, intval($bulanIni) - intval($cutiBulanIni)),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'tanggal_mulai'  => 'required|date|after_or_equal:today',
            'tanggal_selesai'=> 'required|date|after_or_equal:tanggal_mulai',
            'alasan'         => 'required|string|max:500',
        ]);

        $user  = $request->user();
        $mitra = $user->mitra;
        if (!$mitra) return response()->json(['success'=>false,'message'=>'Mitra not found'],404);

        $mulai   = \Carbon\Carbon::parse($request->tanggal_mulai);
        $selesai = \Carbon\Carbon::parse($request->tanggal_selesai);
        $hari    = $mulai->diffInDays($selesai) + 1;

        // Cek quota cuti
        $max = intval(\DB::table('payroll_settings')->where('key','max_cuti_per_bulan')->value('value') ?? 2);
        $bulanThis = Cuti::where('mitra_id', $mitra->id)
            ->whereIn('status', ['approved','pending'])
            ->whereMonth('tanggal_mulai', $mulai->month)
            ->whereYear('tanggal_mulai', $mulai->year)
            ->sum('jumlah_hari');
        if ($bulanThis + $hari > $max) {
            return response()->json([
                'success' => false,
                'message' => "Quota cuti bulan ini hanya $max hari, sudah terpakai/pending $bulanThis hari",
            ], 400);
        }

        $cuti = Cuti::create([
            'mitra_id'        => $mitra->id,
            'tanggal_mulai'   => $request->tanggal_mulai,
            'tanggal_selesai' => $request->tanggal_selesai,
            'jumlah_hari'     => $hari,
            'alasan'          => $request->alasan,
            'status'          => 'pending',
        ]);

        // Notif realtime ke staff Finance: ada pengajuan cuti baru
        $financeUserIds = \App\Models\User::byRole('finance')->pluck('id')->toArray();
        if (!empty($financeUserIds)) {
            \App\Services\NotifikasiService::sendBulk(
                $financeUserIds,
                'cuti',
                'Pengajuan Cuti Baru 📝',
                ($mitra->nama_lengkap ?? 'Mitra') . " mengajukan cuti " . $hari . " hari (" . $request->tanggal_mulai . " s/d " . $request->tanggal_selesai . "). Perlu persetujuan.",
                ['related_type' => 'cuti', 'related_id' => $cuti->id]
            );
        }

        return response()->json(['success'=>true,'data'=>$cuti], 201);
    }
}
