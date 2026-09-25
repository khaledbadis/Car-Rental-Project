<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rentals', function (Blueprint $t) {
            $t->id();
            $t->foreignId('reservation_id')->nullable()->unique()->constrained()->restrictOnDelete();
            $t->foreignId('customer_id')->constrained()->restrictOnDelete();
            $t->foreignId('driver_id')->nullable()->constrained('customers')->restrictOnDelete();
            $t->foreignId('vehicle_id')->constrained()->restrictOnDelete();
            $t->string('status')->default('draft');
            $t->timestampTz('starts_at');
            $t->timestampTz('ends_at');
            $t->timestampTz('handed_over_at')->nullable();
            $t->timestampTz('returned_at')->nullable();
            $t->unsignedInteger('preparation_minutes');
            $t->jsonb('pricing');
            $t->jsonb('snapshot');
            $t->unsignedInteger('version')->default(1);
            $t->unsignedInteger('contract_version')->default(1);
            $t->unsignedInteger('mileage_allowance')->nullable();
            $t->text('terms')->nullable();
            $t->text('cancellation_reason')->nullable();
            $t->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $t->timestampsTz();
            $t->index(['status', 'ends_at']);
        });
        Schema::table('vehicle_commitments', function (Blueprint $t) {
            $t->foreignId('rental_id')->nullable()->unique()->constrained()->restrictOnDelete();
        });
        DB::statement('ALTER TABLE vehicle_commitments DROP CONSTRAINT commitment_kind, DROP CONSTRAINT commitment_owner, DROP CONSTRAINT reservation_occupancy_exclusion');
        DB::statement("ALTER TABLE vehicle_commitments ADD CONSTRAINT commitment_kind CHECK (kind IN ('reservation','rental','maintenance','cleaning','inspection','preparation','temporary','administrative')), ADD CONSTRAINT commitment_owner CHECK ((kind='reservation' AND reservation_id IS NOT NULL AND rental_id IS NULL AND ends_at IS NOT NULL) OR (kind='rental' AND rental_id IS NOT NULL AND reservation_id IS NULL AND ends_at IS NOT NULL) OR (kind NOT IN ('reservation','rental') AND rental_id IS NULL AND reservation_id IS NULL)), ADD CONSTRAINT allocation_exclusion EXCLUDE USING gist (int8range(vehicle_id,vehicle_id,'[]') WITH &&, tstzrange(starts_at,ends_at,'[)') WITH &&) WHERE (kind IN ('reservation','rental') AND released_at IS NULL)");
        DB::statement("ALTER TABLE rentals ADD CONSTRAINT rental_interval CHECK (ends_at>starts_at), ADD CONSTRAINT rental_status CHECK (status IN ('draft','active','returned','cancelled'))");
        Schema::create('rental_versions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('rental_id')->constrained()->restrictOnDelete();
            $t->unsignedInteger('number');
            $t->jsonb('snapshot');
            $t->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $t->text('reason');
            $t->timestampTz('created_at');
            $t->unique(['rental_id', 'number']);
        });
        Schema::create('inspections', function (Blueprint $t) {
            $t->id();
            $t->foreignId('rental_id')->constrained()->restrictOnDelete();
            $t->string('kind');
            $t->timestampTz('occurred_at');
            $t->unsignedInteger('mileage_km');
            $t->unsignedInteger('fuel_percent');
            $t->text('condition_notes');
            $t->text('correction_reason')->nullable();
            $t->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $t->timestampTz('created_at');
            $t->unique(['rental_id', 'kind']);
        });
        Schema::create('inspection_photos', function (Blueprint $t) {
            $t->id();
            $t->foreignId('inspection_id')->constrained()->restrictOnDelete();
            $t->string('path');
            $t->string('mime');
            $t->unsignedInteger('size');
            $t->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $t->timestampTz('created_at');
        });
        Schema::create('financial_accounts', function (Blueprint $t) {
            $t->id();
            $t->foreignId('customer_id')->constrained()->restrictOnDelete();
            $t->foreignId('reservation_id')->nullable()->unique()->constrained()->restrictOnDelete();
            $t->foreignId('rental_id')->nullable()->unique()->constrained()->restrictOnDelete();
            $t->timestampsTz();
        });
        Schema::create('ledger_entries', function (Blueprint $t) {
            $t->id();
            $t->foreignId('financial_account_id')->constrained()->restrictOnDelete();
            $t->string('kind');
            $t->string('category')->nullable();
            $t->bigInteger('amount_cents');
            $t->bigInteger('charge_delta')->default(0);
            $t->bigInteger('payment_delta')->default(0);
            $t->bigInteger('deposit_delta')->default(0);
            $t->foreignId('reverses_id')->nullable()->unique()->constrained('ledger_entries')->restrictOnDelete();
            $t->string('method')->nullable();
            $t->string('reference')->nullable();
            $t->text('reason');
            $t->timestampTz('effective_at');
            $t->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $t->timestampTz('created_at');
        });
        Schema::create('operation_keys', function (Blueprint $t) {
            $t->id();
            $t->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $t->uuid('key');
            $t->string('operation');
            $t->string('fingerprint', 64);
            $t->unsignedBigInteger('result_id');
            $t->timestampTz('created_at');
            $t->unique(['actor_id', 'key']);
        });
        foreach (['ledger_entries', 'rental_versions', 'inspections', 'inspection_photos'] as $table) {
            DB::statement("CREATE TRIGGER {$table}_immutable BEFORE UPDATE OR DELETE ON {$table} FOR EACH ROW EXECUTE FUNCTION prevent_audit_mutation()");
        }
        DB::statement("ALTER TABLE inspections ADD CONSTRAINT inspection_kind CHECK(kind IN ('handover','return')), ADD CONSTRAINT inspection_values CHECK(mileage_km >= 0 AND fuel_percent BETWEEN 0 AND 100)");
    }

    public function down(): void
    {
        foreach (['operation_keys', 'ledger_entries', 'financial_accounts', 'inspection_photos', 'inspections', 'rental_versions'] as $table) {
            Schema::dropIfExists($table);
        }
        DB::statement('ALTER TABLE vehicle_commitments DROP CONSTRAINT allocation_exclusion, DROP CONSTRAINT commitment_owner, DROP CONSTRAINT commitment_kind');
        DB::table('vehicle_commitments')->where('kind', 'rental')->update(['kind' => 'temporary']);
        Schema::table('vehicle_commitments', fn (Blueprint $t) => $t->dropConstrainedForeignId('rental_id'));
        Schema::dropIfExists('rentals');
        DB::statement("ALTER TABLE vehicle_commitments ADD CONSTRAINT commitment_kind CHECK(kind IN ('reservation','maintenance','cleaning','inspection','preparation','temporary','administrative')), ADD CONSTRAINT commitment_owner CHECK((kind='reservation' AND reservation_id IS NOT NULL AND ends_at IS NOT NULL) OR (kind<>'reservation' AND reservation_id IS NULL)), ADD CONSTRAINT reservation_occupancy_exclusion EXCLUDE USING gist (int8range(vehicle_id,vehicle_id,'[]') WITH &&, tstzrange(starts_at,ends_at,'[)') WITH &&) WHERE (kind='reservation' AND released_at IS NULL)");
    }
};
