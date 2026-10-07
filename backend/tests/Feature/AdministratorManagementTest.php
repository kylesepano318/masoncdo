<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\AdministratorSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdministratorManagementTest extends TestCase
{
    use RefreshDatabase;

    private function superadmin(): User
    {
        return User::factory()->create(['is_admin' => true, 'is_superadmin' => true]);
    }

    private function credentials(): array
    {
        return ['name' => 'New Administrator', 'username' => 'newAdmin', 'email' => 'new@example.test',
            'password' => 'New-admin-password!42', 'password_confirmation' => 'New-admin-password!42'];
    }

    public function test_management_requires_a_superadmin_for_every_action(): void
    {
        $target = User::factory()->create(['is_admin' => true]);
        $this->getJson('/api/admin/administrators')->assertUnauthorized();
        $this->postJson('/api/admin/administrators', $this->credentials())->assertUnauthorized();
        $this->deleteJson('/api/admin/administrators/'.$target->id)->assertUnauthorized();
        foreach ([false, true] as $isAdmin) {
            $this->actingAs(User::factory()->create(['is_admin' => $isAdmin]));
            $this->getJson('/api/admin/administrators')->assertForbidden();
            $this->postJson('/api/admin/administrators', $this->credentials())->assertForbidden();
            $this->deleteJson('/api/admin/administrators/'.$target->id)->assertForbidden();
        }
        $this->assertDatabaseHas('users', ['id' => $target->id]);
    }

    public function test_creation_hashes_password_and_cannot_grant_superadmin(): void
    {
        $this->actingAs($this->superadmin())->postJson('/api/admin/administrators', $this->credentials() + ['is_superadmin' => true])->assertCreated();
        $created = User::where('username', 'newAdmin')->firstOrFail();
        $this->assertTrue($created->is_admin);
        $this->assertFalse($created->is_superadmin);
        $this->assertTrue(Hash::check('New-admin-password!42', $created->password));
        $this->getJson('/api/admin/administrators')->assertOk()->assertJsonMissingPath('administrators.0.password');
        $this->postJson('/api/admin/administrators', $this->credentials())->assertJsonValidationErrors(['username', 'email']);
        $this->postJson('/api/admin/administrators', array_replace($this->credentials(), ['username' => 'anotherAdmin', 'email' => 'another@example.test', 'password' => 'weak', 'password_confirmation' => 'weak']))->assertJsonValidationErrors('password');
    }

    public function test_superadmins_are_protected_but_an_ordinary_admin_can_be_deleted(): void
    {
        $owner = $this->superadmin();
        $otherOwner = $this->superadmin();
        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($owner);
        $this->deleteJson('/api/admin/administrators/'.$owner->id)->assertJsonValidationErrors('administrator');
        $this->deleteJson('/api/admin/administrators/'.$otherOwner->id)->assertJsonValidationErrors('administrator');
        $this->deleteJson('/api/admin/administrators/'.$admin->id)->assertOk();
        $this->assertDatabaseMissing('users', ['id' => $admin->id]);
        $this->assertDatabaseHas('users', ['id' => $owner->id, 'is_superadmin' => true]);
        $this->assertDatabaseHas('users', ['id' => $otherOwner->id, 'is_superadmin' => true]);
        $nonAdmin = User::factory()->create();
        $this->deleteJson('/api/admin/administrators/'.$nonAdmin->id)->assertNotFound();
    }

    public function test_account_updates_cannot_elevate_an_admin_and_role_survives_username_changes(): void
    {
        config(['sanctum.stateful' => ['localhost']]);
        $this->withHeaders(['Origin' => 'http://localhost']);
        foreach ([false, true] as $isSuperadmin) {
            app('auth')->forgetGuards();
            session()->flush();
            $user = User::factory()->create(['is_admin' => true, 'is_superadmin' => $isSuperadmin, 'password' => 'Original-password!42']);
            $this->actingAs($user, 'web')->putJson('/api/admin/account', [
                'email' => $user->email, 'username' => 'changed'.$user->id,
                'current_password' => 'Original-password!42', 'is_superadmin' => ! $isSuperadmin,
            ])->assertOk();
            $this->assertSame($isSuperadmin, $user->fresh()->is_superadmin);
            $this->getJson('/api/admin/me')->assertJsonPath('user.is_superadmin', $isSuperadmin);
        }
    }

    public function test_created_admin_can_login_and_deletion_revokes_login_and_database_sessions(): void
    {
        config(['sanctum.stateful' => ['localhost'], 'session.driver' => 'database']);
        $this->withHeaders(['Origin' => 'http://localhost']);
        $owner = $this->superadmin();
        $this->actingAs($owner)->postJson('/api/admin/administrators', $this->credentials())->assertCreated();
        $admin = User::where('username', 'newAdmin')->firstOrFail();
        $this->postJson('/api/admin/logout')->assertOk();
        app('auth')->forgetGuards();
        $this->postJson('/api/admin/login', ['email' => 'newAdmin', 'password' => 'New-admin-password!42'])
            ->assertOk()->assertJsonPath('user.is_superadmin', false);
        $this->postJson('/api/admin/logout')->assertOk();
        app('auth')->forgetGuards();
        DB::table('sessions')->insert(['id' => 'deleted-admin-session', 'user_id' => $admin->id, 'payload' => base64_encode(serialize([])), 'last_activity' => time()]);
        $this->actingAs($owner, 'web')->deleteJson('/api/admin/administrators/'.$admin->id)->assertOk();
        $this->assertDatabaseMissing('sessions', ['user_id' => $admin->id]);
        $this->postJson('/api/admin/logout')->assertOk();
        app('auth')->forgetGuards();
        $this->postJson('/api/admin/login', ['email' => 'newAdmin', 'password' => 'New-admin-password!42'])->assertJsonValidationErrors('email');
    }

    public function test_new_seed_accounts_are_superadmins_and_rerun_preserves_credentials(): void
    {
        $keys = [];
        try {
            foreach ([1, 2] as $number) {
                foreach (['USERNAME' => 'seedOwner'.$number, 'PASSWORD' => 'Seed-owner-password!42'] as $suffix => $value) {
                    $key = "LODGE_ADMIN_{$number}_{$suffix}";
                    $keys[$key] = [getenv($key), $_ENV[$key] ?? null, $_SERVER[$key] ?? null];
                    putenv($key.'='.$value);
                    $_ENV[$key] = $_SERVER[$key] = $value;
                }
            }
            $this->seed(AdministratorSeeder::class);
            $this->assertSame(2, User::where('is_superadmin', true)->count());
            $first = User::where('username', 'seedOwner1')->firstOrFail();
            $first->update(['password' => 'Changed-owner-password!42']);
            $this->seed(AdministratorSeeder::class);
            $this->assertTrue(Hash::check('Changed-owner-password!42', $first->fresh()->password));
        } finally {
            foreach ($keys as $key => [$env, $array, $server]) {
                $env === false ? putenv($key) : putenv($key.'='.$env);
                if ($array === null) {
                    unset($_ENV[$key]);
                } else {
                    $_ENV[$key] = $array;
                }
                if ($server === null) {
                    unset($_SERVER[$key]);
                } else {
                    $_SERVER[$key] = $server;
                }
            }
        }
    }
}
