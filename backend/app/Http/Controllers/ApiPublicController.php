<?php

namespace App\Http\Controllers;

use App\Http\Requests\ApplicationRequest;
use App\Http\Resources\PublicMemberResource;
use App\Models\Affiliation;
use App\Models\Celebration;
use App\Models\Member;
use App\Models\Page;
use App\Models\SiteSetting;
use App\Services\ApplicationService;
use Illuminate\Http\Request;

class ApiPublicController extends Controller
{
    public function session(Request $request)
    {
        $user = $request->user('web');

        return response()->json(['user' => $user?->is_admin ? $user->only('id', 'name', 'email', 'username') : null])
            ->header('Cache-Control', 'private, no-store');
    }

    public function site()
    {
        $values = SiteSetting::allValues();
        $allowed = ['branding' => ['name', 'location', 'emblem', 'organization_name', 'organization_short_name', 'federation_logo'], 'theme' => ['primary', 'secondary', 'accent', 'background', 'text', 'heading', 'navbar', 'footer'], 'navigation' => ['home', 'members', 'history', 'celebrations', 'application'], 'header' => ['subtitle'], 'footer' => ['description', 'address', 'email', 'phone', 'facebook', 'instagram', 'copyright']];
        $site = [];
        foreach ($allowed as $group => $keys) {
            $site[$group] = collect($values[$group] ?? [])->only($keys)->all();
        }

        return response()->json(['site' => $site]);
    }

    public function members()
    {
        return PublicMemberResource::collection($this->publicMembers());
    }

    public function page(Request $r, string $slug)
    {
        $page = Page::where('slug', $slug)->where('is_published', true)->firstOrFail();

        return $this->pageResponse($page, false);
    }

    public function preview(string $slug)
    {
        return $this->pageResponse(Page::where('slug', $slug)->firstOrFail(), true);
    }

    private function pageResponse(Page $page, bool $preview)
    {
        $sections = collect($preview ? $page->sections->toArray() : ($page->published_sections ?? []))->where('is_visible', true)->values();
        $types = $sections->pluck('section_type');
        $members = $types->intersect(['officers', 'members_grid'])->isNotEmpty()
            ? PublicMemberResource::collection($this->publicMembers(! $types->contains('members_grid')))->resolve() : [];
        $celebrations = collect();
        if ($types->contains('celebrations')) {
            $query = Celebration::with('type:id,slug,name')->where('is_public', true)->where('event_date', '<=', today()->endOfYear()->toDateString())->orderBy('event_date');
            $showAll = $sections->where('section_type', 'celebrations')->contains(function ($section) use ($page) {
                return ($section['settings']['display_mode'] ?? ($page->slug === 'home' ? 'preview' : 'all')) === 'all';
            });
            if (! $showAll) {
                $query->where('event_date', '>=', today()->toDateString())->limit(3);
            }
            $celebrations = $query->get(['id', 'title', 'category', 'event_date', 'location', 'description', 'image', 'gallery']);
        }

        return response()->json(['page' => $page->only('name', 'slug', 'meta_title', 'meta_description', 'seo'), 'sections' => $sections, 'members' => $members, 'affiliations' => $types->contains('affiliations') ? Affiliation::where('is_visible', true)->orderBy('display_order')->get() : [], 'celebrations' => $celebrations, 'today' => today()->toDateString(), 'preview' => $preview]);
    }

    private function publicMembers(bool $officersOnly = false)
    {
        return Member::with('position:id,name,slug,rank,is_officer')
            ->join('membership_positions', 'membership_positions.id', '=', 'members.membership_position_id')
            ->where('members.is_public', true)->where('members.status', 'active')
            ->when($officersOnly, fn ($query) => $query->where('membership_positions.is_officer', true))
            ->orderBy('membership_positions.rank')->orderBy('members.display_order')->orderBy('members.id')
            ->get(['members.id', 'members.first_name', 'members.middle_name', 'members.last_name', 'members.suffix', 'members.membership_position_id', 'members.profile_photo', 'members.member_since', 'members.biography']);
    }

    public function submit(ApplicationRequest $r, ApplicationService $service)
    {
        $data = $r->validated();
        unset($data['website']);
        $application = $service->submit($data);

        return response()->json(['message' => 'Application submitted successfully.', 'reference_number' => $application->reference_number], 201);
    }
}
