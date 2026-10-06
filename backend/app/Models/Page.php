<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Page extends Model
{
    protected $fillable = ['name', 'slug', 'meta_title', 'meta_description', 'seo', 'is_published', 'published_sections'];

    protected $casts = ['is_published' => 'boolean', 'seo' => 'array', 'published_sections' => 'array'];

    public function sections()
    {
        return $this->hasMany(PageSection::class)->orderBy('display_order');
    }
}
