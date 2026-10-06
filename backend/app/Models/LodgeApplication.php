<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LodgeApplication extends Model
{
    protected $table = 'applications';

    protected $fillable = ['reference_number', 'first_name', 'middle_name', 'last_name', 'suffix', 'date_of_birth', 'place_of_birth', 'civil_status', 'occupation', 'employer', 'postal_code', 'how_did_you_hear', 'referring_member_name', 'complete_address', 'city', 'province', 'mobile_number', 'email', 'reason_for_joining', 'has_referrer', 'declaration', 'consent', 'consented_at', 'status', 'admin_notes', 'converted_member_id', 'converted_at', 'is_read_by_admin', 'submitted_at', 'reviewed_at', 'certification_accepted_at', 'privacy_consent_accepted_at', 'notification_email_sent_at', 'notification_email_failed_at', 'notification_email_error'];

    protected $casts = ['is_read_by_admin' => 'boolean', 'has_referrer' => 'boolean'];
}
