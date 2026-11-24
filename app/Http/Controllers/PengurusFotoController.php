<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PengurusFotoController extends Controller
{
    public function simpan(Request $request): JsonResponse
    {
        abort_unless((bool) $request->user()?->can('konten.manage'), 403);

        $validated = $request->validate([
            'foto' => ['required', 'file', 'image', 'mimes:png,webp,jpeg,jpg', 'max:4096'],
        ]);

        $file = $validated['foto'];
        $info = @getimagesize($file->getRealPath());
        if ($info === false || ! in_array($info[2], [IMAGETYPE_PNG, IMAGETYPE_JPEG, IMAGETYPE_WEBP], true)) {
            return response()->json(['message' => 'File gambar tidak valid.'], 422);
        }

        $ext = match ($info[2]) {
            IMAGETYPE_PNG => 'png',
            IMAGETYPE_WEBP => 'webp',
            default => 'jpg',
        };

        $namaFile = 'pengurus-' . strtolower((string) Str::ulid()) . '.' . $ext;
        $path = Storage::disk('public')->putFileAs('pengurus', $file, $namaFile);

        return response()->json([
            'path' => $path,
            'url' => asset('storage/' . $path),
        ]);
    }
}
