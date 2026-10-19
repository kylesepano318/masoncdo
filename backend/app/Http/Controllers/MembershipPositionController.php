<?php

namespace App\Http\Controllers;

use App\Models\Member;
use App\Models\MembershipPosition;
use App\Support\Audit;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class MembershipPositionController extends Controller
{
    public function destroy(MembershipPosition $membershipPosition)
    {
        DB::transaction(function () use ($membershipPosition) {
            $position = MembershipPosition::whereKey($membershipPosition->id)->lockForUpdate()->firstOrFail();
            if ($position->members()->exists()) {
                throw ValidationException::withMessages(['position' => 'Reassign all members from this position before deleting it, including inactive and private members.']);
            }
            $position->delete();
            Audit::log('Membership Position Deleted', ['position_id' => $position->id]);
        });

        return response()->json(['message' => 'Membership position deleted.']);
    }

    public function reorder(Request $request)
    {
        $ids = $request->validate(['ids' => 'required|array|min:1', 'ids.*' => 'required|integer|distinct'])['ids'];
        DB::transaction(function () use ($ids) {
            $current = MembershipPosition::orderBy('id')->lockForUpdate()->pluck('id')->all();
            $submitted = array_map('intval', $ids);
            sort($current);
            sort($submitted);
            if ($current !== $submitted) {
                throw ValidationException::withMessages(['ids' => 'The position list changed. Refresh the page and try again.']);
            }
            foreach ($ids as $i => $id) {
                MembershipPosition::whereKey($id)->update(['rank' => $i + 1]);
            }
            Audit::log('Membership Positions Reordered');
        });

        return response()->json(['message' => 'Position order saved.']);
    }

    public function update(Request $request, MembershipPosition $membershipPosition)
    {
        $data = $request->validate(['name' => 'required|string|max:100', 'rank' => 'required|integer|min:1|max:1000', 'is_officer' => 'required|boolean']);
        $data['name'] = trim($data['name']);
        $data['slug'] = Str::slug($data['name']) ?: 'position-'.hash('sha256', mb_strtolower($data['name']));
        if ($data['name'] === '' || MembershipPosition::where('slug', $data['slug'])->whereKeyNot($membershipPosition->id)->exists()) {
            throw ValidationException::withMessages(['name' => 'Enter a unique position name.']);
        }
        try {
            DB::transaction(function () use ($data, $membershipPosition) {
                // Lock the ordered registry before shifting ranks so concurrent edits
                // and arrow reorders cannot interleave part of a move.
                $positions = MembershipPosition::orderBy('id')->lockForUpdate()->get();
                $position = $positions->firstWhere('id', $membershipPosition->id);
                abort_unless($position, 404);
                $oldRank = (int) $position->rank;
                $newRank = (int) $data['rank'];
                if ($newRank < $oldRank) {
                    MembershipPosition::whereKeyNot($position->id)->whereBetween('rank', [$newRank, $oldRank - 1])->increment('rank');
                } elseif ($newRank > $oldRank) {
                    MembershipPosition::whereKeyNot($position->id)->whereBetween('rank', [$oldRank + 1, $newRank])->decrement('rank');
                }
                Member::where('membership_position_id', $position->id)->update(['active_officer_position' => null]);
                if ($data['is_officer']) {
                    Member::where('membership_position_id', $position->id)->where('status', 'active')->update(['active_officer_position' => $position->id]);
                }
                $position->update($data);
                Audit::log('Membership Position Updated', ['position_id' => $position->id]);
            });
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['name' => 'This membership position already exists.']);
        }

        return response()->json(['message' => 'Membership position updated.']);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:100',
            'rank' => 'required|integer|min:1|max:1000',
            'is_officer' => 'required|boolean',
        ]);
        $data['name'] = trim($data['name']);
        $data['slug'] = Str::slug($data['name']) ?: 'position-'.hash('sha256', mb_strtolower($data['name']));
        if ($data['name'] === '' || MembershipPosition::where('slug', $data['slug'])->exists()) {
            throw ValidationException::withMessages(['name' => 'Enter a new, unique position name.']);
        }
        try {
            $position = DB::transaction(function () use ($data) {
                MembershipPosition::orderBy('id')->lockForUpdate()->get();
                MembershipPosition::where('rank', '>=', $data['rank'])->increment('rank');
                $position = MembershipPosition::create($data);
                Audit::log('Membership Position Created', ['position_id' => $position->id]);

                return $position;
            });
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['name' => 'This membership position already exists.']);
        }

        return response()->json(['message' => 'Membership position added.', 'position' => $position], 201);
    }
}
