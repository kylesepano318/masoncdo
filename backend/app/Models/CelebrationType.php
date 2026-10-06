<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CelebrationType extends Model
{
    protected $fillable = ['name', 'slug'];

    public function celebrations(): HasMany
    {
        return $this->hasMany(Celebration::class, 'category', 'slug');
    }
}
