<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agency_settings', function (Blueprint $t) {
            $t->unsignedInteger('maintenance_warning_days')->default(30);
            $t->unsignedInteger('maintenance_warning_km')->default(500);
        });
        Schema::create('maintenance_records', function (Blueprint $t) {
            $t->id();
            $t->foreignId('vehicle_id')->constrained()->restrictOnDelete();
            $t->foreignId('vehicle_commitment_id')->nullable()->constrained()->restrictOnDelete();
            $t->string('service_type', 40);
            $t->date('serviced_on');
            $t->unsignedInteger('mileage_km');
            $t->bigInteger('cost_cents');
            $t->text('notes')->nullable();
            $t->date('next_due_on')->nullable();
            $t->unsignedInteger('next_due_km')->nullable();
            $t->uuid('idempotency_key')->unique();
            $t->string('request_hash', 64);
            $t->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $t->timestampsTz();
            $t->index(['vehicle_id', 'service_type', 'serviced_on']);
        });
        DB::statement('ALTER TABLE maintenance_records ADD CONSTRAINT maintenance_values CHECK (cost_cents >= 0 AND mileage_km >= 0 AND (next_due_km IS NULL OR next_due_km > mileage_km) AND (next_due_on IS NULL OR next_due_on > serviced_on))');
        Schema::create('maintenance_attachments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('maintenance_record_id')->constrained()->restrictOnDelete();
            $t->string('path');
            $t->string('mime');
            $t->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $t->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_attachments');
        Schema::dropIfExists('maintenance_records');
        Schema::table('agency_settings', fn (Blueprint $t) => $t->dropColumn(['maintenance_warning_days', 'maintenance_warning_km']));
    }
};
