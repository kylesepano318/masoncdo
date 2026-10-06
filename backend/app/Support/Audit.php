<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

class Audit
{
    public static function log(string $event, array $context = []): void
    {
        DB::table('activity_logs')->insert(['user_id' => auth()->id(), 'event' => $event, 'context' => json_encode($context), 'created_at' => now(), 'updated_at' => now()]);
    }
}
