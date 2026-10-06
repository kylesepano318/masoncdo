<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Celebration extends Model
{
    protected $fillable = ['title', 'category', 'event_date', 'location', 'description', 'image', 'gallery', 'member_id', 'is_public'];

    protected $casts = ['gallery' => 'array', 'is_public' => 'boolean'];

    protected $appends = ['category_label'];

    public function type(): BelongsTo
    {
        return $this->belongsTo(CelebrationType::class, 'category', 'slug');
    }

    public function getCategoryLabelAttribute(): string
    {
        return $this->type?->name ?? str_replace('_', ' ', $this->category);
    }
}
