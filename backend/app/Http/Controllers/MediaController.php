<?php

namespace App\Http\Controllers;

use App\Http\Requests\MediaRequest;
use App\Models\Media;
use App\Services\MediaReferences;
use App\Services\MediaStorage;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class MediaController extends Controller
{
    public function index(Request $r)
    {
        $data = $r->validate(['search' => 'nullable|string|max:255', 'type' => 'nullable|in:image']);

        return response()->json(['media' => Media::select(['id', 'path', 'original_name', 'alt_text', 'caption', 'mime_type', 'resource_type'])->when($data['type'] ?? null, fn ($q) => $q->where('resource_type', 'image'))->when($data['search'] ?? null, fn ($q, $s) => $q->where(fn ($q) => $q->whereLike('original_name', "%$s%")->orWhereLike('alt_text', "%$s%")))->latest('id')->paginate(24)->withQueryString()]);
    }

    public function store(MediaRequest $r, MediaStorage $storage)
    {
        $data = $r->validated();
        $media = $storage->upload($r->file('file'), $data['alt_text'], $data['caption'] ?? null);

        return response()->json(['message' => 'File uploaded.', 'media' => $media], 201);
    }

    public function update(Request $r, Media $media)
    {
        $media->update($r->validate(['alt_text' => 'required|string|max:255', 'caption' => 'nullable|string|max:2000']));

        return response()->json(['message' => 'File details saved.']);
    }

    public function destroy(Media $media, MediaStorage $storage, MediaReferences $references)
    {
        if ($references->inUse($media)) {
            throw ValidationException::withMessages(['media' => 'This file is in use. Remove its draft and published references before deleting it.']);
        }$storage->destroy($media);
        $media->delete();

        return response()->json(['message' => 'File deleted.']);
    }
}
