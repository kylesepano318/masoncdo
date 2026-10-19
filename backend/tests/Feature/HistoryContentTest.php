<?php

namespace Tests\Feature;

use App\Models\Page;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HistoryContentTest extends TestCase
{
    use RefreshDatabase;

    public function test_fresh_history_includes_supplied_story_and_existing_photographs(): void
    {
        $this->seed();
        $response = $this->getJson('/api/public/pages/history')->assertOk();
        $body = collect($response->json('sections'))->firstWhere('section_type', 'rich_text')['body'];
        foreach (['Origins &amp; Foundation', 'Early Years &amp; Growth', 'Core Mission &amp; Legacy', 'Mahilum', 'Paradillo', 'Umalay', '06 September 2026', '12 October 2026', 'So Mote It Be.'] as $text) {
            $this->assertStringContainsString($text, $body);
        }
        $this->assertTrue(collect($response->json('sections'))->contains('section_type', 'gallery'));
    }

    public function test_upgrade_replaces_placeholder_without_publishing_other_drafts_and_is_repeatable(): void
    {
        $this->seed();
        $page = Page::where('slug', 'history')->firstOrFail();
        $story = $page->sections()->where('section_type', 'rich_text')->firstOrFail();
        $story->update(['title' => 'A history to be preserved', 'body' => '<p>The historical account will be published here.</p>', 'settings' => []]);
        $page->update(['published_sections' => $page->sections()->get()->toArray()]);
        $otherPublished = array_values(array_filter($page->published_sections, fn ($section) => $section['id'] !== $story->id));
        $hero = $page->sections()->where('section_type', 'hero')->firstOrFail();
        $hero->update(['title' => 'UNPUBLISHED HERO CHANGE']);
        $migration = require database_path('migrations/2026_10_19_000001_add_lodge_history.php');
        $migration->up();
        $migration->up();
        $this->assertStringContainsString('So Mote It Be.', $story->fresh()->body);
        $this->assertSame(1, $page->sections()->where('section_type', 'rich_text')->count());
        $this->assertEquals($otherPublished, array_values(array_filter($page->fresh()->published_sections, fn ($section) => $section['id'] !== $story->id)));
        $this->assertSame('UNPUBLISHED HERO CHANGE', $hero->fresh()->title);
        $this->getJson('/api/public/pages/history')->assertOk()->assertJsonMissing(['title' => 'UNPUBLISHED HERO CHANGE']);
    }

    public function test_upgrade_keeps_a_custom_existing_story(): void
    {
        $this->seed();
        $page = Page::where('slug', 'history')->firstOrFail();
        $original = $page->sections()->where('section_type', 'rich_text')->firstOrFail();
        $original->update(['title' => 'Our existing archive', 'body' => '<p>Keep this custom history.</p>', 'settings' => []]);
        $page->update(['published_sections' => $page->sections()->get()->toArray()]);
        $migration = require database_path('migrations/2026_10_19_000001_add_lodge_history.php');
        $migration->up();
        $migration->up();
        $this->assertSame('<p>Keep this custom history.</p>', $original->fresh()->body);
        $this->assertSame(2, $page->sections()->where('section_type', 'rich_text')->count());
        $this->assertCount(2, collect($page->fresh()->published_sections)->where('section_type', 'rich_text'));
    }
}
