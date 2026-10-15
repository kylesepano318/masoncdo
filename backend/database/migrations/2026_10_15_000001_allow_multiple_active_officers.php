<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('members', fn (Blueprint $table) => $table->dropUnique(['active_officer_position']));
    }

    public function down(): void
    {
        if (DB::table('members')->whereNotNull('active_officer_position')->groupBy('active_officer_position')->havingRaw('COUNT(*) > 1')->exists()) {
            throw new RuntimeException('Cannot restore the single-officer restriction while multiple active officers share a position.');
        }
        Schema::table('members', fn (Blueprint $table) => $table->unique('active_officer_position'));
    }
};
