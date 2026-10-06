<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Affiliation extends Model
{
    protected $fillable = ['name', 'logo', 'subtitle', 'description', 'website_url', 'display_order', 'is_visible'];

    protected $casts = ['is_visible' => 'boolean'];
}
