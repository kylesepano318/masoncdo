<?php

use App\Services\LodgePositionRegistry;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        app(LodgePositionRegistry::class)->apply(updateExisting: true);
    }

    public function down(): void
    {
        // Keep positions and their member assignments when rolling back.
    }
};
