<?php
namespace App\Http\Controllers\Internal;

use App\Http\Controllers\Controller;
use App\Models\Lembaga;
use App\Models\MitraReferral;
use App\Models\FeeLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LembagaController extends Controller
{
    public function index(Request $request)
    {
        $data = Lembaga::withCount('mitra')
            ->when($request->search, fn($q) => $q->where('nama','like',"%{$request->search}%")->orWhere('kota','like',"%{$request->search}%"))
            ->when($request->status, fn($q) => $q->where('status',$request->status))
            ->when($request->tipe,   fn($q) => $q->where('tipe',$request->tipe))
            ->orderBy('created_at','desc')
            ->paginate(15);
        return response()->json(['success'=>true,'data'=>$data->items(),'pagination'=>['total'=>$data->total(),'last_page'=>$data->lastPage(),'current_page'=>$data->currentPage()]]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'nama'         => 'required|string|max:255',
            'tipe'         => 'required|in:lpk,sekolah,universitas,komunitas,perusahaan,lainnya',
            'fee_per_mitra'=> 'nullable|numeric|min:0',
        ]);
        $lembaga = Lembaga::create([...$request->only(['nama','tipe','kontak_nama','kontak_hp','kontak_email','alamat','kota','provinsi','fee_per_mitra','catatan']),'status'=>'aktif','created_by'=>auth()->id()]);
        return response()->json(['success'=>true,'data'=>$lembaga],201);
    }

    public function show($id) {
        $lembaga = Lembaga::withCount('mitra')->with('mitra:id,nama_lengkap,foto_url,status,created_at')->findOrFail($id);
        return response()->json(['success'=>true,'data'=>$lembaga]);
    }

    public function update(Request $request, $id) {
        $lembaga = Lembaga::findOrFail($id);
        $lembaga->update($request->only(['nama','tipe','kontak_nama','kontak_hp','kontak_email','alamat','kota','provinsi','fee_per_mitra','status','catatan']));
        return response()->json(['success'=>true,'data'=>$lembaga]);
    }

    public function destroy($id) {
        $lembaga = Lembaga::findOrFail($id);
        $lembaga->update(['status'=>'nonaktif']);
        return response()->json(['success'=>true,'message'=>'Lembaga dinonaktifkan']);
    }

    public function listPublic() {
        $data = Lembaga::where('status','aktif')->select('id','nama','tipe','kota')->orderBy('nama')->get();
        return response()->json(['success'=>true,'data'=>$data]);
    }

    // Kontrol Fee — list semua fee pending/paid
    public function feeList(Request $request) {
        $data = MitraReferral::with(['mitra:id,nama_lengkap,foto_url','lembaga:id,nama','referrerMitra:id,nama_lengkap,foto_url'])
            ->when($request->fee_status, fn($q) => $q->where('fee_status',$request->fee_status))
            ->when($request->sumber_tipe, fn($q) => $q->where('sumber_tipe',$request->sumber_tipe))
            
            ->orderBy('created_at','desc')
            ->paginate(20);
        return response()->json(['success'=>true,'data'=>$data->items(),'pagination'=>['total'=>$data->total(),'last_page'=>$data->lastPage()]]);
    }

    // Bayar fee
    public function bayarFee($referralId) {
        DB::beginTransaction();
        try {
            $referral = MitraReferral::findOrFail($referralId);
            $referral->update(['fee_status'=>'paid','fee_paid_at'=>now(),'fee_paid_by'=>auth()->id()]);
            FeeLog::create([
                'referral_id'   => $referral->id,
                'penerima_tipe' => $referral->sumber_tipe,
                'penerima_id'   => $referral->lembaga_id ?? $referral->referrer_mitra_id,
                'jumlah'        => $referral->fee_amount,
                'status'        => 'paid',
                'keterangan'    => 'Fee dibayar saat mitra diterima',
                'paid_at'       => now(),
                'paid_by'       => auth()->id(),
            ]);
            DB::commit();
            return response()->json(['success'=>true,'message'=>'Fee berhasil dibayar']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success'=>false,'message'=>$e->getMessage()],500);
        }
    }
}
