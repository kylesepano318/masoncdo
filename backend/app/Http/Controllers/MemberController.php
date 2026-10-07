<?php

namespace App\Http\Controllers;

use App\Http\Requests\MemberRequest;
use App\Models\Media;
use App\Models\Member;
use App\Models\MembershipPosition;
use App\Services\MediaReferences;
use App\Services\MediaStorage;
use App\Services\MemberService;
use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MemberController extends Controller
{
    public function index(Request $r)
    {
        return response()->json(['members' => Member::with('position')->when($r->query('search'), fn ($q, $s) => $q->where(fn ($q) => $q->where('first_name', 'like', "%$s%")->orWhere('last_name', 'like', "%$s%")))->orderBy('display_order')->paginate(20)->withQueryString(), 'positions' => MembershipPosition::orderBy('rank')->get()]);
    }

    public function create()
    {
        return response()->json(['member' => null, 'positions' => MembershipPosition::orderBy('rank')->get()]);
    }

    public function edit(Member $member)
    {
        return response()->json(['member' => $member, 'positions' => MembershipPosition::orderBy('rank')->get()]);
    }

    public function store(MemberRequest $r, MemberService $service)
    {
        $this->save($r, $service);

        return response()->json(['message' => 'Member added.']);
    }

    public function update(MemberRequest $r, Member $member, MemberService $service)
    {
        $this->save($r, $service, $member);

        return response()->json(['message' => 'Member updated.']);
    }

    private function save(MemberRequest $r, MemberService $service, ?Member $member = null)
    {
        $data = $r->validated();
        unset($data['photo'],$data['replace_officer']);
        $oldPath = $member?->profile_photo;
        if ($r->hasFile('photo')) {
            $upload = app(MediaStorage::class)->upload($r->file('photo'), $data['first_name'].' '.$data['last_name']);
            $data['profile_photo'] = $upload->path;
        }
        $saved = $service->save($data, $member, $r->boolean('replace_officer'));
        if ($oldPath && $oldPath !== $saved->profile_photo && ($old = Media::where('path', $oldPath)->first()) && ! app(MediaReferences::class)->inUse($old)) {
            try {
                app(MediaStorage::class)->destroy($old);
                $old->delete();
            } catch (\Throwable $e) {
                Log::warning('Old member photo cleanup failed', ['media_id' => $old->id]);
            }
        }
    }

    public function destroy(Member $member)
    {
        Audit::log('Member Deleted', ['member_id' => $member->id]);
        $member->delete();

        return response()->json(['message' => 'Member deleted.']);
    }

    public function reorder(Request $r)
    {
        $ids = $r->validate(['ids' => 'required|array', 'ids.*' => 'integer|distinct|exists:members,id'])['ids'];
        DB::transaction(function () use ($ids) {
            foreach ($ids as $i => $id) {
                Member::whereKey($id)->update(['display_order' => $i]);
            }
        });
        Audit::log('Member Updated', ['action' => 'reorder']);

        return response()->json(['message' => 'Order saved.']);
    }
}
