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
        $payload = ['name' => 'Assistant Secretary', 'rank' => 5, 'is_officer' => true];
        $this->postJson('/api/admin/membership-positions', $payload)->assertUnauthorized();
        $this->actingAs(User::factory()->create())->postJson('/api/admin/membership-positions', $payload)->assertForbidden();
        $this->actingAs($this->admin())->postJson('/api/admin/membership-positions', $payload)->assertCreated()->assertJsonPath('position.slug', 'assistant-secretary');
        $this->getJson('/api/admin/members/create')->assertJsonFragment(['name' => 'Assistant Secretary', 'is_officer' => true]);
    }

    public function test_invalid_and_duplicate_positions_are_rejected(): void
    {
        $this->actingAs($this->admin());
        $this->postJson('/api/admin/membership-positions', ['name' => ' ', 'rank' => 0, 'is_officer' => 'invalid'])->assertJsonValidationErrors(['name', 'rank', 'is_officer']);
        foreach (['Member', 'MEMBER', 'Worshipful Master', 'Worshipful-Master'] as $name) {
            $this->postJson('/api/admin/membership-positions', ['name' => $name, 'rank' => 5, 'is_officer' => false])->assertJsonValidationErrors('name');
        }
        $this->assertDatabaseCount('membership_positions', 13);
    }

    public function test_delete_and_reorder_require_an_admin(): void
    {
        $position = MembershipPosition::firstOrFail();
        $ids = MembershipPosition::pluck('id')->all();
        $this->deleteJson('/api/admin/membership-positions/'.$position->id)->assertUnauthorized();
        $this->postJson('/api/admin/membership-positions/reorder', ['ids' => $ids])->assertUnauthorized();
        $this->actingAs(User::factory()->create());
        $this->deleteJson('/api/admin/membership-positions/'.$position->id)->assertForbidden();
        $this->postJson('/api/admin/membership-positions/reorder', ['ids' => $ids])->assertForbidden();
    }

    public function test_only_unassigned_positions_can_be_deleted_including_private_and_inactive_members(): void
    {
        $this->actingAs($this->admin());
        $unused = MembershipPosition::where('slug', 'tyler')->firstOrFail();
        $this->deleteJson('/api/admin/membership-positions/'.$unused->id)->assertOk();
        $this->assertDatabaseMissing('membership_positions', ['id' => $unused->id]);
        $position = MembershipPosition::where('slug', 'member')->firstOrFail();
        foreach (['active', 'inactive', 'past_officer'] as $status) {
            $member = app(MemberService::class)->save(['first_name' => 'Assigned', 'last_name' => 'Member', 'membership_position_id' => $position->id, 'status' => $status, 'is_public' => false]);
            $this->deleteJson('/api/admin/membership-positions/'.$position->id)->assertJsonValidationErrors('position');
            $response = $this->getJson('/api/admin/members')->assertOk();
            $this->assertSame(1, collect($response->json('positions'))->firstWhere('id', $position->id)['members_count']);
            $this->assertDatabaseHas('members', ['id' => $member->id, 'membership_position_id' => $position->id]);
            $member->delete();
        }
        $this->deleteJson('/api/admin/membership-positions/'.$position->id)->assertOk();
    }

    public function test_reordering_saves_all_ranks_and_rejects_stale_or_invalid_lists(): void
    {
        $this->actingAs($this->admin());
        $ids = MembershipPosition::orderBy('rank')->pluck('id')->all();
        [$ids[0], $ids[3]] = [$ids[3], $ids[0]];
        $this->postJson('/api/admin/membership-positions/reorder', ['ids' => $ids])->assertOk();
        $this->assertSame($ids, MembershipPosition::orderBy('rank')->pluck('id')->all());
        $this->assertSame(range(1, count($ids)), MembershipPosition::orderBy('rank')->pluck('rank')->all());
        $this->postJson('/api/admin/membership-positions/reorder', ['ids' => array_slice($ids, 1)])->assertJsonValidationErrors('ids');
        $this->postJson('/api/admin/membership-positions/reorder', ['ids' => [$ids[0], $ids[0]]])->assertUnprocessable();
        $this->postJson('/api/admin/membership-positions/reorder', ['ids' => [...$ids, 999999]])->assertJsonValidationErrors('ids');
        $this->assertSame($ids, MembershipPosition::orderBy('rank')->pluck('id')->all());
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

    public function test_editing_rank_inserts_position_and_shifts_both_directions(): void
    {
        $this->actingAs($this->admin());
        $this->postJson('/api/admin/membership-positions', ['name' => 'Additional Officer', 'rank' => 14, 'is_officer' => true])->assertCreated();
        $position = MembershipPosition::where('slug', 'additional-officer')->firstOrFail();
        $before = MembershipPosition::orderBy('rank')->pluck('id')->all();
        $payload = ['name' => $position->name, 'rank' => 7, 'is_officer' => true];
        $this->putJson('/api/admin/membership-positions/'.$position->id, $payload)->assertOk();
        $expected = $before;
        array_pop($expected);
        array_splice($expected, 6, 0, [$position->id]);
        $this->assertSame($expected, MembershipPosition::orderBy('rank')->pluck('id')->all());
        foreach (array_slice($before, 6, 7) as $index => $id) {
            $this->assertSame(8 + $index, MembershipPosition::findOrFail($id)->rank);
        }
        $this->assertSame(range(1, 14), MembershipPosition::orderBy('rank')->pluck('rank')->all());
        // Saving at the same rank must not shift any other position again.
        $this->putJson('/api/admin/membership-positions/'.$position->id, $payload)->assertOk();
        $this->assertSame($expected, MembershipPosition::orderBy('rank')->pluck('id')->all());
        $this->putJson('/api/admin/membership-positions/'.$position->id, array_replace($payload, ['rank' => 14]))->assertOk();
        $this->assertSame($before, MembershipPosition::orderBy('rank')->pluck('id')->all());
        $this->assertSame(range(1, 14), MembershipPosition::orderBy('rank')->pluck('rank')->all());
    }

    public function test_new_position_inserts_at_occupied_rank_and_invalid_edit_does_not_shift(): void
    {
        $this->actingAs($this->admin());
        $before = MembershipPosition::orderBy('rank')->pluck('id')->all();
        $this->postJson('/api/admin/membership-positions', ['name' => 'Inserted Officer', 'rank' => 7, 'is_officer' => true])->assertCreated();
        $position = MembershipPosition::where('slug', 'inserted-officer')->firstOrFail();
        $expected = $before;
        array_splice($expected, 6, 0, [$position->id]);
        $this->assertSame($expected, MembershipPosition::orderBy('rank')->pluck('id')->all());
        $this->assertSame(range(1, 14), MembershipPosition::orderBy('rank')->pluck('rank')->all());
        $this->putJson('/api/admin/membership-positions/'.$position->id, ['name' => 'Worshipful Master', 'rank' => 1, 'is_officer' => true])->assertJsonValidationErrors('name');
        $this->assertSame($expected, MembershipPosition::orderBy('rank')->pluck('id')->all());
        $this->assertSame(range(1, 14), MembershipPosition::orderBy('rank')->pluck('rank')->all());
    }

    public function test_custom_regular_and_officer_positions_follow_existing_membership_rules(): void
    {
        $this->actingAs($this->admin());
        foreach ([['name' => 'Assistant Secretary', 'rank' => 5, 'is_officer' => true], ['name' => 'Honorary Member', 'rank' => 6, 'is_officer' => false]] as $payload) {
            $this->postJson('/api/admin/membership-positions', $payload)->assertCreated();
        }
        $secretary = MembershipPosition::where('slug', 'assistant-secretary')->firstOrFail();
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
        $this->getJson('/api/public/members')->assertJsonFragment(['name' => 'Assistant Secretary'])->assertJsonFragment(['name' => 'Honorary Member']);
    }
}
