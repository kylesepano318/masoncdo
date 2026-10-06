<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('celebrations', function (Blueprint $table) {
            $table->index(['is_public', 'event_date']);
            $table->index('event_date');
            $table->index('category');
        });
        Schema::table('members', fn (Blueprint $table) => $table->index('display_order'));
        Schema::table('page_sections', fn (Blueprint $table) => $table->index(['page_id', 'display_order']));
        Schema::table('activity_logs', fn (Blueprint $table) => $table->index('created_at'));
    }

    public function down(): void
    {
        Schema::table('celebrations', function (Blueprint $table) {
            $table->dropIndex(['is_public', 'event_date']);
            $table->dropIndex(['event_date']);
            $table->dropIndex(['category']);
        });
        Schema::table('members', fn (Blueprint $table) => $table->dropIndex(['display_order']));
        Schema::table('page_sections', fn (Blueprint $table) => $table->dropIndex(['page_id', 'display_order']));
        Schema::table('activity_logs', fn (Blueprint $table) => $table->dropIndex(['created_at']));
    }
};
