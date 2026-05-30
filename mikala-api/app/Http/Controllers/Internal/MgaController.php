<?php
namespace App\Http\Controllers\Internal;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MgaController extends Controller
{
    // ── Settings ──────────────────────────────────────────────────────────────
    public function getSettings() {
        $settings = DB::table('mga_settings')->get()->keyBy('key');
        return response()->json(['success'=>true,'data'=>$settings->map(fn($s)=>$s->value)]);
    }
    public function updateSettings(Request $request) {
        foreach ($request->all() as $key => $value) {
            DB::table('mga_settings')->updateOrInsert(['key'=>$key],['value'=>$value,'updated_at'=>now()]);
        }
        return response()->json(['success'=>true,'message'=>'Settings updated']);
    }

    // ── Artikel ───────────────────────────────────────────────────────────────
    public function artikelIndex(Request $request) {
        $data = DB::table('mga_artikel')
            ->when($request->search, fn($q) => $q->where('judul','like',"%{$request->search}%"))
            ->when($request->status, fn($q) => $q->where('status',$request->status))
            ->orderBy('created_at','desc')
            ->paginate($request->per_page ?? 10);
        return response()->json(['success'=>true,'data'=>$data->items(),'pagination'=>['total'=>$data->total(),'last_page'=>$data->lastPage()]]);
    }
    public function artikelStore(Request $request) {
        $request->validate(['judul'=>'required','konten'=>'required']);
        $id = DB::table('mga_artikel')->insertGetId([
            'judul'=>$request->judul,'slug'=>Str::slug($request->judul).'-'.time(),
            'konten'=>$request->konten,'ringkasan'=>$request->ringkasan,
            'gambar'=>$request->gambar,'kategori'=>$request->kategori??'Informasi',
            'author'=>$request->author??auth()->user()->name,
            'status'=>$request->status??'draft','created_at'=>now(),'updated_at'=>now(),
        ]);
        return response()->json(['success'=>true,'data'=>DB::table('mga_artikel')->find($id)],201);
    }
    public function artikelUpdate(Request $request, $id) {
        DB::table('mga_artikel')->where('id',$id)->update(array_merge(
            $request->only(['judul','konten','ringkasan','gambar','kategori','author','status']),
            ['updated_at'=>now()]
        ));
        return response()->json(['success'=>true,'data'=>DB::table('mga_artikel')->find($id)]);
    }
    public function artikelDestroy($id) {
        DB::table('mga_artikel')->where('id',$id)->delete();
        return response()->json(['success'=>true]);
    }

    // ── Galeri ────────────────────────────────────────────────────────────────
    public function galeriIndex() {
        return response()->json(['success'=>true,'data'=>DB::table('mga_galeri')->orderBy('urutan')->get()]);
    }
    public function galeriStore(Request $request) {
        $id = DB::table('mga_galeri')->insertGetId([
            'url'=>$request->url,'caption'=>$request->caption,
            'kategori'=>$request->kategori??'Umum','urutan'=>$request->urutan??0,
            'created_at'=>now(),'updated_at'=>now(),
        ]);
        return response()->json(['success'=>true,'data'=>DB::table('mga_galeri')->find($id)],201);
    }
    public function galeriDestroy($id) {
        DB::table('mga_galeri')->where('id',$id)->delete();
        return response()->json(['success'=>true]);
    }

    // ── Program ───────────────────────────────────────────────────────────────
    public function programIndex() {
        return response()->json(['success'=>true,'data'=>DB::table('mga_program')->orderBy('urutan')->get()]);
    }
    public function programStore(Request $request) {
        $id = DB::table('mga_program')->insertGetId(array_merge(
            $request->only(['judul','subtitle','deskripsi','icon','durasi','biaya','kuota','kurikulum','syarat','warna','urutan']),
            ['status'=>'aktif','created_at'=>now(),'updated_at'=>now()]
        ));
        return response()->json(['success'=>true,'data'=>DB::table('mga_program')->find($id)],201);
    }
    public function programUpdate(Request $request, $id) {
        DB::table('mga_program')->where('id',$id)->update(array_merge($request->all(),['updated_at'=>now()]));
        return response()->json(['success'=>true,'data'=>DB::table('mga_program')->find($id)]);
    }
    public function programDestroy($id) {
        DB::table('mga_program')->where('id',$id)->delete();
        return response()->json(['success'=>true]);
    }

    // ── Testimoni ─────────────────────────────────────────────────────────────
    public function testimoniIndex() {
        return response()->json(['success'=>true,'data'=>DB::table('mga_testimoni')->orderBy('created_at','desc')->get()]);
    }
    public function testimoniStore(Request $request) {
        $id = DB::table('mga_testimoni')->insertGetId(array_merge(
            $request->only(['nama','asal','jabatan','teks','foto','rating']),
            ['status'=>'aktif','created_at'=>now(),'updated_at'=>now()]
        ));
        return response()->json(['success'=>true,'data'=>DB::table('mga_testimoni')->find($id)],201);
    }
    public function testimoniDestroy($id) {
        DB::table('mga_testimoni')->where('id',$id)->delete();
        return response()->json(['success'=>true]);
    }
}
