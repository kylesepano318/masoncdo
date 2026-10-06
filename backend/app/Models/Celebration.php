<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Celebration extends Model
{
    protected $fillable = ['title', 'category', 'event_date', 'location', 'description', 'image', 'gallery', 'member_id', 'is_public'];

    protected $casts = ['gallery' => 'array', 'is_public' => 'boolean'];
}
