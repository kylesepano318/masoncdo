<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Member extends Model
{
    protected $fillable = ['member_number', 'first_name', 'middle_name', 'last_name', 'suffix', 'membership_position_id', 'active_officer_position', 'profile_photo', 'member_since', 'biography', 'status', 'display_order', 'is_public'];

    protected $casts = ['is_public' => 'boolean'];

    public function position()
    {
        return $this->belongsTo(MembershipPosition::class, 'membership_position_id');
    }
}
