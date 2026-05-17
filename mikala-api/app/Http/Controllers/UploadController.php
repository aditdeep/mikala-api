<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use CloudinaryLabs\CloudinaryLaravel\Facades\Cloudinary;

class UploadController extends Controller
{
    public function upload(Request $request)
    {
        $request->validate([
            'file'   => 'required|file|max:10240',
            'folder' => 'nullable|string',
            'type'   => 'nullable|string',
        ]);

        try {
            $folder = 'mikala/' . ($request->folder ?? 'general');
            $result = Cloudinary::upload($request->file('file')->getRealPath(), [
                'folder'    => $folder,
                'resource_type' => 'auto',
            ]);

            return response()->json([
                'success'   => true,
                'url'       => $result->getSecurePath(),
                'public_id' => $result->getPublicId(),
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
        $request->validate([
            'data'   => 'required|string',
            'folder' => 'nullable|string',
        ]);

        try {
            $folder = 'mikala/' . ($request->folder ?? 'general');
            $result = Cloudinary::upload($request->data, [
                'folder'        => $folder,
                'resource_type' => 'auto',
            ]);

            return response()->json([
                'success'   => true,
                'url'       => $result->getSecurePath(),
                'public_id' => $result->getPublicId(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Upload failed: ' . $e->getMessage()
            ], 500);
        }
    }
}
