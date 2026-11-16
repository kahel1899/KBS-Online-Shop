<?php

namespace App\Http\Controllers;

use App\Models\UploadedImage;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ImageController extends Controller
{
    private const ALLOWED = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];

    // Admin only: save a photo and give back the address to use for it
    public function store(Request $request)
    {
        // max is in kilobytes. The browser shrinks photos before sending.
        $request->validate([
            'image' => 'required|file|max:2048',
        ]);

        $binary = file_get_contents($request->file('image')->getRealPath());

        // Look at the bytes themselves, not the file name, to be sure it is a picture
        $info = @getimagesizefromstring($binary);

        if (! $info || ! in_array($info['mime'], self::ALLOWED, true)) {
            throw ValidationException::withMessages([
                'image' => 'Please upload a JPG, PNG or WebP photo.',
            ]);
        }

        $image = UploadedImage::create([
            'mime' => $info['mime'],
            'size' => strlen($binary),
            'width' => $info[0],
            'height' => $info[1],
            'data' => base64_encode($binary),
        ]);

        return response()->json([
            'id' => $image->id,
            'url' => '/api/images/' . $image->id,
        ], 201);
    }

    // Public: the shop shows the photo from here
    public function show($id)
    {
        $image = UploadedImage::findOrFail($id);
        $binary = base64_decode($image->data, true);

        abort_if($binary === false, 404);

        return response($binary, 200, [
            'Content-Type' => $image->mime,
            'Content-Length' => strlen($binary),
            // a photo never changes once uploaded, so browsers can keep it
            'Cache-Control' => 'public, max-age=31536000, immutable',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
