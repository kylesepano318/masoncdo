<?php

namespace Database\Seeders;

use App\Models\MembershipPosition;
use App\Models\Page;
use App\Models\SiteSetting;
use Illuminate\Database\Seeder;

class LodgeSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['Worshipful Master', 'Senior Warden', 'Junior Warden', 'Member'] as $i => $name) {
            MembershipPosition::firstOrCreate(['slug' => str($name)->slug()->toString()], ['name' => $name, 'rank' => $i + 1, 'is_officer' => $i < 3]);
        }
        $defaults = ['branding' => ['name' => 'Golden Friendship', 'location' => 'Cagayan de Oro City', 'emblem' => '/images/golden-friendship-lodge-no-40.png'], 'theme' => ['primary' => '#111111', 'secondary' => '#101726', 'accent' => '#C59A3D', 'background' => '#F8F5EC', 'text' => '#444139', 'heading' => '#24251F', 'navbar' => '#F8F5EC', 'footer' => '#101726'], 'navigation' => ['home' => 'Home', 'members' => 'Members', 'history' => 'History', 'celebrations' => 'Celebrations', 'application' => 'Application'], 'header' => ['subtitle' => 'MASONIC LODGE NO. 40'], 'footer' => ['description' => 'Liberty. Equality. Fraternity.\nA bond of friendship. A commitment to service.', 'address' => 'Cagayan de Oro City, Philippines', 'email' => '', 'facebook' => '', 'copyright' => 'Golden Friendship Masonic Lodge No. 40']];
        foreach ($defaults as $key => $value) {
            if (isset($value['description'])) {
                $value['description'] = str_replace('\\n', "\n", $value['description']);
            } SiteSetting::firstOrCreate(['key' => $key], ['value' => $value]);
        }
        SiteSetting::firstOrCreate(['key' => 'notifications'], ['value' => ['application_notification_email' => config('lodge.notification_email') ?? '', 'send_application_notification_email' => true, 'send_applicant_confirmation_email' => false]]);
        foreach (['branding' => ['organization_name' => 'Sovereign Independent Grand Lodge of the Orient', 'organization_short_name' => 'S:.I:.G:.L:.O:.', 'federation_logo' => ''], 'footer' => ['phone' => '', 'instagram' => ''], 'notifications' => ['send_application_notification_email' => true]] as $group => $missing) {
            $setting = SiteSetting::where('key', $group)->first();
            $setting->update(['value' => $setting->value + $missing]);
        }
        $emblem = '/images/golden-friendship-lodge-no-40.png';
        $home = json_decode(file_get_contents(database_path('content/home.json')), true, flags: JSON_THROW_ON_ERROR);
        $history = [['hero', 'Our history', 'THE GOLDEN FRIENDSHIP ARCHIVE', '<p>Preserving our story. Honoring our traditions.</p>', null, ['background' => '#101726', 'text_color' => '#F8F5EC']], ['historical_document', 'Our brethren', 'A LIVING FELLOWSHIP', null, '/images/lodge-brethren.jpg', ['alt' => 'Golden Friendship lodge brethren', 'caption' => 'Golden Friendship Masonic Lodge No. 40']], ['rich_text', 'A history to be preserved', 'OUR LODGE STORY', '<p>The lodge’s historical account will be published here following review of its founding documents, charter, and archival records.</p><p>Dates, names, and historical claims will be drawn from source materials provided by the lodge.</p>', null, []], ['timeline', 'Milestones of the lodge', 'OUR TIMELINE', '<p>Verified milestones will be added to this archive.</p>', null, ['items' => []]], ['gallery', 'The lodge in photographs', 'FROM OUR ARCHIVE', null, null, ['items' => [['image' => '/images/lodge-brethren.jpg', 'title' => 'Our brethren'], ['image' => '/images/lodge-ceremony.jpg', 'title' => 'Lodge fellowship']]]], ['quote', 'Liberty. Equality. Fraternity.', 'OUR SHARED PRINCIPLES', null, null, []]];
        $members = [['hero', 'Our lodge', 'THE BRETHREN OF GOLDEN FRIENDSHIP', '<p>Bound by friendship. United in purpose.</p>', null, ['background' => '#101726', 'text_color' => '#F8F5EC']], ['officers', 'Lodge officers', 'OUR LODGE LEADERSHIP', null, null, []], ['members_grid', 'Our brethren', 'MEMBERS', null, null, []]];
        $application = [['hero', 'Begin your journey', 'MEMBERSHIP APPLICATION', '<p>Take the first step toward fellowship with Golden Friendship.</p>', null, ['background' => '#101726', 'text_color' => '#F8F5EC']]];
        $celebrations = [['hero', 'Celebrating together', 'THE LIFE OF OUR LODGE', '<p>Birthdays, milestones, and moments of brotherhood.</p>', null, ['background' => '#101726', 'text_color' => '#F8F5EC']], ['celebrations', 'Our celebrations', 'UPCOMING & RECENT', null, null, []]];
        foreach (['home' => $home, 'history' => $history, 'members' => $members, 'application' => $application, 'celebrations' => $celebrations] as $slug => $sections) {
            if (Page::where('slug', $slug)->exists()) {
                continue;
            } $page = Page::create(['name' => ucfirst($slug), 'slug' => $slug, 'meta_title' => ucfirst($slug).' · Golden Friendship Masonic Lodge No. 40', 'meta_description' => 'Golden Friendship Masonic Lodge No. 40, Cagayan de Oro City. Liberty, Equality, Fraternity.']);
            foreach ($sections as $i => $s) {
                $page->sections()->create(['section_type' => $s[0], 'title' => str_replace('\\n', "\n", $s[1]), 'subtitle' => $s[2], 'body' => $s[3], 'image' => $s[4], 'settings' => $s[5], 'display_order' => $i, 'is_visible' => true]);
            } $page->update(['published_sections' => $page->sections()->get()->toArray()]);
        }
    }
}
