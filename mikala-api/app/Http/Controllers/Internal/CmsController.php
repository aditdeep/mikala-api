<?php

namespace App\Http\Controllers\Internal;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class CmsController extends Controller
{
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
        return response()->json(['success'=>true,'data'=>$q->paginate(12)]);
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
