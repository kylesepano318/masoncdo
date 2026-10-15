<?php

namespace Tests\Feature;

use App\Models\MembershipPosition;
use App\Models\User;
use App\Services\MemberService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MembershipPositionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->is_admin = true;
        $admin->save();

        return $admin;
    }

    public function test_only_admin_can_add_a_position(): void
    {
        $payload = ['name' => 'Secretary', 'rank' => 5, 'is_officer' => true];
        $this->postJson('/api/admin/membership-positions', $payload)->assertUnauthorized();
        $this->actingAs(User::factory()->create())->postJson('/api/admin/membership-positions', $payload)->assertForbidden();
        $this->actingAs($this->admin())->postJson('/api/admin/membership-positions', $payload)->assertCreated()->assertJsonPath('position.slug', 'secretary');
        $this->getJson('/api/admin/members/create')->assertJsonFragment(['name' => 'Secretary', 'is_officer' => true]);
    }

    public function test_invalid_and_duplicate_positions_are_rejected(): void
    {
        $this->actingAs($this->admin());
        $this->postJson('/api/admin/membership-positions', ['name' => ' ', 'rank' => 0, 'is_officer' => 'invalid'])->assertJsonValidationErrors(['name', 'rank', 'is_officer']);
        foreach (['Member', 'MEMBER', 'Worshipful Master', 'Worshipful-Master'] as $name) {
            $this->postJson('/api/admin/membership-positions', ['name' => $name, 'rank' => 5, 'is_officer' => false])->assertJsonValidationErrors('name');
        }
        $this->assertDatabaseCount('membership_positions', 4);
    }

    public function test_position_edits_preserve_assignments_and_allow_multiple_officers(): void
    {
        $position = MembershipPosition::where('slug', 'member')->firstOrFail();
        $data = ['name' => 'Brethren', 'rank' => 8, 'is_officer' => true];
        $this->putJson('/api/admin/membership-positions/'.$position->id, $data)->assertUnauthorized();
        $this->actingAs(User::factory()->create())->putJson('/api/admin/membership-positions/'.$position->id, $data)->assertForbidden();
        $this->actingAs($this->admin());
        $service = app(MemberService::class);
        $first = $service->save(['first_name' => 'First', 'last_name' => 'Member', 'membership_position_id' => $position->id, 'status' => 'active', 'is_public' => true]);
        $second = $service->save(['first_name' => 'Second', 'last_name' => 'Member', 'membership_position_id' => $position->id, 'status' => 'active', 'is_public' => true]);
        $url = '/api/admin/membership-positions/'.$position->id;
        $this->putJson($url, $data)->assertOk();
        $this->assertSame($position->id, $first->fresh()->membership_position_id);
        $this->assertSame($position->id, $first->fresh()->active_officer_position);
        $this->assertSame($position->id, $second->fresh()->active_officer_position);
        $this->assertSame('Brethren', $position->fresh()->name);
        $this->getJson('/api/public/members')->assertJsonCount(2, 'data')->assertJsonPath('data.0.position.is_officer', true)->assertJsonPath('data.1.position.is_officer', true);
        $this->putJson($url, array_replace($data, ['name' => 'Worshipful Master']))->assertJsonValidationErrors('name');
        $this->putJson($url, array_replace($data, ['is_officer' => false]))->assertOk();
        $this->assertNull($first->fresh()->active_officer_position);
        $this->assertNull($second->fresh()->active_officer_position);
        $this->assertFalse($position->fresh()->is_officer);
    }

    public function test_custom_regular_and_officer_positions_follow_existing_membership_rules(): void
    {
        $this->actingAs($this->admin());
        foreach ([['name' => 'Secretary', 'rank' => 5, 'is_officer' => true], ['name' => 'Honorary Member', 'rank' => 6, 'is_officer' => false]] as $payload) {
            $this->postJson('/api/admin/membership-positions', $payload)->assertCreated();
        }
        $secretary = MembershipPosition::where('slug', 'secretary')->firstOrFail();
        $honorary = MembershipPosition::where('slug', 'honorary-member')->firstOrFail();
        $service = app(MemberService::class);
        $data = ['first_name' => 'First', 'last_name' => 'Officer', 'membership_position_id' => $secretary->id, 'status' => 'active', 'is_public' => true];
        $first = $service->save($data);
        $second = $service->save(array_replace($data, ['first_name' => 'Second']));
        $this->assertSame('active', $first->fresh()->status);
        $this->assertSame($secretary->id, $second->active_officer_position);
        foreach (['One', 'Two'] as $name) {
            $service->save(array_replace($data, ['first_name' => $name, 'membership_position_id' => $honorary->id]));
        }
        $this->assertDatabaseCount('members', 4);
        $this->getJson('/api/public/members')->assertJsonFragment(['name' => 'Secretary'])->assertJsonFragment(['name' => 'Honorary Member']);
    }
}
