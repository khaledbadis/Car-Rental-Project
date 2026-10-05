<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expenses', function (Blueprint $t) {
            $t->id();
            $t->foreignId('vehicle_id')->constrained()->restrictOnDelete();
            $t->foreignId('maintenance_record_id')->nullable()->unique()->constrained()->restrictOnDelete();
            $t->foreignId('reverses_id')->nullable()->unique()->constrained('expenses')->restrictOnDelete();
            $t->string('category');
            $t->bigInteger('amount_cents');
            $t->date('incurred_on');
            $t->text('description');
            $t->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $t->uuid('idempotency_key')->nullable()->unique();
            $t->string('request_hash', 64)->nullable();
            $t->timestampsTz();
            $t->index(['vehicle_id', 'incurred_on']);
        });
        DB::statement("ALTER TABLE expenses ADD CONSTRAINT expense_amount CHECK ((reverses_id IS NULL AND amount_cents >= 0) OR (reverses_id IS NOT NULL AND amount_cents <= 0)), ADD CONSTRAINT expense_category CHECK (category IN ('maintenance','spare_parts','cleaning','towing','insurance','other'))");
        DB::statement("INSERT INTO expenses (vehicle_id,maintenance_record_id,category,amount_cents,incurred_on,description,created_by,created_at,updated_at) SELECT vehicle_id,id,'maintenance',cost_cents,serviced_on,COALESCE(notes,service_type),created_by,created_at,updated_at FROM maintenance_records");
        Schema::create('expense_attachments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('expense_id')->constrained()->restrictOnDelete();
            $t->string('path');
            $t->string('mime');
            $t->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $t->timestampsTz();
        });
        DB::statement('CREATE TRIGGER expenses_immutable BEFORE UPDATE OR DELETE ON expenses FOR EACH ROW EXECUTE FUNCTION prevent_audit_mutation()');
    }

    public function down(): void
    {
        Schema::dropIfExists('expense_attachments');
        Schema::dropIfExists('expenses');
    }
};
