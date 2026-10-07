<?php

use Illuminate\Support\Facades\Route;

// Reserved server paths must never return the SPA document.
Route::get('/{path?}', function () {
    abort_unless(is_file(public_path('index.html')), 404);

    return response()->file(public_path('index.html'), ['Cache-Control' => 'no-cache']);
})->where('path', '(?!(?:api|sanctum|up|storage|assets|images|register)(?:/|$))[^.]*');
