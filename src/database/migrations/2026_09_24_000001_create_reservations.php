<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reservations', function (Blueprint $t) {
            $t->id();
            $t->foreignId('customer_id')->constrained()->restrictOnDelete();
            $t->foreignId('category_id')->nullable()->constrained('vehicle_categories')->restrictOnDelete();
            $t->foreignId('vehicle_id')->nullable()->constrained()->restrictOnDelete();
            $t->string('status')->default('tentative');
            $t->timestampTz('starts_at');
            $t->timestampTz('ends_at');
            $t->unsignedInteger('preparation_minutes')->default(0);
            $t->text('notes')->nullable();
            $t->text('document_override_reason')->nullable();
            $t->jsonb('document_issues')->nullable();
            $t->unsignedInteger('version')->default(1);
            $t->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $t->text('cancellation_reason')->nullable();
            $t->timestampTz('cancelled_at')->nullable();
            $t->timestampsTz();
            $t->index(['status', 'starts_at']);
        });
        Schema::create('vehicle_commitments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('vehicle_id')->constrained()->restrictOnDelete();
            $t->foreignId('reservation_id')->nullable()->unique()->constrained()->restrictOnDelete();
            $t->string('kind');
            $t->timestampTz('starts_at');
            $t->timestampTz('ends_at')->nullable();
            $t->boolean('emergency')->default(false);
            $t->text('reason')->nullable();
            $t->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $t->timestampTz('released_at')->nullable();
            $t->text('release_reason')->nullable();
            $t->unsignedInteger('version')->default(1);
            $t->timestampsTz();
            $t->index(['vehicle_id', 'starts_at', 'ends_at']);
        });
        DB::statement("ALTER TABLE reservations ADD CONSTRAINT reservation_interval CHECK (ends_at > starts_at), ADD CONSTRAINT reservation_status CHECK (status IN ('tentative','confirmed','cancelled','converted')), ADD CONSTRAINT confirmed_vehicle CHECK (status NOT IN ('confirmed','converted') OR vehicle_id IS NOT NULL)");
        DB::statement("ALTER TABLE vehicle_commitments ADD CONSTRAINT commitment_interval CHECK (ends_at IS NULL OR ends_at > starts_at), ADD CONSTRAINT commitment_kind CHECK (kind IN ('reservation','maintenance','cleaning','inspection','preparation','temporary','administrative')), ADD CONSTRAINT commitment_owner CHECK ((kind = 'reservation' AND reservation_id IS NOT NULL AND ends_at IS NOT NULL) OR (kind <> 'reservation' AND reservation_id IS NULL))");
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicle_commitments');
        Schema::dropIfExists('reservations');
    }
};
