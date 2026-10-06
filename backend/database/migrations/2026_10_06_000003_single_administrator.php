<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE UNIQUE INDEX users_one_admin ON users (is_admin) WHERE is_admin = true');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS users_one_admin');
    }
};
