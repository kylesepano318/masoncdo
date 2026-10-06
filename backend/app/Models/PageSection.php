<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PageSection extends Model
{
    protected $fillable = ['page_id', 'section_type', 'title', 'subtitle', 'body', 'image', 'mobile_image', 'settings', 'display_order', 'is_visible'];

    protected $casts = ['settings' => 'array', 'is_visible' => 'boolean'];
}
