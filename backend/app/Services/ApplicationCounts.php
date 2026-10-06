<?php

namespace App\Services;

use App\Models\LodgeApplication;

class ApplicationCounts
{
    public function get(): array
    {
        $counts = LodgeApplication::query()->selectRaw("COUNT(CASE WHEN status = 'pending' THEN 1 END) AS pending, COUNT(CASE WHEN is_read_by_admin = false THEN 1 END) AS unread, COUNT(CASE WHEN submitted_at BETWEEN ? AND ? THEN 1 END) AS this_month", [now()->startOfMonth(), now()->endOfMonth()])->first();

        return ['pending' => (int) $counts->pending, 'unread' => (int) $counts->unread, 'this_month' => (int) $counts->this_month];
    }
}
