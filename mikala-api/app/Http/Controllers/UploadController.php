<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Cloudinary\Cloudinary;
use Cloudinary\Configuration\Configuration;

class UploadController extends Controller
{
    private function getCloudinary()
    {
        Configuration::instance([
            'cloud' => [
                'cloud_name' => env('CLOUDINARY_CLOUD_NAME'),
                'api_key'    => env('CLOUDINARY_API_KEY'),
                'api_secret' => env('CLOUDINARY_API_SECRET'),
            ],
            'url' => ['secure' => true]
        ]);
        return new Cloudinary();
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
