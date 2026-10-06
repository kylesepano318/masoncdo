<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ApplicationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return $this->resource->only(['id', 'reference_number', 'first_name', 'middle_name', 'last_name', 'suffix', 'date_of_birth', 'place_of_birth', 'civil_status', 'occupation', 'employer', 'complete_address', 'city', 'province', 'postal_code', 'mobile_number', 'email', 'reason_for_joining', 'how_did_you_hear', 'has_referrer', 'referring_member_name', 'certification_accepted_at', 'privacy_consent_accepted_at', 'status', 'is_read_by_admin', 'admin_notes', 'submitted_at', 'reviewed_at', 'converted_member_id', 'converted_at', 'notification_email_sent_at', 'notification_email_failed_at', 'notification_email_error']);
    }
}
