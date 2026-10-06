<?php

namespace App\Http\Controllers;

use App\Http\Requests\MediaRequest;
use App\Models\Media;
use App\Services\CloudinaryService;
use App\Services\MediaReferences;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class MediaController extends Controller
{
    public function index(Request $r)
    {
        return response()->json(['media' => Media::when($r->query('search'), fn ($q, $s) => $q->where('original_name', 'ilike', "%$s%"))->latest()->paginate(24)->withQueryString()]);
    }

    public function store(MediaRequest $r, CloudinaryService $cloud)
    {
        $data = $r->validated();
        $media = $cloud->upload($r->file('file'), $data['alt_text'], $data['caption'] ?? null);

        return response()->json(['message' => 'Image uploaded.', 'media' => $media], 201);
    }

    public function update(Request $r, Media $media)
    {
        $media->update($r->validate(['alt_text' => 'required|string|max:255', 'caption' => 'nullable|string|max:2000']));

        return response()->json(['message' => 'Image details saved.']);
    }

    public function destroy(Media $media, CloudinaryService $cloud, MediaReferences $references)
    {
        if ($references->inUse($media)) {
            throw ValidationException::withMessages(['media' => 'This image is in use. Remove its draft and published references before deleting it.']);
        }$cloud->destroy($media);
        $media->delete();

        return response()->json(['message' => 'Image deleted.']);
    }
}
