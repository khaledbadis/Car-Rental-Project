<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicle_categories', function (Blueprint $t) {
            $t->id();
            $t->string('name')->unique();
            $t->timestamps();
        });
        Schema::create('vehicles', function (Blueprint $t) {
            $t->id();
            $t->string('registration')->unique();
            $t->string('make');
            $t->string('model');
            $t->unsignedSmallInteger('year');
            $t->foreignId('category_id')->constrained('vehicle_categories')->restrictOnDelete();
            $t->string('fuel_type');
            $t->string('transmission');
            $t->unsignedInteger('mileage_km')->default(0);
            $t->decimal('daily_rate', 12, 2);
            $t->decimal('weekly_rate', 12, 2)->nullable();
            $t->decimal('monthly_rate', 12, 2)->nullable();
            $t->date('entered_service_at')->nullable();
            $t->text('notes')->nullable();
            $t->timestampTz('archived_at')->nullable();
            $t->unsignedInteger('version')->default(1);
            $t->timestampsTz();
        });
        DB::statement('ALTER TABLE vehicles ADD CONSTRAINT vehicle_positive_values CHECK (mileage_km >= 0 AND daily_rate >= 0 AND (weekly_rate IS NULL OR weekly_rate >= 0) AND (monthly_rate IS NULL OR monthly_rate >= 0))');
        Schema::create('customers', function (Blueprint $t) {
            $t->id();
            $t->string('type');
            $t->string('name');
            $t->string('phone')->nullable();
            $t->string('phone_normalized')->nullable()->index();
            $t->text('address')->nullable();
            $t->date('birth_date')->nullable();
            $t->string('identity_type')->nullable();
            $t->string('identity_number')->nullable();
            $t->date('identity_issue_date')->nullable();
            $t->date('identity_expiry_date')->nullable();
            $t->string('licence_number')->nullable();
            $t->date('licence_issue_date')->nullable();
            $t->date('licence_expiry_date')->nullable();
            $t->string('licence_country')->nullable();
            $t->string('contact_person')->nullable();
            $t->string('tax_identifier')->nullable();
            $t->foreignId('driver_id')->nullable()->constrained('customers')->restrictOnDelete();
            $t->timestampTz('archived_at')->nullable();
            $t->unsignedInteger('version')->default(1);
            $t->timestampsTz();
            $t->unique(['identity_type', 'identity_number']);
            $t->unique(['licence_country', 'licence_number']);
        });
        DB::statement("ALTER TABLE customers ADD CONSTRAINT customer_type CHECK (type IN ('individual','company'))");
        Schema::create('customer_notes', function (Blueprint $t) {
            $t->id();
            $t->foreignId('customer_id')->constrained()->restrictOnDelete();
            $t->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $t->string('category');
            $t->text('body');
            $t->timestampsTz();
        });
        Schema::create('documents', function (Blueprint $t) {
            $t->id();
            $t->foreignId('vehicle_id')->nullable()->constrained()->restrictOnDelete();
            $t->foreignId('customer_id')->nullable()->constrained()->restrictOnDelete();
            $t->string('type');
            $t->string('original_name');
            $t->string('path');
            $t->string('mime');
            $t->unsignedBigInteger('size');
            $t->date('expires_at')->nullable();
            $t->foreignId('uploaded_by')->constrained('users')->restrictOnDelete();
            $t->timestampTz('removed_at')->nullable();
            $t->timestampsTz();
        });
        DB::statement('ALTER TABLE documents ADD CONSTRAINT one_document_owner CHECK ((vehicle_id IS NOT NULL)::int + (customer_id IS NOT NULL)::int = 1)');
    }

    public function down(): void
    {
        Schema::dropIfExists('documents');
        Schema::dropIfExists('customer_notes');
        Schema::dropIfExists('customers');
        Schema::dropIfExists('vehicles');
        Schema::dropIfExists('vehicle_categories');
    }
};
