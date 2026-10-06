<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->boolean('is_admin')->default(false);
        });
        Schema::create('membership_positions', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('slug')->unique();
            $t->integer('rank');
            $t->boolean('is_officer');
            $t->timestamps();
        });
        Schema::create('members', function (Blueprint $t) {
            $t->id();
            $t->string('member_number')->nullable();
            $t->string('first_name');
            $t->string('middle_name')->nullable();
            $t->string('last_name');
            $t->string('suffix')->nullable();
            $t->foreignId('membership_position_id')->constrained();
            $t->unsignedBigInteger('active_officer_position')->nullable()->unique();
            $t->string('profile_photo')->nullable();
            $t->date('member_since')->nullable();
            $t->text('biography')->nullable();
            $t->string('status')->default('active');
            $t->integer('display_order')->default(0);
            $t->boolean('is_public')->default(true);
            $t->timestamps();
        });
        Schema::create('application_sequences', function (Blueprint $t) {
            $t->unsignedInteger('year')->primary();
            $t->unsignedInteger('value')->default(0);
        });
        Schema::create('applications', function (Blueprint $t) {
            $t->id();
            $t->string('reference_number')->unique();
            $t->string('first_name');
            $t->string('middle_name')->nullable();
            $t->string('last_name');
            $t->string('suffix')->nullable();
            $t->date('date_of_birth');
            foreach (['place_of_birth', 'civil_status', 'occupation', 'employer', 'postal_code', 'source', 'referring_member'] as $f) {
                $t->string($f)->nullable();
            } $t->text('address');
            $t->string('city');
            $t->string('province');
            $t->string('mobile');
            $t->string('email');
            $t->text('motivation')->nullable();
            $t->boolean('referred')->default(false);
            $t->boolean('declaration');
            $t->boolean('consent');
            $t->timestamp('consented_at');
            $t->string('status')->default('pending');
            $t->text('internal_notes')->nullable();
            $t->foreignId('converted_member_id')->nullable()->unique()->constrained('members')->nullOnDelete();
            $t->timestamp('converted_at')->nullable();
            $t->timestamps();
        });
        Schema::create('pages', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('slug')->unique();
            $t->string('meta_title')->nullable();
            $t->text('meta_description')->nullable();
            $t->json('seo')->nullable();
            $t->boolean('is_published')->default(true);
            $t->json('published_sections')->nullable();
            $t->timestamps();
        });
        Schema::create('page_sections', function (Blueprint $t) {
            $t->id();
            $t->foreignId('page_id')->constrained()->cascadeOnDelete();
            $t->string('section_type');
            $t->string('title')->nullable();
            $t->string('subtitle')->nullable();
            $t->longText('body')->nullable();
            $t->string('image')->nullable();
            $t->string('mobile_image')->nullable();
            $t->json('settings')->nullable();
            $t->integer('display_order')->default(0);
            $t->boolean('is_visible')->default(true);
            $t->timestamps();
        });
        Schema::create('media', function (Blueprint $t) {
            $t->id();
            $t->string('filename');
            $t->string('original_name');
            $t->string('disk')->default('public');
            $t->string('path');
            $t->string('mime_type');
            $t->unsignedBigInteger('size');
            $t->string('alt_text')->default('');
            $t->text('caption')->nullable();
            $t->timestamps();
        });
        Schema::create('affiliations', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('logo')->nullable();
            $t->string('subtitle')->nullable();
            $t->text('description')->nullable();
            $t->string('website_url')->nullable();
            $t->integer('display_order')->default(0);
            $t->boolean('is_visible')->default(true);
            $t->timestamps();
        });
        Schema::create('site_settings', function (Blueprint $t) {
            $t->id();
            $t->string('key')->unique();
            $t->json('value');
            $t->timestamps();
        });
        Schema::create('activity_logs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->string('event');
            $t->json('context')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['activity_logs', 'site_settings', 'affiliations', 'media', 'page_sections', 'pages', 'applications', 'application_sequences', 'members', 'membership_positions'] as $t) {
            Schema::dropIfExists($t);
        } Schema::table('users', fn (Blueprint $t) => $t->dropColumn('is_admin'));
    }
};
