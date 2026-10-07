<?php

namespace App\Services;

use App\Models\Media;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class MediaStorage
{
    public function upload(UploadedFile $file, string $alt = '', ?string $caption = null): Media
    {
        if (config('lodge.media_disk') === 'cloudinary') {
            return app(CloudinaryService::class)->upload($file, $alt, $caption);
        }
        $disk = config('lodge.media_disk');
        $this->configured($disk);
        $isPdf = $file->getMimeType() === 'application/pdf';
        $key = ($isPdf ? 'documents/' : 'images/').Str::uuid().'.'.$file->guessExtension();
        $dimensions = $isPdf ? null : getimagesize($file->getRealPath());
        $stream = fopen($file->getRealPath(), 'rb');
        try {
            if (! Storage::disk($disk)->put($key, $stream, ['ContentType' => $file->getMimeType()])) {
                throw new \RuntimeException('Storage rejected the upload.');
            }
        } catch (\Throwable $e) {
            Log::warning('Media upload failed', ['exception_type' => get_class($e)]);
            throw ValidationException::withMessages(['file' => 'File upload failed. Check server storage configuration and try again.']);
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }
        try {
            return Media::create([
                'filename' => $key, 'original_name' => $file->getClientOriginalName(),
                'disk' => $disk, 'path' => Storage::disk($disk)->url($key),
                'secure_url' => Storage::disk($disk)->url($key), 'resource_type' => $isPdf ? 'raw' : 'image',
                'width' => $dimensions[0] ?? null, 'height' => $dimensions[1] ?? null,
                'mime_type' => $file->getMimeType(), 'size' => $file->getSize(), 'bytes' => $file->getSize(),
                'alt_text' => $alt, 'caption' => $caption,
            ]);
        } catch (\Throwable $e) {
            try {
                Storage::disk($disk)->delete($key);
            } catch (\Throwable) {
                Log::warning('Media orphan cleanup failed', ['key' => $key]);
            }
            throw $e;
        }
    }

    public function destroy(Media $media): void
    {
        if ($media->disk === 'cloudinary') {
            app(CloudinaryService::class)->destroy($media);

            return;
        }
        if (! in_array($media->disk, ['public', 'r2'], true)) {
            throw ValidationException::withMessages(['media' => 'This legacy file must be managed in its original storage.']);
        }
        $this->configured($media->disk);
        if (! preg_match('~^(?:images|documents)/[a-zA-Z0-9._-]+$~D', $media->filename)) {
            throw ValidationException::withMessages(['media' => 'Invalid storage key. The media record has been preserved.']);
        }
        try {
            if (! Storage::disk($media->disk)->delete($media->filename)) {
                throw new \RuntimeException('Delete failed.');
            }
        } catch (\Throwable) {
            throw ValidationException::withMessages(['media' => 'File deletion failed. The media record has been preserved.']);
        }
    }

    private function configured(string $disk): void
    {
        if ($disk === 'public') {
            if (! config('filesystems.disks.public.root') || ! config('filesystems.disks.public.url')) {
                throw ValidationException::withMessages(['file' => 'Public storage is not configured.']);
            }

            return;
        }
        if ($disk !== 'r2') {
            throw ValidationException::withMessages(['file' => 'Select public or R2 media storage on the server.']);
        }
        foreach (['key', 'secret', 'bucket', 'endpoint', 'url'] as $field) {
            if (! config('filesystems.disks.r2.'.$field)) {
                throw ValidationException::withMessages(['file' => 'R2 storage is not configured. Set the server R2 environment variables.']);
            }
        }
        if (! str_starts_with(config('filesystems.disks.r2.url'), 'https://')) {
            throw ValidationException::withMessages(['file' => 'R2_PUBLIC_URL must be an HTTPS public media URL.']);
        }
    }
}
