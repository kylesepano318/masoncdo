<?php

namespace App\Services;

use App\Models\Media;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CloudinaryService
{
    private function config(): array
    {
        $config = config('lodge.cloudinary');
        if (! ($config['cloud_name'] && $config['api_key'] && $config['api_secret'])) {
            throw ValidationException::withMessages(['file' => 'Cloudinary storage is not configured. Configure the server environment before uploading.']);
        }

        return $config;
    }

    private function signature(array $params, string $secret): string
    {
        ksort($params);

        return sha1(urldecode(http_build_query($params)).$secret);
    }

    public function upload(UploadedFile $file, string $alt = '', ?string $caption = null): Media
    {
        $config = $this->config();
        $params = ['folder' => 'golden-friendship-lodge', 'public_id' => (string) Str::uuid(), 'timestamp' => time()];
        try {
            $result = Http::timeout(25)->connectTimeout(8)->attach('file', fopen($file->getRealPath(), 'r'), $file->getClientOriginalName())->post('https://api.cloudinary.com/v1_1/'.$config['cloud_name'].'/image/upload', $params + ['api_key' => $config['api_key'], 'signature' => $this->signature($params, $config['api_secret'])])->throw()->json();
            if (! is_array($result) || ! isset($result['public_id'], $result['secure_url'], $result['width'], $result['height'], $result['bytes']) || ! str_starts_with($result['secure_url'], 'https://')) {
                throw new \RuntimeException('Incomplete Cloudinary response.');
            }
        } catch (\Throwable $e) {
            Log::warning('Cloudinary upload failed', ['exception_type' => get_class($e)]);
            throw ValidationException::withMessages(['file' => 'Image upload failed. The existing image has been preserved. Try again.']);
        }

        return Media::create(['filename' => basename($result['public_id']), 'original_name' => $file->getClientOriginalName(), 'disk' => 'cloudinary', 'path' => $result['secure_url'], 'secure_url' => $result['secure_url'], 'cloudinary_public_id' => $result['public_id'], 'resource_type' => $result['resource_type'] ?? 'image', 'width' => $result['width'], 'height' => $result['height'], 'bytes' => $result['bytes'], 'size' => $result['bytes'], 'mime_type' => $file->getMimeType(), 'alt_text' => $alt, 'caption' => $caption]);
    }

    public function destroy(Media $media): void
    {
        if (! $media->cloudinary_public_id) {
            return;
        }
        $config = $this->config();
        $params = ['public_id' => $media->cloudinary_public_id, 'timestamp' => time()];
        try {
            $result = Http::timeout(15)->connectTimeout(8)->asForm()->post('https://api.cloudinary.com/v1_1/'.$config['cloud_name'].'/image/destroy', $params + ['api_key' => $config['api_key'], 'signature' => $this->signature($params, $config['api_secret'])])->throw()->json('result');
            if (! in_array($result, ['ok', 'not found'], true)) {
                throw new \RuntimeException('Cloudinary deletion was not confirmed.');
            }
        } catch (\Throwable $e) {
            throw ValidationException::withMessages(['media' => 'Cloudinary deletion failed. The media record was preserved. Try again.']);
        }
    }
}
