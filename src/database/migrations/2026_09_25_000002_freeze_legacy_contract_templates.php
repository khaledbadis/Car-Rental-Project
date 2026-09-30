<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // PostgreSQL adds a frozen default to old immutable rows without rewriting their historical snapshot.
        $template = DB::getPdo()->quote(json_encode(config('contract'), JSON_THROW_ON_ERROR));
        DB::statement("ALTER TABLE rental_versions ADD COLUMN legacy_template jsonb NOT NULL DEFAULT {$template}::jsonb");
    }

    public function down(): void
    {
        Schema::table('rental_versions', fn (Blueprint $t) => $t->dropColumn('legacy_template'));
    }
};
