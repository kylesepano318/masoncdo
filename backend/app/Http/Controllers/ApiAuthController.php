<?php

namespace App\Http\Controllers;

use App\Models\LodgeApplication;
use App\Models\Page;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class ApiAuthController extends Controller
{
    public function login(Request $r)
    {
        abort_unless($r->hasSession(), 419, 'Session unavailable. Check the configured frontend origin.');
        $data = $r->validate(['email' => 'required|email', 'password' => 'required|string', 'remember' => 'sometimes|boolean']);
        if (! Auth::guard('web')->attempt(['email' => $data['email'], 'password' => $data['password'], 'is_admin' => true], $r->boolean('remember'))) {
            throw ValidationException::withMessages(['email' => 'The supplied credentials are incorrect.']);
        }$r->session()->regenerate();

        return $this->me($r);
    }

    public function me(Request $r)
    {
        return response()->json(['user' => $r->user()->only('id', 'name', 'email'), 'pages' => Page::all(['id', 'slug', 'name']), 'unread' => LodgeApplication::where('is_read_by_admin', false)->count()]);
    }

    public function logout(Request $r)
    {
        Auth::guard('web')->logout();
        $r->session()->invalidate();
        $r->session()->regenerateToken();

        return response()->json(['message' => 'Logged out.']);
    }

    public function password(Request $r)
    {
        $data = $r->validate(['current_password' => 'required|current_password:web', 'password' => ['required', 'confirmed', Password::min(12)->mixedCase()->numbers()->symbols()]]);
        $r->user()->update(['password' => $data['password']]);
        $r->session()->regenerate();

        return response()->json(['message' => 'Password updated.']);
    }
}
