<?php

namespace App\Http\Controllers;

use App\Models\LodgeApplication;
use App\Models\Page;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class ApiAuthController extends Controller
{
    public function login(Request $r)
    {
        abort_unless($r->hasSession(), 419, 'Session unavailable. Check the configured frontend origin.');
        $data = $r->validate(['email' => 'required|string|max:255', 'password' => 'required|string', 'remember' => 'sometimes|boolean']);
        $identifier = trim($data['email']);
        $field = filter_var($identifier, FILTER_VALIDATE_EMAIL) ? 'email' : 'username';
        if (! Auth::guard('web')->attempt([$field => $identifier, 'password' => $data['password'], 'is_admin' => true], $r->boolean('remember'))) {
            throw ValidationException::withMessages(['email' => 'The supplied credentials are incorrect.']);
        }
        $r->session()->regenerate();

        return $this->me($r);
    }

    public function me(Request $r)
    {
        return response()->json(['user' => $r->user()->only('id', 'name', 'email', 'username'), 'pages' => Page::all(['id', 'slug', 'name']), 'unread' => LodgeApplication::where('is_read_by_admin', false)->count()]);
    }

    public function logout(Request $r)
    {
        Auth::guard('web')->logout();
        $r->session()->invalidate();
        $r->session()->regenerateToken();

        return response()->json(['message' => 'Logged out.']);
    }

    public function account(Request $r)
    {
        $data = $r->validate([
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($r->user()->id)],
            'username' => ['sometimes', 'nullable', 'string', 'min:3', 'max:100', 'regex:/^[a-zA-Z0-9._-]+$/', Rule::unique('users', 'username')->ignore($r->user()->id)],
            'current_password' => 'required|current_password:web',
            'password' => ['nullable', 'confirmed', Password::min(12)->mixedCase()->numbers()->symbols()],
            'password_confirmation' => 'nullable|required_with:password|string',
        ]);
        $user = $r->user();
        DB::transaction(function () use ($r, $user, $data) {
            $changes = ['email' => $data['email']];
            if (array_key_exists('username', $data)) {
                $changes['username'] = $data['username'];
            }
            if (! empty($data['password'])) {
                $changes['password'] = $data['password'];
            }
            if ($user->email !== $data['email']) {
                $user->email_verified_at = null;
            }
            $user->remember_token = Str::random(60);
            $user->fill($changes)->save();
            if (config('session.driver') === 'database') {
                DB::connection(config('session.connection'))->table(config('session.table', 'sessions'))
                    ->where('user_id', $user->id)->where('id', '!=', $r->session()->getId())->delete();
            }
        });
        $r->session()->regenerate();

        return response()->json(['message' => 'Login credentials updated.', 'user' => $user->only('id', 'name', 'email', 'username')]);
    }

    public function password(Request $r)
    {
        $r->validate(['password' => 'required']);
        $r->merge(['email' => $r->user()->email]);

        return $this->account($r);
    }
}
