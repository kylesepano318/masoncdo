<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MembershipPosition extends Model
{
    protected $fillable = ['name', 'slug', 'rank', 'is_officer'];

    protected $casts = ['is_officer' => 'boolean'];
}
