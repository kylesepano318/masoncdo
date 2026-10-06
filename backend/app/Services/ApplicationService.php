<?php

namespace App\Services;

use App\Models\LodgeApplication;
use App\Models\Member;
use App\Models\MembershipPosition;
use App\Support\Audit;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ApplicationService
{
    public function submit(array $data): LodgeApplication
    {
        $record = DB::transaction(function () use ($data) {
            $year = (int) now()->format('Y');
            DB::table('application_sequences')->insertOrIgnore(['year' => $year, 'value' => 0]);
            $sequence = DB::table('application_sequences')->where('year', $year)->lockForUpdate()->first();
            $value = $sequence->value + 1;
            DB::table('application_sequences')->where('year', $year)->update(['value' => $value]);
            $record = LodgeApplication::create($data + ['reference_number' => sprintf('APP-%d-%06d', $year, $value), 'consented_at' => now(), 'submitted_at' => now(), 'certification_accepted_at' => now(), 'privacy_consent_accepted_at' => now(), 'is_read_by_admin' => false]);
            Audit::log('Application Submitted', ['application_id' => $record->id]);

            return $record;
        });
        app(ApplicationNotificationService::class)->deliver($record);

        return $record;
    }

    public function convert(LodgeApplication $application): Member
    {
        return DB::transaction(function () use ($application) {
            $application = LodgeApplication::whereKey($application->id)->lockForUpdate()->firstOrFail();
            if ($application->converted_at) {
                throw ValidationException::withMessages(['conversion' => 'This application has already been converted.']);
            } $member = Member::create(collect($application->toArray())->only(['first_name', 'middle_name', 'last_name', 'suffix'])->all() + ['membership_position_id' => MembershipPosition::where('slug', 'member')->firstOrFail()->id, 'status' => 'active', 'is_public' => false, 'member_since' => today()]);
            $application->update(['status' => 'approved', 'converted_member_id' => $member->id, 'converted_at' => now(), 'reviewed_at' => now()]);
            Audit::log('Application Converted to Member', ['application_id' => $application->id, 'member_id' => $member->id]);

            return $member;
        });
    }
}
