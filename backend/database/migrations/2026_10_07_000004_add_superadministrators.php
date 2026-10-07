<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->boolean('is_superadmin')->default(false));
        // Upgrade the original seeded accounts without changing their credentials.
        DB::table('users')->where('is_admin', true)->where(function ($query) {
            $query->whereIn('username', ['worshipfulmasterRJMahilum', 'milzan'])
                ->orWhereIn('email', ['worshipfulmasterRJMahilum@gfmasoniclodge40.org', 'milzan@gfmasoniclodge40.org']);
        })->update(['is_superadmin' => true]);
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('is_superadmin'));
    }
};
