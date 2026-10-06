<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('applications', function (Blueprint $t) {
            foreach (['address' => 'complete_address', 'mobile' => 'mobile_number', 'motivation' => 'reason_for_joining', 'source' => 'how_did_you_hear', 'referred' => 'has_referrer', 'referring_member' => 'referring_member_name', 'internal_notes' => 'admin_notes'] as $old => $new) {
                $t->renameColumn($old, $new);
            }
        });
        Schema::table('applications', function (Blueprint $t) {
            $t->boolean('is_read_by_admin')->default(false)->index();
            foreach (['certification_accepted_at', 'privacy_consent_accepted_at', 'submitted_at', 'reviewed_at', 'notification_email_sent_at', 'notification_email_failed_at'] as $f) {
                $t->timestamp($f)->nullable();
            }$t->string('notification_email_error')->nullable();
            $t->index('status');
            $t->index('submitted_at');
            $t->index('email');
        });
        DB::table('applications')->update(['submitted_at' => DB::raw('created_at'), 'certification_accepted_at' => DB::raw('consented_at'), 'privacy_consent_accepted_at' => DB::raw('consented_at')]);
        Schema::table('media', function (Blueprint $t) {
            $t->string('cloudinary_public_id')->nullable()->unique();
            $t->string('secure_url')->nullable();
            $t->string('resource_type')->default('image');
            $t->unsignedInteger('width')->nullable();
            $t->unsignedInteger('height')->nullable();
            $t->unsignedBigInteger('bytes')->nullable();
        });
        Schema::table('members', function (Blueprint $t) {
            $t->index('membership_position_id');
            $t->index('status');
            $t->index('is_public');
        });
        Schema::table('page_sections', fn (Blueprint $t) => $t->index('display_order'));
    }

    public function down(): void
    {
        Schema::table('page_sections', fn (Blueprint $t) => $t->dropIndex(['display_order']));
        Schema::table('members', function (Blueprint $t) {
            foreach (['membership_position_id', 'status', 'is_public'] as $f) {
                $t->dropIndex([$f]);
            }
        });
        Schema::table('media', fn (Blueprint $t) => $t->dropColumn(['cloudinary_public_id', 'secure_url', 'resource_type', 'width', 'height', 'bytes']));
        Schema::table('applications', function (Blueprint $t) {
            foreach (['status', 'submitted_at', 'email', 'is_read_by_admin'] as $f) {
                $t->dropIndex([$f]);
            }$t->dropColumn(['is_read_by_admin', 'certification_accepted_at', 'privacy_consent_accepted_at', 'submitted_at', 'reviewed_at', 'notification_email_sent_at', 'notification_email_failed_at', 'notification_email_error']);
        });
        Schema::table('applications', function (Blueprint $t) {
            foreach (['address' => 'complete_address', 'mobile' => 'mobile_number', 'motivation' => 'reason_for_joining', 'source' => 'how_did_you_hear', 'referred' => 'has_referrer', 'referring_member' => 'referring_member_name', 'internal_notes' => 'admin_notes'] as $old => $new) {
                $t->renameColumn($new, $old);
            }
        });
    }
};
