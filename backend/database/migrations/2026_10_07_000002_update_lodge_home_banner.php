<?php

use App\Models\Page;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function () {
            $page = Page::where('slug', 'home')->lockForUpdate()->first();
            if (! $page) {
                return;
            }
            foreach ($page->sections()->where('section_type', 'hero')->where('title', 'S:.I:.G:.L:.O:.')->where('subtitle', 'FEDERATION')->get() as $section) {
                $section->update([
                    'title' => 'Golden Friendship',
                    'subtitle' => 'Masonic Lodge No. 40',
                    'body' => '<p>Cagayan de Oro City</p>',
                    'settings' => array_merge($section->settings ?? [], ['lodge_hero' => true]),
                ]);
            }
            $published = $page->published_sections;
            if (is_array($published)) {
                foreach ($published as &$section) {
                    if (($section['section_type'] ?? '') === 'hero' && ($section['title'] ?? '') === 'S:.I:.G:.L:.O:.' && ($section['subtitle'] ?? '') === 'FEDERATION') {
                        $section['title'] = 'Golden Friendship';
                        $section['subtitle'] = 'Masonic Lodge No. 40';
                        $section['body'] = '<p>Cagayan de Oro City</p>';
                        $section['settings'] = array_merge($section['settings'] ?? [], ['lodge_hero' => true]);
                    }
                }
                unset($section);
                $page->update(['published_sections' => $published]);
            }
        });
    }

    public function down(): void
    {
        // Keep the lodge identity when rolling back unrelated schema changes.
    }
};
