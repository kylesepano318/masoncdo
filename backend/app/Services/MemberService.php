<?php

namespace App\Services;

use App\Models\Member;
use App\Models\MembershipPosition;
use App\Support\Audit;
use Illuminate\Support\Facades\DB;

class MemberService
{
    public function save(array $data, ?Member $member = null): Member
    {
        return DB::transaction(function () use ($data, $member) {
            $position = MembershipPosition::whereKey($data['membership_position_id'])->lockForUpdate()->firstOrFail();
            $data['active_officer_position'] = null;
            if ($position->is_officer && $data['status'] === 'active') {
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
