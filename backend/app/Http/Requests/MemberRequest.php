<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->is_admin;
    }

    public function rules(): array
    {
        return ['first_name' => 'required|string|max:100', 'middle_name' => 'nullable|string|max:100', 'last_name' => 'required|string|max:100', 'suffix' => 'nullable|string|max:20', 'member_number' => 'nullable|string|max:50', 'membership_position_id' => 'required|exists:membership_positions,id', 'profile_photo' => 'nullable|string|max:255', 'photo' => 'nullable|file|image|mimes:jpg,jpeg,png,webp|max:8192', 'member_since' => 'nullable|date', 'biography' => 'nullable|string|max:10000', 'status' => 'required|in:active,inactive,past_officer', 'display_order' => 'integer|min:0', 'is_public' => 'required|boolean', 'replace_officer' => 'sometimes|boolean'];
    }
}
