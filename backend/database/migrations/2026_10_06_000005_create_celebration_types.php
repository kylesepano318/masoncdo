<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('celebration_types', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('slug')->unique();
            $table->timestamps();
        });
        $slugs = collect(['birthday', 'degree_advancement', 'anniversary', 'fellowship', 'other'])
            ->merge(DB::table('celebrations')->distinct()->pluck('category'))->unique();
        foreach ($slugs as $slug) {
            DB::table('celebration_types')->insert(['slug' => $slug, 'name' => Str::headline($slug), 'created_at' => now(), 'updated_at' => now()]);
        }
        Schema::table('celebrations', function (Blueprint $table) {
            $table->foreign('category')->references('slug')->on('celebration_types')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('celebrations', fn (Blueprint $table) => $table->dropForeign(['category']));
        Schema::dropIfExists('celebration_types');
    }
};
