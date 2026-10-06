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
        return ['file' => 'required|file|image|mimes:jpg,jpeg,png,webp|max:8192', 'alt_text' => 'required|string|max:255', 'caption' => 'nullable|string|max:2000'];
    }
}
