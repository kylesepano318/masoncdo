<?php

namespace App\Http\Controllers;

use App\Http\Requests\SectionRequest;
use App\Models\Media;
use App\Models\Page;
use App\Models\PageSection;
use App\Support\Audit;
use App\Support\SafeHtml;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CmsController extends Controller
{
    public function edit(Page $page)
    {
        return response()->json(['page' => $page->load('sections'), 'media' => Media::pickerItems()]);
    }

    public function update(Request $r, Page $page)
    {
        $data = $r->validate(['name' => 'required|string|max:255', 'meta_title' => 'nullable|string|max:255', 'meta_description' => 'nullable|string|max:1000', 'seo' => 'nullable|array', 'seo.og_title' => 'nullable|string|max:255', 'seo.og_description' => 'nullable|string|max:1000', 'seo.og_image' => 'nullable|string|max:1000', 'publish' => 'required|boolean']);
        DB::transaction(function () use ($data, $page) {
            $publish = $data['publish'];
            unset($data['publish']);
            if ($publish) {
                $data['published_sections'] = $page->sections()->get()->toArray();
                $data['is_published'] = true;
            } $page->update($data);
            Audit::log('Page Updated', ['page_id' => $page->id, 'published' => $publish]);
        });

        return response()->json(['message' => $r->boolean('publish') ? 'Page published.' : 'Draft saved.']);
    }

    public function store(SectionRequest $r, Page $page)
    {
        $data = $r->validated();
        $data['body'] = SafeHtml::clean($data['body'] ?? null);
        $page->sections()->create($data + ['display_order' => $page->sections()->max('display_order') + 1]);
        Audit::log('Section Added', ['page_id' => $page->id]);

        return response()->json(['message' => 'Section added to draft.']);
    }

    public function section(SectionRequest $r, PageSection $section)
    {
        $data = $r->validated();
        $data['body'] = SafeHtml::clean($data['body'] ?? null);
        $section->update($data);
        Audit::log($section->section_type === 'hero' ? 'Banner Updated' : 'Page Updated', ['section_id' => $section->id]);

        return response()->json(['message' => 'Section saved to draft.']);
    }

    public function duplicate(PageSection $section)
    {
        $copy = $section->replicate();
        $copy->title = $section->title.' (copy)';
        $copy->display_order = PageSection::where('page_id', $section->page_id)->max('display_order') + 1;
        $copy->save();
        Audit::log('Section Added', ['section_id' => $copy->id]);

        return response()->json(['message' => 'Changes saved.']);
    }

    public function destroy(PageSection $section)
    {
        Audit::log('Section Removed', ['section_id' => $section->id]);
        $section->delete();

        return response()->json(['message' => 'Changes saved.']);
    }

    public function reorder(Request $r, Page $page)
    {
        $ids = $r->validate(['ids' => 'required|array', 'ids.*' => 'required|integer|distinct'])['ids'];
        abort_unless($page->sections()->whereIn('id', $ids)->count() === count($ids), 422);
        DB::transaction(function () use ($ids, $page) {
            foreach ($ids as $i => $id) {
                $page->sections()->whereKey($id)->update(['display_order' => $i]);
            } Audit::log('Section Reordered', ['page_id' => $page->id]);
        });

        return response()->json(['message' => 'Changes saved.']);
    }
}
