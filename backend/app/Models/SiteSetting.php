<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SiteSetting extends Model
{
    protected $fillable = ['key', 'value'];

    protected $casts = ['value' => 'array'];

    public static function allValues()
    {
        return static::all()->mapWithKeys(fn ($s) => [$s->key => $s->value])->all();
    }
}
