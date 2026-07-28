<?php

namespace App\Http\Controllers\Internal;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class CmsController extends Controller
{
    // Pencarian global: artikel, layanan, penunjang
    public function search(Request $request) {
        $q = trim($request->q ?? '');
        if ($q === '') return response()->json(['success'=>true,'data'=>[]]);
        $like = '%'.$q.'%';

        $artikel = \App\Models\CmsArtikel::where('status','published')->where('published_at','<=', now())
            ->where(function($w) use ($like) { $w->where('judul','ILIKE',$like)->orWhere('excerpt','ILIKE',$like); })
            ->orderBy('published_at','desc')->limit(10)->get()
            ->map(fn($a) => ['type'=>'artikel','title'=>$a->judul,'excerpt'=>$a->excerpt,'thumbnail'=>$a->thumbnail,'slug'=>$a->slug]);

        $layanan = \App\Models\CmsLayanan::where(function($w) use ($like) {
                $w->where('nama','ILIKE',$like)->orWhere('deskripsi','ILIKE',$like)->orWhere('deskripsi_panjang','ILIKE',$like);
            })->orderBy('urutan')->limit(10)->get()
            ->map(fn($l) => ['type'=>'layanan','title'=>$l->nama,'excerpt'=>$l->deskripsi,'thumbnail'=>$l->gambar,'nama'=>$l->nama]);

        $penunjang = \App\Models\CmsPenunjang::where(function($w) use ($like) {
                $w->where('nama','ILIKE',$like)->orWhere('deskripsi','ILIKE',$like)->orWhere('deskripsi_panjang','ILIKE',$like);
            })->orderBy('urutan')->limit(10)->get()
            ->map(fn($p) => ['type'=>'penunjang','title'=>$p->nama,'excerpt'=>$p->deskripsi,'thumbnail'=>$p->gambar,'nama'=>$p->nama]);

        $results = collect()->concat($layanan)->concat($penunjang)->concat($artikel)->values();
        return response()->json(['success'=>true,'data'=>$results]);
    }

    // Artikel
    public function indexArtikel(Request $request) {
        $q = \App\Models\CmsArtikel::orderBy('published_at','desc')->orderBy('created_at','desc');
        if ($request->user()) {
            // admin: tampil semua, bisa filter status
            if ($request->status) $q->where('status', $request->status);
        } else {
            // publik: hanya published & sudah waktunya
            $q->where('status','published')->where('published_at','<=', now());
        }
        if ($request->search) {
            $q->where('judul', 'ILIKE', '%'.$request->search.'%');
        }
        if ($request->kategori && $request->kategori !== 'Semuanya' && $request->kategori !== 'Semua') {
            $q->whereRaw('LOWER(TRIM(kategori)) = ?', [mb_strtolower(trim($request->kategori))]);
        }
        $perPage = (int) ($request->per_page ?: 12);
        return response()->json(['success'=>true,'data'=>$q->paginate($perPage)]);
    }
    public function storeArtikel(Request $request) {
        $request->validate(['judul'=>'required','konten'=>'required','slug'=>'required']);
        $data = $request->all();
        $publishedAt = $request->published_at ? \Carbon\Carbon::parse($request->published_at) : now();
        $data['published_at'] = $publishedAt;
        if (($data['status'] ?? 'published') === 'published' && $publishedAt->isFuture()) {
            $data['status'] = 'scheduled';
        }
        $artikel = \App\Models\CmsArtikel::create($data + ['author_id'=>$request->user()->id]);
        return response()->json(['success'=>true,'data'=>$artikel],201);
    }
    public function updateArtikel(Request $request, $id) {
        $artikel = \App\Models\CmsArtikel::findOrFail($id);
        $data = $request->all();
        if ($request->filled('published_at')) {
            $publishedAt = \Carbon\Carbon::parse($request->published_at);
            $data['published_at'] = $publishedAt;
            if (($data['status'] ?? $artikel->status) === 'published' && $publishedAt->isFuture()) {
                $data['status'] = 'scheduled';
            }
        }
        $artikel->update($data);
        return response()->json(['success'=>true,'data'=>$artikel]);
    }
    public function deleteArtikel($id) {
        \App\Models\CmsArtikel::findOrFail($id)->delete();
        return response()->json(['success'=>true]);
    }
    public function showArtikel($slug) {
        $a = \App\Models\CmsArtikel::where('slug',$slug)->where('status','published')->where('published_at','<=', now())->firstOrFail();
        // Clean literal \n from content
        $a->konten = str_replace(['\\n', '\\r', '\n\n\n'], ['', '', '\n'], $a->konten ?? '');
        return response()->json(['success'=>true,'data'=>$a]);
    }

