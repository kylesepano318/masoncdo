<?php

namespace Tests\Feature;

use App\Models\Page;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AccountCredentialsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['sanctum.stateful' => ['localhost']]);
        $this->withHeaders(['Origin' => 'http://localhost']);
    }

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true, 'password' => 'Original-password!42']);
    }

    public function test_only_an_authenticated_admin_can_change_credentials(): void
    {
        $this->putJson('/api/admin/account', [])->assertUnauthorized();
        $this->actingAs(User::factory()->create())->putJson('/api/admin/account', [])->assertForbidden();
    }

    public function test_email_only_change_preserves_password_and_requires_current_password(): void
    {
        $admin = $this->admin();
        $hash = $admin->password;
        $this->actingAs($admin)->putJson('/api/admin/account', ['email' => 'updated@example.test', 'current_password' => 'wrong'])
            ->assertJsonValidationErrors('current_password');
        $this->assertEquals($admin->email, $admin->fresh()->email);
        $this->putJson('/api/admin/account', ['email' => 'updated@example.test', 'current_password' => 'Original-password!42', 'password' => '', 'password_confirmation' => ''])
            ->assertOk()->assertJsonPath('user.email', 'updated@example.test')->assertJsonMissingPath('user.password');
        $this->assertSame($hash, $admin->fresh()->password);
        $this->assertNull($admin->fresh()->email_verified_at);
        $this->getJson('/api/admin/me')->assertJsonPath('user.email', 'updated@example.test');
    }

    public function test_invalid_email_and_password_do_not_partially_update_account(): void
    {
        $admin = $this->admin();
        $other = User::factory()->create();
        $this->actingAs($admin)->putJson('/api/admin/account', ['email' => $other->email, 'current_password' => 'Original-password!42'])
            ->assertJsonValidationErrors('email');
        $this->putJson('/api/admin/account', ['email' => 'invalid', 'current_password' => 'Original-password!42'])->assertJsonValidationErrors('email');
        $this->putJson('/api/admin/account', ['email' => 'updated@example.test', 'current_password' => 'Original-password!42', 'password' => 'short', 'password_confirmation' => 'short'])
            ->assertJsonValidationErrors('password');
        $this->putJson('/api/admin/account', ['email' => 'updated@example.test', 'current_password' => 'Original-password!42', 'password' => 'Updated-password!42', 'password_confirmation' => 'different'])
            ->assertJsonValidationErrors('password');
        $this->assertSame($admin->email, $admin->fresh()->email);
    }

    public function test_changed_credentials_work_and_old_credentials_stop_working(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->putJson('/api/admin/account', ['email' => 'updated@example.test', 'current_password' => 'Original-password!42', 'password' => 'Updated-password!42', 'password_confirmation' => 'Updated-password!42'])
            ->assertOk();
        $this->assertTrue(Hash::check('Updated-password!42', $admin->fresh()->password));
        $this->postJson('/api/admin/logout')->assertOk();
        $this->postJson('/api/admin/login', ['email' => $admin->email, 'password' => 'Original-password!42'])->assertJsonValidationErrors('email');
        $this->postJson('/api/admin/login', ['email' => 'updated@example.test', 'password' => 'Original-password!42'])->assertJsonValidationErrors('email');
        $this->postJson('/api/admin/login', ['email' => 'updated@example.test', 'password' => 'Updated-password!42'])->assertOk();
    }

    public function test_credential_change_revokes_other_database_sessions_and_remember_token(): void
    {
        config(['session.driver' => 'database']);
        $admin = $this->admin();
        $other = User::factory()->create();
        $token = $admin->remember_token;
        foreach (['other-admin-session' => $admin->id, 'unrelated-user-session' => $other->id] as $id => $userId) {
            DB::table('sessions')->insert(['id' => $id, 'user_id' => $userId, 'payload' => base64_encode(serialize([])), 'last_activity' => time()]);
        }
        $this->actingAs($admin)->putJson('/api/admin/account', ['email' => $admin->email, 'current_password' => 'Original-password!42'])->assertOk();
        $this->assertDatabaseMissing('sessions', ['id' => 'other-admin-session']);
        $this->assertDatabaseHas('sessions', ['id' => 'unrelated-user-session']);
        $this->assertNotSame($token, $admin->fresh()->remember_token);
        $this->getJson('/api/admin/me')->assertOk();
    }

    public function test_lodge_banner_updates_draft_and_published_without_replacing_other_content(): void
    {
        $this->seed();
        $page = Page::where('slug', 'home')->firstOrFail();
        $hero = $page->sections()->first();
        $this->assertSame('Golden Friendship', $hero->title);
        $hero->update(['title' => 'S:.I:.G:.L:.O:.', 'subtitle' => 'FEDERATION', 'body' => 'Old tagline']);
        $page->update(['published_sections' => $page->sections()->get()->toArray()]);
        $remaining = array_slice($page->published_sections, 1);
        $migration = require database_path('migrations/2026_10_07_000002_update_lodge_home_banner.php');
        $migration->up();
        $this->assertSame('Golden Friendship', $hero->fresh()->title);
        $this->assertSame('Masonic Lodge No. 40', $hero->fresh()->subtitle);
        $this->assertSame('<p>Cagayan de Oro City</p>', $page->fresh()->published_sections[0]['body']);
        $this->assertEquals($remaining, array_slice($page->fresh()->published_sections, 1));
        $migration->up();
        $this->assertEquals($remaining, array_slice($page->fresh()->published_sections, 1));
    }
}
