<?php

namespace App\Http\Controllers;

use App\Models\Celebration;
use App\Models\Media;
use App\Models\Member;
use App\Support\Audit;
use App\Support\SafeHtml;
use Illuminate\Http\Request;

class CelebrationController extends Controller
{
    public function index()
    {
        return response()->json(['celebrations' => Celebration::orderByDesc('event_date')->paginate(20), 'members' => Member::select('id', 'first_name', 'last_name')->get(), 'media' => Media::latest()->get()]);
    }

    public function save(Request $r, ?Celebration $celebration = null)
    {
        $data = $r->validate(['title' => 'required|string|max:255', 'category' => 'required|in:birthday,degree_advancement,anniversary,fellowship,other', 'event_date' => 'required|date', 'location' => 'nullable|string|max:255', 'description' => 'nullable|string|max:20000', 'image' => 'nullable|string|max:1000', 'gallery' => 'nullable|array|max:30', 'gallery.*.image' => 'required|string|max:1000', 'gallery.*.title' => 'nullable|string|max:255', 'member_id' => 'nullable|exists:members,id', 'is_public' => 'required|boolean']);
        $data['description'] = SafeHtml::clean($data['description'] ?? null);
        $celebration ? $celebration->update($data) : Celebration::create($data);
        Audit::log('Celebration Updated');

        return response()->json(['message' => 'Celebration saved.']);
    }

    public function destroy(Celebration $celebration)
    {
        $celebration->delete();
        Audit::log('Celebration Removed');

        return response()->json(['message' => 'Changes saved.']);
    }
}
