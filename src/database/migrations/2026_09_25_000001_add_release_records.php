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
            $t->string('username', 80)->nullable()->unique();
            $t->string('phone', 50)->nullable();
            $t->text('address')->nullable();
            $t->string('avatar_path')->nullable();
        });
        // ID-based names are deterministic and cannot collide with mutable names/emails.
        DB::statement("UPDATE users SET username = 'staff-' || id::text");
        DB::statement('ALTER TABLE users ALTER COLUMN username SET NOT NULL');
        DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION assign_staff_username() RETURNS trigger AS $$
BEGIN
 IF TG_OP = 'INSERT' THEN
   NEW.username := COALESCE(NEW.username, 'staff-' || NEW.id::text);
 ELSIF NEW.username IS DISTINCT FROM OLD.username THEN
   RAISE EXCEPTION 'Username is immutable';
 END IF;
 RETURN NEW;
END;
$$ LANGUAGE plpgsql;
CREATE TRIGGER staff_username BEFORE INSERT OR UPDATE ON users FOR EACH ROW EXECUTE FUNCTION assign_staff_username();
SQL);
        Schema::table('audit_events', fn (Blueprint $t) => $t->string('actor_username', 80)->nullable());
        // Earlier immutable events remain untouched; stable user ID still resolves their username.
        Schema::create('receipt_sequences', function (Blueprint $t) {
            $t->string('series')->primary();
            $t->unsignedBigInteger('last_number')->default(0);
        });
        Schema::create('receipts', function (Blueprint $t) {
            $t->id();
            $t->foreignId('ledger_entry_id')->unique()->constrained()->restrictOnDelete();
            $t->string('number')->unique();
            $t->jsonb('snapshot');
            $t->timestampTz('created_at');
        });
        DB::statement('CREATE TRIGGER receipts_immutable BEFORE UPDATE OR DELETE ON receipts FOR EACH ROW EXECUTE FUNCTION prevent_audit_mutation()');
    }

    public function down(): void
    {
        Schema::dropIfExists('receipts');
        Schema::dropIfExists('receipt_sequences');
        Schema::table('audit_events', fn (Blueprint $t) => $t->dropColumn('actor_username'));
        DB::statement('DROP TRIGGER staff_username ON users');
        DB::statement('DROP FUNCTION assign_staff_username()');
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn(['username', 'phone', 'address', 'avatar_path']));
    }
};
