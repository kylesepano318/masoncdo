<?php

namespace App\Http\Controllers;

use App\Models\CelebrationType;
use App\Support\Audit;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CelebrationTypeController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate(['name' => 'required|string|max:100']);
        $data['name'] = trim($data['name']);
        $data['slug'] = str_replace('-', '_', Str::slug($data['name'])) ?: 'type_'.hash('sha256', mb_strtolower($data['name']));
        if ($data['name'] === '' || CelebrationType::where('slug', $data['slug'])->exists()) {
            throw ValidationException::withMessages(['name' => 'Enter a new, unique celebration type name.']);
        }
        try {
            $type = DB::transaction(function () use ($data) {
                $type = CelebrationType::create($data);
                Audit::log('Celebration Type Created', ['type_id' => $type->id]);

                return $type;
            });
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['name' => 'This celebration type already exists.']);
        }

        return response()->json(['message' => 'Celebration type added.', 'type' => $type], 201);
    }

    public function destroy(CelebrationType $celebrationType)
    {
        DB::transaction(function () use ($celebrationType) {
            $type = CelebrationType::whereKey($celebrationType->id)->lockForUpdate()->firstOrFail();
            if ($type->celebrations()->exists()) {
                throw ValidationException::withMessages(['type' => 'This type is used by celebrations. Change their type or delete those celebrations first.']);
            }
            $type->delete();
            Audit::log('Celebration Type Removed', ['type_id' => $type->id]);
        });

        return response()->json(['message' => 'Celebration type deleted.']);
    }
}
