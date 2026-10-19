<?php

use App\Models\Page;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function () {
            $page = Page::where('slug', 'history')->lockForUpdate()->first();
            if (! $page) {
                return; // Fresh installs receive this content from LodgeSeeder.
            }
            $story = json_decode(file_get_contents(database_path('content/history-story.json')), true, flags: JSON_THROW_ON_ERROR);
            $key = $story['settings']['history_key'];
            $sections = $page->sections()->get();
            $placeholder = fn ($section) => ($section['section_type'] ?? '') === 'rich_text'
                && ($section['title'] ?? '') === 'A history to be preserved'
                && str_contains($section['body'] ?? '', 'historical account will be published here');
            $section = $sections->first(fn ($section) => ($section->settings['history_key'] ?? '') === $key);
            if (! $section) {
                $section = $sections->first(fn ($section) => $placeholder($section));
                if ($section) {
                    $section->update($story + ['is_visible' => true]);
                } else {
                    $section = $page->sections()->create($story + ['section_type' => 'rich_text', 'is_visible' => true, 'display_order' => ((int) $sections->max('display_order')) + 1]);
                }
            }
            // Update only this story in the public snapshot, never publish other drafts.
            $published = $page->published_sections;
            if (is_array($published) && ! collect($published)->contains(fn ($item) => ($item['settings']['history_key'] ?? '') === $key)) {
                $index = collect($published)->search($placeholder);
                if ($index !== false) {
                    $published[$index] = array_replace($published[$index], $story, ['is_visible' => true]);
                } else {
                    $published[] = $section->toArray();
                }
                $page->update(['published_sections' => $published]);
            }
        });
    }

    public function down(): void
    {
        // Preserve the lodge's supplied history and any subsequent editorial changes.
    }
};
