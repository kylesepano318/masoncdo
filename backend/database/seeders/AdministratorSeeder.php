<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AdministratorSeeder extends Seeder
{
    public function run(): void
    {
        $accounts = [];
        foreach ([1, 2] as $number) {
            $username = (string) env("LODGE_ADMIN_{$number}_USERNAME", '');
            $password = (string) env("LODGE_ADMIN_{$number}_PASSWORD", '');
            if ($username === '' && $password === '') {
                continue;
            }
            if (! preg_match('/^[a-zA-Z0-9._-]{3,100}$/', $username) || strlen($password) < 10) {
                throw new \RuntimeException('Provide a valid administrator username and an initial password of at least 10 characters.');
            }
            $email = (string) env("LODGE_ADMIN_{$number}_EMAIL", $username.'@gfmasoniclodge40.org');
            if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new \RuntimeException('Provide a valid administrator email.');
            }
            $accounts[] = compact('username', 'password', 'email');
        }
        DB::transaction(function () use ($accounts) {
            foreach ($accounts as $account) {
                // Re-running the seeder never resets an existing account's credentials.
                if (User::where('username', $account['username'])->exists()) {
                    continue;
                }
                if (User::where('email', $account['email'])->exists()) {
                    throw new \RuntimeException('An administrator seed email is already in use; existing accounts were preserved.');
                }
                $user = new User(['name' => $account['username'], 'username' => $account['username'], 'email' => $account['email'], 'password' => $account['password']]);
                $user->is_admin = true;
                $user->save();
            }
        });
    }
}
