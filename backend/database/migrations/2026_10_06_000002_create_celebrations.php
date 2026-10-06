<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('celebrations', function (Blueprint $t) {
            $t->id();
            $t->string('title');
            $t->string('category')->default('fellowship');
            $t->date('event_date');
            $t->string('location')->nullable();
            $t->text('description')->nullable();
            $t->string('image')->nullable();
            $t->json('gallery')->nullable();
            $t->foreignId('member_id')->nullable()->constrained('members')->nullOnDelete();
            $t->boolean('is_public')->default(true);
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('celebrations');
    }
};
