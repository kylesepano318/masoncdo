<?php

namespace App\Http\Controllers;

use App\Models\Affiliation;
use App\Models\Media;
use App\Models\SiteSetting;
use App\Support\Audit;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    public function edit(string $group = 'branding')
    {
        abort_unless(in_array($group, ['branding', 'theme', 'navigation', 'header', 'footer', 'account', 'notifications']), 404);

        return response()->json(['group' => $group, 'settings' => SiteSetting::allValues(), 'media' => Media::latest()->get()]);
    }

    public function update(Request $r, string $group)
    {
        abort_unless(in_array($group, ['branding', 'theme', 'navigation', 'header', 'footer', 'notifications']), 404);
        $rules = match ($group) {
            'notifications' => ['application_notification_email' => 'required|email|max:255', 'send_applicant_confirmation_email' => 'required|boolean', 'send_application_notification_email' => 'required|boolean'],
            'theme' => collect(['primary', 'secondary', 'accent', 'background', 'text', 'heading', 'navbar', 'footer'])->mapWithKeys(fn ($k) => [$k => 'required|regex:/^#[0-9a-fA-F]{6}$/'])->all(), 'branding' => ['name' => 'required|string|max:255', 'location' => 'required|string|max:255', 'emblem' => 'nullable|string|max:1000', 'organization_name' => 'nullable|string|max:255', 'organization_short_name' => 'nullable|string|max:100', 'federation_logo' => 'nullable|string|max:1000'], 'navigation' => ['home' => 'required|string|max:30', 'members' => 'required|string|max:30', 'history' => 'required|string|max:30', 'celebrations' => 'required|string|max:30', 'application' => 'required|string|max:30'], 'header' => ['subtitle' => 'nullable|string|max:255'], 'footer' => ['description' => 'nullable|string|max:2000', 'address' => 'nullable|string|max:1000', 'email' => 'nullable|email', 'facebook' => 'nullable|url:http,https', 'instagram' => 'nullable|url:http,https', 'phone' => 'nullable|string|max:50', 'copyright' => 'nullable|string|max:255']
        };
        $data = $r->validate($rules);
        if ($group === 'branding' && empty($data['emblem'])) {
            $data['emblem'] = '/images/golden-friendship-lodge-no-40.png';
        }
        SiteSetting::updateOrCreate(['key' => $group], ['value' => $data]);
        Audit::log($group === 'theme' ? 'Theme Updated' : ($group === 'branding' ? 'Logo Updated' : 'Page Updated'), ['group' => $group]);

        return response()->json(['message' => 'Settings saved.']);
    }

    public function affiliations()
    {
        return response()->json(['affiliations' => Affiliation::orderBy('display_order')->get(), 'media' => Media::latest()->get()]);
    }

    public function affiliation(Request $r, ?Affiliation $affiliation = null)
    {
        $data = $r->validate(['name' => 'required|string|max:255', 'logo' => 'nullable|string|max:1000', 'subtitle' => 'nullable|string|max:255', 'description' => 'nullable|string|max:5000', 'website_url' => 'nullable|url:http,https', 'display_order' => 'required|integer|min:0', 'is_visible' => 'required|boolean']);
        $affiliation ? $affiliation->update($data) : Affiliation::create($data);
        Audit::log('Page Updated', ['entity' => 'affiliation']);

        return response()->json(['message' => 'Affiliation saved.']);
    }

    public function removeAffiliation(Affiliation $affiliation)
    {
        $affiliation->delete();
        Audit::log('Page Updated', ['entity' => 'affiliation']);

        return response()->json(['message' => 'Changes saved.']);
    }
}
