<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->string('role')->default('agent');
            $t->boolean('active')->default(true);
            $t->string('locale')->default('fr');
            $t->string('theme')->default('system');
            $t->boolean('must_change_password')->default(false);
        });
        DB::statement("ALTER TABLE users ADD CONSTRAINT users_role_check CHECK (role IN ('manager','agent','finance'))");
        Schema::create('audit_events', function (Blueprint $t) {
            $t->id();
            $t->foreignId('actor_id')->nullable()->constrained('users')->restrictOnDelete();
            $t->string('action');
            $t->string('subject_type');
            $t->string('subject_id');
            $t->jsonb('before')->nullable();
            $t->jsonb('after')->nullable();
            $t->text('reason')->nullable();
            $t->timestampTz('created_at')->useCurrent();
        });
        DB::unprepared("CREATE OR REPLACE FUNCTION prevent_audit_mutation() RETURNS trigger LANGUAGE plpgsql AS $$ BEGIN RAISE EXCEPTION 'Audit events are append-only'; END; $$; CREATE TRIGGER audit_immutable BEFORE UPDATE OR DELETE ON audit_events FOR EACH ROW EXECUTE FUNCTION prevent_audit_mutation();");
        Schema::create('agency_settings', function (Blueprint $t) {
            $t->id();
            $t->unsignedInteger('preparation_minutes')->default(120);
            $t->unsignedInteger('late_grace_minutes')->default(60);
            $t->unsignedInteger('no_show_minutes')->default(120);
            $t->unsignedInteger('upload_limit_mb')->default(10);
            $t->timestamps();
        });
        DB::table('agency_settings')->insert(['id' => 1, 'created_at' => now(), 'updated_at' => now()]);
        Schema::create('personal_access_tokens', function (Blueprint $t) {
            $t->id();
            $t->morphs('tokenable');
            $t->text('name');
            $t->string('token', 64)->unique();
            $t->text('abilities')->nullable();
            $t->timestamp('last_used_at')->nullable();
            $t->timestamp('expires_at')->nullable()->index();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('personal_access_tokens');
        Schema::dropIfExists('agency_settings');
        Schema::dropIfExists('audit_events');
        DB::statement('DROP FUNCTION IF EXISTS prevent_audit_mutation()');
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn(['role', 'active', 'locale', 'theme', 'must_change_password']));
    }
};
