<?php

namespace App\Services;

use App\Models\Member;
use App\Models\MembershipPosition;
use App\Support\Audit;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MemberService
{
    public function save(array $data, ?Member $member = null, bool $replace = false): Member
    {
        return DB::transaction(function () use ($data, $member, $replace) {
            $position = MembershipPosition::whereKey($data['membership_position_id'])->lockForUpdate()->firstOrFail();
            $data['active_officer_position'] = null;
            if ($position->is_officer && $data['status'] === 'active') {
                $previous = Member::where('active_officer_position', $position->id)->when($member, fn ($q) => $q->where('id', '!=', $member->id))->lockForUpdate()->first();
                if ($previous && ! $replace) {
                    throw ValidationException::withMessages(['replace_officer' => "{$previous->first_name} {$previous->last_name} is currently {$position->name}. Replacing them will move their status to Past Officer. Confirm replacement to continue."]);
                }
                if ($previous) {
                    $previous->update(['status' => 'past_officer', 'active_officer_position' => null]);
                    Audit::log('Officer Changed', ['previous_member_id' => $previous->id, 'position_id' => $position->id]);
                }
                $data['active_officer_position'] = $position->id;
            }
            $created = ! $member;
            $member ??= new Member;
            $member->fill($data)->save();
            Audit::log($created ? 'Member Created' : 'Member Updated', ['member_id' => $member->id]);

            return $member;
        });
    }
}
