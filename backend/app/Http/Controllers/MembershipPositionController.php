<?php

namespace App\Http\Controllers;

use App\Models\MembershipPosition;
use App\Support\Audit;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class MembershipPositionController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:100',
            'rank' => 'required|integer|min:1|max:1000',
            'is_officer' => 'required|boolean',
        ]);
        $data['name'] = trim($data['name']);
        $data['slug'] = Str::slug($data['name']) ?: 'position-'.hash('sha256', mb_strtolower($data['name']));
        if ($data['name'] === '' || MembershipPosition::where('slug', $data['slug'])->exists()) {
            throw ValidationException::withMessages(['name' => 'Enter a new, unique position name.']);
        }
        try {
            $position = DB::transaction(function () use ($data) {
                $position = MembershipPosition::create($data);
                Audit::log('Membership Position Created', ['position_id' => $position->id]);

                return $position;
            });
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['name' => 'This membership position already exists.']);
        }

        return response()->json(['message' => 'Membership position added.', 'position' => $position], 201);
    }
}
