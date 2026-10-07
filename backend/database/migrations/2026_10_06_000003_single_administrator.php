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
            Schema::table('users', function (Blueprint $table) {
                // Non-administrators have NULL slots; unique indexes allow multiple NULLs.
                $table->unsignedTinyInteger('admin_slot')->nullable()->storedAs('CASE WHEN is_admin = 1 THEN 1 ELSE NULL END');
                $table->unique('admin_slot', 'users_one_admin');
            });
        } else {
            DB::statement('CREATE UNIQUE INDEX users_one_admin ON users (is_admin) WHERE is_admin = true');
        }
    }

    public function down(): void
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
    }
};
