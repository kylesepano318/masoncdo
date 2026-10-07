<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MediaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->is_admin;
    }

    public function rules(): array
    {
        $types = config('lodge.media_disk') === 'cloudinary' ? 'jpg,jpeg,png,webp' : 'jpg,jpeg,png,webp,pdf';

        return ['file' => 'required|file|mimes:'.$types.'|max:8192', 'alt_text' => 'required|string|max:255', 'caption' => 'nullable|string|max:2000'];
    }
}