    // Layanan
    public function indexLayanan(Request $request) {
        return response()->json(['success'=>true,'data'=>\App\Models\CmsLayanan::orderBy('urutan')->get()]);
    }
    public function storeLayanan(Request $request) {
        $l = \App\Models\CmsLayanan::create($request->all());
        return response()->json(['success'=>true,'data'=>$l],201);
    }
    public function updateLayanan(Request $request, $id) {
        $l = \App\Models\CmsLayanan::findOrFail($id);
        $l->update($request->all());
        return response()->json(['success'=>true,'data'=>$l]);
    }
    public function deleteLayanan($id) {
        \App\Models\CmsLayanan::findOrFail($id)->delete();
        return response()->json(['success'=>true]);
    }

    // Penunjang Kesehatan
    public function indexPenunjang(Request $request) {
        return response()->json(['success'=>true,'data'=>\App\Models\CmsPenunjang::orderBy('urutan')->get()]);
    }
    public function storePenunjang(Request $request) {
        $p = \App\Models\CmsPenunjang::create($request->all());
        return response()->json(['success'=>true,'data'=>$p],201);
    }
    public function updatePenunjang(Request $request, $id) {
        $p = \App\Models\CmsPenunjang::findOrFail($id);
        $p->update($request->all());
        return response()->json(['success'=>true,'data'=>$p]);
    }
    public function deletePenunjang($id) {
        \App\Models\CmsPenunjang::findOrFail($id)->delete();
        return response()->json(['success'=>true]);
    }

    // Galeri
    public function indexGaleri() {
        return response()->json(['success'=>true,'data'=>\App\Models\CmsGaleri::orderBy('created_at','desc')->get()]);
    }
    public function storeGaleri(Request $request) {
        $g = \App\Models\CmsGaleri::create($request->all());
        return response()->json(['success'=>true,'data'=>$g],201);
    }
    public function deleteGaleri($id) {
        \App\Models\CmsGaleri::findOrFail($id)->delete();
        return response()->json(['success'=>true]);
    }

    // Testimoni
    public function indexTestimoni() {
        return response()->json(['success'=>true,'data'=>\App\Models\CmsTestimoni::where('status','approved')->orderBy('created_at','desc')->get()]);
    }
    public function storeTestimoni(Request $request) {
        $t = \App\Models\CmsTestimoni::create($request->all() + ['status'=>'pending']);
        return response()->json(['success'=>true,'data'=>$t],201);
    }
    public function updateTestimoni(Request $request, $id) {
        $t = \App\Models\CmsTestimoni::findOrFail($id);
        $t->update($request->all());
        return response()->json(['success'=>true,'data'=>$t]);
    }

    // Settings
    public function getSettings() {
        $settings = \App\Models\Setting::whereIn('key', [
            'site_title','site_description','site_keywords',
            'hero_title','hero_subtitle','hero_image',
            'wa_number','email_cs','alamat',
            'facebook','instagram','tiktok','youtube',
            'stats_customer','stats_nakes','stats_mitra',
            'hero_slides','stats_google','profile_text','profile_images',
            'video_url','video_title','alasan_list','sertifikat_images',
            'google_review_url',
            // Halaman Perusahaan
            'prsh_hero_images','prsh_hero_title','prsh_hero_text',
            'prsh_direktur_nama','prsh_direktur_jabatan','prsh_direktur_foto',
            'prsh_direktur_sambutan','prsh_direktur_sambutan_lengkap',
            'prsh_visi',
            'prsh_misi_list',
            'prsh_legalitas_images','prsh_legalitas_sk','prsh_legalitas_nib','prsh_legalitas_izin',
            'prsh_checklist_list','prsh_checklist_image',
            'prsh_mga_text','prsh_mga_images','prsh_mga_url',
            'prsh_loker_text','prsh_loker_image','prsh_loker_url',
            // Halaman Layanan
            'layanan_hero_images',
            // Footer
            'footer_social_icons',
        ])->pluck('value','key');
        return response()->json(['success'=>true,'data'=>$settings]);
    }
    public function updateSettings(Request $request) {
        foreach ($request->all() as $key => $value) {
            \App\Models\Setting::updateOrCreate(['key'=>$key],['value'=>$value]);
        }
        return response()->json(['success'=>true,'message'=>'Settings updated']);
    }
}
