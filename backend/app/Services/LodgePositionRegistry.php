<?php

namespace App\Services;

use App\Models\Member;
use App\Models\MembershipPosition;
use Illuminate\Support\Facades\DB;

class LodgePositionRegistry
{
    public function apply(bool $updateExisting = false): void
    {
        // Keep the original four creation slots; public order is determined by rank.
        $positions = [
            ['Worshipful Master', 'worshipful-master', 1, true, []],
            ['Senior Warden', 'senior-warden', 2, true, []],
            ['Junior Warden', 'junior-warden', 3, true, []],
            ['Member', 'member', 13, false, []],
            ['Lodge Secretary', 'secretary', 4, true, ['lodge-secretary']],
            ['Lodge Treasurer', 'treasurer', 5, true, ['lodge-treasurer']],
            ['Lodge Orator', 'orator', 6, true, ['lodge-orator']],
            ['Lodge Marshall / Expert', 'marshal-expert', 7, true, ['lodge-marshal-expert', 'marshal', 'expert', 'lodge-marshal', 'lodge-expert', 'marshall-expert', 'lodge-marshall-expert', 'marshall', 'lodge-marshall']],
            ['Lodge Chaplain', 'chaplain', 8, true, ['lodge-chaplain']],
            ['Lodge Standard Bearer', 'standard-bearer', 9, true, ['lodge-standard-bearer']],
            ['Lodge Sword Bearer', 'sword-bearer', 10, true, ['lodge-sword-bearer']],
            ['Lodge Pursuivant', 'pursuivant', 11, true, ['lodge-pursuivant']],
            ['Lodge Tyler', 'tyler', 12, true, ['lodge-tyler']],
        ];
        DB::transaction(function () use ($positions, $updateExisting) {
            $known = [];
            foreach ($positions as [$name, $slug, $rank, $isOfficer, $aliases]) {
                $position = MembershipPosition::whereIn('slug', [$slug, ...$aliases])->orderBy('id')->lockForUpdate()->first();
                if (! $position) {
                    $position = MembershipPosition::create(['name' => $name, 'slug' => $slug, 'rank' => $rank, 'is_officer' => $isOfficer]);
                } elseif ($updateExisting) {
                    // Preserve IDs/slugs so existing assignments and links remain intact.
                    $position->update(['name' => $name, 'rank' => $rank, 'is_officer' => $isOfficer]);
                    Member::where('membership_position_id', $position->id)->update(['active_officer_position' => null]);
                    if ($isOfficer) {
                        Member::where('membership_position_id', $position->id)->where('status', 'active')->update(['active_officer_position' => $position->id]);
                    }
                }
                $known[] = $position->id;
            }
            if ($updateExisting) {
                foreach (MembershipPosition::whereNotIn('id', $known)->orderBy('rank')->orderBy('id')->lockForUpdate()->get() as $i => $position) {
                    $position->update(['rank' => 14 + $i]);
                }
            }
        });
    }
}
