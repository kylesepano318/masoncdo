<?php

namespace Tests\Feature;

use App\Models\MembershipPosition;
use App\Services\LodgePositionRegistry;
use App\Services\MemberService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LodgePositionGuideTest extends TestCase
{
    use RefreshDatabase;

    public function test_guide_order_and_officer_classification_are_used_in_public_members(): void
    {
        $this->seed();
        $expected = ['Worshipful Master', 'Senior Warden', 'Junior Warden', 'Lodge Secretary', 'Lodge Treasurer', 'Lodge Orator', 'Lodge Marshall / Expert', 'Lodge Chaplain', 'Lodge Standard Bearer', 'Lodge Sword Bearer', 'Lodge Pursuivant', 'Lodge Tyler', 'Member'];
        $positions = MembershipPosition::orderBy('rank')->get();
        $this->assertSame($expected, $positions->pluck('name')->all());
        $this->assertCount(12, $positions->where('is_officer', true));
        foreach ($positions as $position) {
            app(MemberService::class)->save(['first_name' => 'Public', 'last_name' => $position->name, 'membership_position_id' => $position->id, 'status' => 'active', 'is_public' => true]);
        }
        $response = $this->getJson('/api/public/members')->assertOk();
        $this->assertSame($expected, collect($response->json('data'))->pluck('position.name')->all());
    }

    public function test_existing_alias_assignments_and_custom_positions_are_preserved(): void
    {
        $secretary = MembershipPosition::where('slug', 'secretary')->firstOrFail();
        $secretary->update(['name' => 'Secretary', 'slug' => 'lodge-secretary', 'is_officer' => false, 'rank' => 25]);
        $members = [];
        foreach (['First', 'Second'] as $name) {
            $members[] = app(MemberService::class)->save(['first_name' => $name, 'last_name' => 'Secretary', 'membership_position_id' => $secretary->id, 'status' => 'active', 'is_public' => true]);
        }
        $custom = MembershipPosition::create(['name' => 'Custom Position', 'slug' => 'custom-position', 'rank' => 2, 'is_officer' => false]);
        $migration = require database_path('migrations/2026_10_19_000002_apply_lodge_position_guide.php');
        $migration->up();
        $migration->up();
        $this->assertDatabaseCount('membership_positions', 14);
        $this->assertSame('Lodge Secretary', $secretary->fresh()->name);
        $this->assertSame(4, $secretary->fresh()->rank);
        $this->assertSame('lodge-secretary', $secretary->fresh()->slug);
        foreach ($members as $member) {
            $this->assertSame($secretary->id, $member->fresh()->membership_position_id);
            $this->assertSame($secretary->id, $member->fresh()->active_officer_position);
            $this->assertSame('active', $member->fresh()->status);
        }
        $this->assertSame('Custom Position', $custom->fresh()->name);
        $this->assertGreaterThan(13, $custom->fresh()->rank);
        $secretary->update(['name' => 'Edited Secretary', 'rank' => 30]);
        app(LodgePositionRegistry::class)->apply();
        $this->assertSame('Edited Secretary', $secretary->fresh()->name);
        $this->assertSame(30, $secretary->fresh()->rank);
    }
}
