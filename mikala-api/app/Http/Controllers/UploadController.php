<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Cloudinary\Cloudinary;

class UploadController extends Controller
{
    private function getCloudinary()
    {
        $url = config('cloudinary.cloud_url');
        return new Cloudinary($url);
    }

    public function upload(Request $request)
    {
        try {
            if (!$request->hasFile('file')) {
                return response()->json(['success' => false, 'message' => 'No file uploaded'], 400);
            }

            $file   = $request->file('file');
            $folder = 'mikala/' . ($request->folder ?? 'general');

            $cloudinary = $this->getCloudinary();
            $result = $cloudinary->uploadApi()->upload($file->getRealPath(), [
                'folder'        => $folder,
                'resource_type' => 'auto',
            ]);

            return response()->json([
                'success'   => true,
                'url'       => $result['secure_url'],
                'public_id' => $result['public_id'],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Upload failed: ' . $e->getMessage()
            ], 500);
        }
    }

    public function uploadBase64(Request $request)
    {
        try {
            $folder     = 'mikala/' . ($request->folder ?? 'general');
            $cloudinary = $this->getCloudinary();
            $result     = $cloudinary->uploadApi()->upload($request->data, [
                'folder'        => $folder,
                'resource_type' => 'auto',
            ]);

            return response()->json([
                'success'   => true,
                'url'       => $result['secure_url'],
                'public_id' => $result['public_id'],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Upload failed: ' . $e->getMessage()
            ], 500);
        }
    }
}
