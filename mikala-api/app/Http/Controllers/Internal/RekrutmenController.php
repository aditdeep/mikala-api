<?php
namespace App\Http\Controllers\Internal;

use App\Http\Controllers\Controller;
use App\Models\Mitra;
use App\Models\Agen;
use App\Models\User;
use App\Models\MitraKreditPelatihan;
use App\Models\MitraJadwalInterview;
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
            if ($request->has('status_rekrutmen')) $query->where('status_rekrutmen', $request->status_rekrutmen);
            if ($request->has('search')) {
                $search = $request->search;
                $query->where(function($q) use ($search) {
                    $q->where('nama_lengkap','like',"%{$search}%")
                      ->orWhereHas('user', fn($q2) => $q2->where('name','like',"%{$search}%")->orWhere('email','like',"%{$search}%"));
                });
            }
            $mitra = $query->orderBy('created_at','desc')->paginate(15);
            return response()->json(['success'=>true,'data'=>$mitra->items(),'pagination'=>[
                'total'=>$mitra->total(),'per_page'=>$mitra->perPage(),
                'current_page'=>$mitra->currentPage(),'last_page'=>$mitra->lastPage()
            ]]);
        } catch (\Exception $e) {
            return response()->json(['success'=>false,'message'=>$e->getMessage()],500);
        }
    }

    public function store(Request $request)
    {
        $request->validate([
            'name'=>'required|string|max:255','email'=>'required|email|unique:users,email',
            'phone'=>'required|string|max:20','password'=>'required|min:8',
        ]);
        DB::beginTransaction();
        try {
            $user = User::create([
                'name'=>$request->name,'email'=>strtolower($request->email),
                'phone'=>$request->phone,'password'=>Hash::make($request->password),
                'role'=>'mitra','status'=>'active',
            ]);
            $mitra = Mitra::create([
                'user_id'=>$user->id,'nik'=>$request->nik ?? 'NIK-'.time(),
                'nama_lengkap'=>$request->name,'alamat'=>$request->alamat,
                'kota'=>$request->kota ?? '-','provinsi'=>$request->provinsi ?? '-',
                'tanggal_lahir'=>$request->tanggal_lahir,'jenis_kelamin'=>$request->jenis_kelamin,
                'pendidikan_terakhir'=>$request->pendidikan,'pengalaman'=>$request->pengalaman,
                'foto_url'=>$request->foto_url,'cv_file'=>$request->ktp_file,
                'status'=>'training','training_status'=>'pending','is_verified'=>false,
                'status_rekrutmen'=>'pending',
            ]);
            DB::commit();
            return response()->json(['success'=>true,'message'=>'Mitra berhasil didaftarkan','data'=>$mitra->load('user')],201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success'=>false,'message'=>$e->getMessage()],500);
        }
    }

    public function show($id)
    {
        try {
            $mitra = Mitra::with(['user','kreditPelatihan','jadwalInterview'])->findOrFail($id);
            return response()->json(['success'=>true,'data'=>$mitra]);
        } catch (\Exception $e) {
            return response()->json(['success'=>false,'message'=>'Mitra not found'],404);
        }
    }

    public function update(Request $request, $id)
    {
        DB::beginTransaction();
        try {
            $mitra = Mitra::with('user')->findOrFail($id);
            if ($request->has('name') || $request->has('email') || $request->has('phone'))
                $mitra->user->update($request->only(['name','email','phone']));
            $fields = ['alamat','tanggal_lahir','jenis_kelamin','pendidikan_terakhir'=>$request->pendidikan,
                'pengalaman','status','training_status','foto_url','cv_file','price_rate','catatan_rekrutmen','payment_type'];
            $updateData = array_filter([
                'alamat'=>$request->alamat,'tanggal_lahir'=>$request->tanggal_lahir,
                'jenis_kelamin'=>$request->jenis_kelamin,'pendidikan_terakhir'=>$request->pendidikan,
                'pengalaman'=>$request->pengalaman,'status'=>$request->status,
                'training_status'=>$request->training_status,'foto_url'=>$request->foto_url,
                'cv_file'=>$request->ktp_file ?? $request->cv_file,'price_rate'=>$request->price_rate,
                'catatan_rekrutmen'=>$request->catatan_rekrutmen,'payment_type'=>$request->payment_type,
            ], fn($v) => !is_null($v));
            $mitra->update($updateData);
            DB::commit();
            return response()->json(['success'=>true,'data'=>$mitra->load('user')]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success'=>false,'message'=>$e->getMessage()],500);
        }
    }

    public function destroy($id)
    {
        try {
            $mitra = Mitra::findOrFail($id);
            $mitra->update(['status'=>'keluar']);
            $mitra->user->update(['status'=>'inactive']);
            return response()->json(['success'=>true,'message'=>'Mitra dinonaktifkan']);
        } catch (\Exception $e) {
            return response()->json(['success'=>false,'message'=>$e->getMessage()],500);
        }
    }

    public function terima(Request $request, $id)
    {
        $request->validate(['price_rate'=>'required|numeric|min:0']);
        DB::beginTransaction();
        try {
            $mitra = Mitra::findOrFail($id);
            $mitra->update([
                'status_rekrutmen'=>'verified','status'=>'training',
                'price_rate'=>$request->price_rate,'catatan_rekrutmen'=>$request->catatan_rekrutmen,
                'verified_at'=>now(),'verified_by'=>auth()->id(),'is_verified'=>true,
            ]);
            if ($mitra->payment_type === 'kredit' && $request->total_biaya > 0) {
                MitraKreditPelatihan::create([
                    'mitra_id'=>$mitra->id,'total_biaya'=>$request->total_biaya,
                    'total_terbayar'=>0,'sisa_tagihan'=>$request->total_biaya,
                    'cicilan_per_job'=>$request->cicilan_per_job ?? 0,
                    'status'=>'active','created_by'=>auth()->id(),
                ]);
            }
            DB::commit();
            return response()->json(['success'=>true,'message'=>'Mitra diterima','data'=>$mitra->fresh(['kreditPelatihan'])]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success'=>false,'message'=>$e->getMessage()],500);
        }
    }

    public function tolak(Request $request, $id)
    {
        $request->validate(['catatan_rekrutmen'=>'required|string']);
        $mitra = Mitra::findOrFail($id);
        $mitra->update(['status_rekrutmen'=>'rejected','catatan_rekrutmen'=>$request->catatan_rekrutmen]);
        return response()->json(['success'=>true,'message'=>'Mitra ditolak','data'=>$mitra]);
    }

    public function buatJadwalInterview(Request $request, $mitraId)
    {
        $request->validate(['jadwal_at'=>'required|date','tipe'=>'required|in:offline,online']);
        $jadwal = MitraJadwalInterview::create([
            'mitra_id'=>$mitraId,'jadwal_at'=>$request->jadwal_at,'tipe'=>$request->tipe,
            'lokasi'=>$request->lokasi,'link_online'=>$request->link_online,
            'catatan'=>$request->catatan,'interviewer_id'=>$request->interviewer_id ?? auth()->id(),
            'status'=>'scheduled',
        ]);
        return response()->json(['success'=>true,'data'=>$jadwal],201);
    }

    public function selesaiInterview($jadwalId)
    {
        $jadwal = MitraJadwalInterview::findOrFail($jadwalId);
        $jadwal->update(['status'=>'done','done_at'=>now()]);
        return response()->json(['success'=>true,'message'=>'Interview selesai']);
    }

    public function jadwalInterviewList(Request $request)
    {
        $data = MitraJadwalInterview::with(['mitra:id,nama_lengkap,foto_url','interviewer:id,name'])
            ->when($request->status, fn($q) => $q->where('status',$request->status))
            ->when($request->mitra_id, fn($q) => $q->where('mitra_id',$request->mitra_id))
            ->orderBy('jadwal_at')->paginate(15);
        return response()->json($data);
    }

    public function kreditPelatihanList(Request $request)
    {
        $data = MitraKreditPelatihan::with('mitra:id,nama_lengkap,foto_url,no_hp')
            ->when($request->status, fn($q) => $q->where('status',$request->status))
            ->orderBy('created_at','desc')->paginate(15);
        return response()->json(['success'=>true,'data'=>$data]);
    }

    public function updateKredit(Request $request, $kreditId)
    {
        $request->validate(['cicilan_per_job'=>'required|numeric|min:0']);
        $kredit = MitraKreditPelatihan::findOrFail($kreditId);
        $kredit->update($request->only(['cicilan_per_job','total_biaya','keterangan']));
        if ($request->total_biaya)
            $kredit->update(['sisa_tagihan' => $request->total_biaya - $kredit->total_terbayar]);
        return response()->json(['success'=>true,'data'=>$kredit]);
    }

    public function report(Request $request)
    {
        try {
            $total = Mitra::count();
            $pending = Mitra::where('status_rekrutmen','pending')->count();
            $verified = Mitra::where('status_rekrutmen','verified')->count();
            $rejected = Mitra::where('status_rekrutmen','rejected')->count();
            $aktif = Mitra::where('status','available')->count();
            return response()->json(['success'=>true,'data'=>compact('total','pending','verified','rejected','aktif')]);
        } catch (\Exception $e) {
            return response()->json(['success'=>false,'message'=>$e->getMessage()],500);
        }
    }

    public function reportMitraBaru(Request $request)
    {
        try {
            $start = $request->input('start_date', now()->startOfMonth());
            $end   = $request->input('end_date', now()->endOfMonth());
            $mitra = Mitra::whereBetween('created_at',[$start,$end])->with('user')->get();
            return response()->json(['success'=>true,'data'=>['total'=>$mitra->count(),'mitra'=>$mitra]]);
        } catch (\Exception $e) {
            return response()->json(['success'=>false,'message'=>$e->getMessage()],500);
        }
    }

    public function reportMitraKeluar(Request $request)
    {
        try {
            $start = $request->input('start_date', now()->startOfMonth());
            $end   = $request->input('end_date', now()->endOfMonth());
            $mitra = Mitra::where('status','keluar')->whereBetween('updated_at',[$start,$end])->with('user')->get();
            return response()->json(['success'=>true,'data'=>['total'=>$mitra->count(),'mitra'=>$mitra]]);
        } catch (\Exception $e) {
            return response()->json(['success'=>false,'message'=>$e->getMessage()],500);
        }
    }

    public function reportAgenInstitusi(Request $request)
    {
        try {
            $agen = Agen::with('user')->select('institution_name', DB::raw('count(*) as total'))->groupBy('institution_name')->get();
            return response()->json(['success'=>true,'data'=>$agen]);
        } catch (\Exception $e) {
            return response()->json(['success'=>false,'message'=>$e->getMessage()],500);
        }
    }
}
