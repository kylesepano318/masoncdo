<?php

namespace Tests\Feature;

use App\Models\MembershipPosition;
use App\Models\User;
use App\Services\MemberService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
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
        try {
            $service->save(array_replace($data, ['first_name' => 'Second']));
            $this->fail('Officer replacement must require confirmation.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('replace_officer', $e->errors());
        }
        $second = $service->save(array_replace($data, ['first_name' => 'Second']), null, true);
        $this->assertSame('past_officer', $first->fresh()->status);
        $this->assertSame($secretary->id, $second->active_officer_position);
        foreach (['One', 'Two'] as $name) {
            $service->save(array_replace($data, ['first_name' => $name, 'membership_position_id' => $honorary->id]));
        }
        $this->assertDatabaseCount('members', 4);
        $this->getJson('/api/public/members')->assertJsonFragment(['name' => 'Secretary'])->assertJsonFragment(['name' => 'Honorary Member']);
    }
}
