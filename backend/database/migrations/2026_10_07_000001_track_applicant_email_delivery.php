<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->timestamp('acknowledgment_email_sent_at')->nullable();
            $table->timestamp('acknowledgment_email_failed_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('applications', fn (Blueprint $table) => $table->dropColumn(['acknowledgment_email_sent_at', 'acknowledgment_email_failed_at']));
    }
};
