<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ApplicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['first_name' => 'required|string|max:100', 'middle_name' => 'nullable|string|max:100', 'last_name' => 'required|string|max:100', 'suffix' => 'nullable|string|max:20', 'date_of_birth' => 'required|date|before:today', 'place_of_birth' => 'nullable|string|max:255', 'civil_status' => 'nullable|in:single,married,widowed,separated', 'occupation' => 'nullable|string|max:255', 'employer' => 'nullable|string|max:255', 'complete_address' => 'required|string|max:1000', 'city' => 'required|string|max:255', 'province' => 'required|string|max:255', 'postal_code' => 'nullable|string|max:20', 'mobile_number' => 'required|string|max:30', 'email' => 'required|email|max:255', 'reason_for_joining' => 'required|string|max:5000', 'how_did_you_hear' => 'nullable|in:member,social_media,website,event,other', 'has_referrer' => 'required|boolean', 'referring_member_name' => 'nullable|required_if:has_referrer,true|string|max:255', 'declaration' => 'required|accepted', 'consent' => 'required|accepted', 'website' => 'nullable|size:0'];
    }
}
