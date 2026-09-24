<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Range equality on a one-point vehicle-ID range avoids an extension dependency.
        // Emergency operational blocks may overlap; reservation allocations may never overlap.
        DB::statement("ALTER TABLE vehicle_commitments ADD CONSTRAINT reservation_occupancy_exclusion EXCLUDE USING gist (int8range(vehicle_id, vehicle_id, '[]') WITH &&, tstzrange(starts_at, ends_at, '[)') WITH &&) WHERE (kind = 'reservation' AND released_at IS NULL)");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE vehicle_commitments DROP CONSTRAINT reservation_occupancy_exclusion');
    }
};
