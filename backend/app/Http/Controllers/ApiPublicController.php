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

        return response()->json(['user' => $user?->is_admin ? $user->only('id', 'name', 'email') : null])
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
        return PublicMemberResource::collection(Member::with('position')->where('is_public', true)->where('status', 'active')->get()->sortBy(fn ($m) => [$m->position->rank, $m->display_order])->values());
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
        return response()->json(['page' => $page->only('name', 'slug', 'meta_title', 'meta_description', 'seo'), 'sections' => collect($preview ? $page->sections->toArray() : ($page->published_sections ?? []))->where('is_visible', true)->values(), 'members' => PublicMemberResource::collection(Member::with('position')->where('is_public', true)->where('status', 'active')->get()->sortBy(fn ($m) => [$m->position->rank, $m->display_order])->values())->resolve(), 'affiliations' => Affiliation::where('is_visible', true)->orderBy('display_order')->get(), 'celebrations' => Celebration::with('type')->where('is_public', true)->where('event_date', '<=', today()->endOfYear()->toDateString())->orderBy('event_date')->get(['id', 'title', 'category', 'event_date', 'location', 'description', 'image', 'gallery']), 'today' => today()->toDateString(), 'preview' => $preview]);
    }

    public function submit(ApplicationRequest $r, ApplicationService $service)
    {
        $data = $r->validated();
        unset($data['website']);
        $application = $service->submit($data);

        return response()->json(['message' => 'Application submitted successfully.', 'reference_number' => $application->reference_number], 201);
    }
}
