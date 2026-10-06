<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Media extends Model
{
    protected $table = 'media';

    protected $fillable = ['filename', 'original_name', 'disk', 'path', 'mime_type', 'size', 'alt_text', 'caption', 'cloudinary_public_id', 'secure_url', 'resource_type', 'width', 'height', 'bytes'];
}
