<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            if (Schema::hasColumn('users', 'admin_slot')) {
                Schema::table('users', function (Blueprint $table) {
                    $table->dropUnique('users_one_admin');
                    $table->dropColumn('admin_slot');
                });
            }
        } else {
            DB::statement('DROP INDEX IF EXISTS users_one_admin');
        }
        Schema::table('users', function (Blueprint $table) {
            $table->string('username')->nullable()->unique();
        });
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('username'));
        // Keep multiple administrator accounts on rollback; never remove account data.
    }
};
