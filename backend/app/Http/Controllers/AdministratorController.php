<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class AdministratorController extends Controller
{
    private function editable(Request $request, User $administrator): void
    {
        abort_unless($request->user()->is_superadmin, 403);
        abort_unless($administrator->is_admin, 404);
        if ($administrator->is_superadmin) {
            throw ValidationException::withMessages(['administrator' => 'Superadministrators manage their own credentials under Login credentials.']);
        }
    }

    private function revokeSessions(User $administrator): void
    {
        if (config('session.driver') === 'database') {
            DB::connection(config('session.connection'))->table(config('session.table', 'sessions'))->where('user_id', $administrator->id)->delete();
        }
    }

    public function update(Request $request, User $administrator)
    {
        $this->editable($request, $administrator);
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'username' => ['required', 'string', 'min:3', 'max:100', 'regex:/^[a-zA-Z0-9._-]+$/', Rule::unique('users', 'username')->ignore($administrator->id)],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($administrator->id)],
        ]);
        DB::transaction(function () use ($administrator, $data) {
            $administrator = User::whereKey($administrator->id)->lockForUpdate()->firstOrFail();
            $credentialsChanged = $administrator->username !== $data['username'] || $administrator->email !== $data['email'];
            if ($administrator->email !== $data['email']) {
                $administrator->email_verified_at = null;
            }
            $administrator->fill($data);
            if ($credentialsChanged) {
                $administrator->remember_token = Str::random(60);
                $this->revokeSessions($administrator);
            }
            $administrator->save();
            Audit::log('Administrator Updated', ['administrator_id' => $administrator->id]);
        });

        return response()->json(['message' => 'Administrator updated.']);
    }

    public function resetPassword(Request $request, User $administrator)
    {
        $this->editable($request, $administrator);
        DB::transaction(function () use ($administrator) {
            $administrator = User::whereKey($administrator->id)->lockForUpdate()->firstOrFail();
            $administrator->password = 'Gfmasoniclodge40!';
            $administrator->remember_token = Str::random(60);
            $administrator->save();
            $this->revokeSessions($administrator);
            Audit::log('Administrator Password Reset', ['administrator_id' => $administrator->id]);
        });

        return response()->json(['message' => 'Password reset to the default password. Ask the administrator to change it after signing in.']);
    }

    public function index(Request $request)
    {
        abort_unless($request->user()->is_superadmin, 403);

        return response()->json(['administrators' => User::where('is_admin', true)
            ->orderByDesc('is_superadmin')->orderBy('name')
            ->get(['id', 'name', 'username', 'email', 'is_superadmin', 'created_at'])]);
    }

    public function store(Request $request)
    {
        abort_unless($request->user()->is_superadmin, 403);
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'username' => ['required', 'string', 'min:3', 'max:100', 'regex:/^[a-zA-Z0-9._-]+$/', 'unique:users,username'],
            'email' => 'required|email|max:255|unique:users,email',
            'password' => ['required', 'confirmed', Password::min(12)->mixedCase()->numbers()->symbols()],
        ]);
        DB::transaction(function () use ($data) {
            $user = new User($data);
            $user->is_admin = true;
            // Submitted roles are ignored; this page only creates ordinary admins.
            $user->is_superadmin = false;
            $user->save();
            Audit::log('Administrator Created', ['administrator_id' => $user->id]);
        });

        return response()->json(['message' => 'Administrator created. Share their login credentials privately.'], 201);
    }

    public function destroy(Request $request, User $administrator)
    {
        abort_unless($request->user()->is_superadmin, 403);
        DB::transaction(function () use ($administrator) {
            $administrator = User::whereKey($administrator->id)->lockForUpdate()->firstOrFail();
            abort_unless($administrator->is_admin, 404);
            if ($administrator->is_superadmin) {
                throw ValidationException::withMessages(['administrator' => 'The two superadministrator accounts cannot be deleted.']);
            }
            Audit::log('Administrator Deleted', ['administrator_id' => $administrator->id]);
            if (config('session.driver') === 'database') {
                DB::connection(config('session.connection'))->table(config('session.table', 'sessions'))
                    ->where('user_id', $administrator->id)->delete();
            }
            $administrator->delete();
        });

        return response()->json(['message' => 'Administrator deleted. Their login access has been removed.']);
    }
}
