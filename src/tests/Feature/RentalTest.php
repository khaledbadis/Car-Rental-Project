<?php

namespace Tests\Feature;

use App\Livewire\LedgerPanel;
use App\Livewire\RentalProfile;
use App\Models\Customer;
use App\Models\Document;
use App\Models\Inspection;
use App\Models\Rental;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleCategory;
use App\Models\VehicleCommitment;
use App\Modules\Rentals\Actions;
use App\Modules\Rentals\Ledger;
use App\Modules\Rentals\Pricing;
use App\Modules\Reservations\Actions as Reservations;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Livewire\Livewire;
use Tests\TestCase;

class RentalTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    private User $agent;

    private User $finance;

    private Vehicle $car;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now('Africa/Algiers')->setDate(2027, 1, 10)->setTime(10, 0));
        $this->manager = User::factory()->create(['role' => 'manager', 'active' => true]);
        $this->agent = User::factory()->create(['role' => 'agent', 'active' => true]);
        $this->finance = User::factory()->create(['role' => 'finance', 'active' => true]);
        $this->car = Vehicle::create(['registration' => 'RENT01', 'make' => 'Renault', 'model' => 'Clio', 'year' => 2024, 'category_id' => VehicleCategory::create(['name' => 'Rental economy'])->id, 'fuel_type' => 'petrol', 'transmission' => 'automatic', 'mileage_km' => 100, 'daily_rate' => '8000', 'weekly_rate' => '50000', 'monthly_rate' => '180000']);
        $this->customer = Customer::create(['type' => 'individual', 'name' => 'Rental Test', 'birth_date' => '1990-01-01', 'phone' => '0555000000', 'address' => 'Fictional address', 'identity_type' => 'passport', 'identity_number' => 'TEST123', 'identity_issue_date' => '2020-01-01', 'identity_expiry_date' => '2030-12-31', 'licence_number' => 'LIC123', 'licence_issue_date' => '2010-01-01', 'licence_expiry_date' => '2030-12-31', 'licence_country' => 'DZ']);
        foreach (['insurance', 'inspection'] as $type) {
            $this->doc(['vehicle_id' => $this->car->id, 'type' => $type]);
        }foreach (['identity', 'licence'] as $type) {
            $this->doc(['customer_id' => $this->customer->id, 'type' => $type]);
        }$this->actingAs($this->manager);
    }

    private function doc(array $v): void
    {
        Document::create($v + ['path' => 'test', 'original_name' => 'test.pdf', 'mime' => 'application/pdf', 'size' => 10, 'expires_at' => '2030-12-31', 'uploaded_by' => $this->manager->id]);
    }

    private function input(array $v = []): array
    {
        return array_replace(['customer_id' => $this->customer->id, 'vehicle_id' => $this->car->id, 'starts_at' => now('Africa/Algiers')->toIso8601String(), 'ends_at' => now('Africa/Algiers')->addDay()->toIso8601String(), 'basis' => 'daily', 'discount_type' => 'none', 'pricing_reason' => 'Test agreed price', 'idempotency_key' => (string) Str::uuid()], $v);
    }

    private function draft(array $v = []): Rental
    {
        return app(Actions::class)->create($this->manager, $this->input($v));
    }

    private function inspection(Rental $r, array $v = []): array
    {
        return array_replace(['version' => $r->fresh()->version, 'occurred_at' => now('Africa/Algiers')->toIso8601String(), 'mileage_km' => 100, 'fuel_percent' => 75, 'condition_confirmed' => true, 'condition_notes' => 'Checked, no new damage', 'idempotency_key' => (string) Str::uuid()], $v);
    }

    private function entry(string $kind, string $amount, array $v = []): array
    {
        return array_replace(['kind' => $kind, 'amount' => $amount, 'method' => 'cash', 'reason' => 'Test transaction', 'effective_at' => now('Africa/Algiers')->toIso8601String(), 'idempotency_key' => (string) Str::uuid()], $v);
    }

    public function test_complete_advance_handover_deposit_damage_and_return_workflow(): void
    {
        $booking = app(Reservations::class)->save($this->manager, $this->input(['status' => 'confirmed']));
        $l = app(Ledger::class);
        $advance = $l->post($this->agent, 'reservation', $booking->id, $this->entry('payment', '3000'));
        $r = $this->postJson('/api/v1/rentals', $this->input(['reservation_id' => $booking->id, 'reservation_version' => 1]))->assertCreated()->json('data');
        $r = Rental::findOrFail($r['id']);
        $this->assertSame('converted', $booking->fresh()->status);
        $this->assertSame($advance->financial_account_id, $r->account->id);
        $this->assertDatabaseCount('vehicle_commitments', 1);
        $this->assertNull(VehicleCommitment::first()->reservation_id);
        $this->actingAs($this->agent)->postJson('/api/v1/rentals/'.$r->id.'/handover', $this->inspection($r))->assertOk()->assertJsonPath('data.status', 'active');
        $l->post($this->agent, 'rental', $r->id, $this->entry('payment', '5000'));
        $l->post($this->agent, 'rental', $r->id, $this->entry('deposit_received', '30000'));
        $l->post($this->agent, 'rental', $r->id, $this->entry('charge', '5000', ['category' => 'damage']));
        $l->post($this->finance, 'rental', $r->id, $this->entry('deposit_applied', '5000'));
        $l->post($this->finance, 'rental', $r->id, $this->entry('deposit_refunded', '25000'));
        $this->travelTo(now()->addDay());
        $this->postJson('/api/v1/rentals/'.$r->id.'/return', $this->inspection($r, ['mileage_km' => 200, 'fuel_percent' => 30]))->assertOk()->assertJsonPath('data.status', 'returned');
        $t = $l->totals($r->account);
        $this->assertSame(1300000, $t['charges_cents']);
        $this->assertSame(1300000, $t['payments_cents']);
        $this->assertSame(0, $t['outstanding_cents']);
        $this->assertSame(0, $t['held_cents']);
        $this->assertSame(800000, $t['cash_payments_cents']);
        $this->assertDatabaseCount('inspections', 2);
        $this->assertSame(200, $this->car->fresh()->mileage_km);
    }

    public function test_driver_expiry_cannot_be_overridden_even_by_manager(): void
    {
        $r = $this->draft();
        $this->customer->update(['licence_expiry_date' => '2027-01-09']);
        $this->postJson('/api/v1/rentals/'.$r->id.'/handover', $this->inspection($r, ['document_override_reason' => 'Manager exception']))->assertConflict()->assertJsonPath('code', 'driver_expired');
        $this->assertDatabaseCount('inspections', 0);
        $this->customer->update(['licence_expiry_date' => '2030-01-01', 'identity_expiry_date' => '2027-01-09']);
        $this->postJson('/api/v1/rentals/'.$r->id.'/handover', $this->inspection($r))->assertConflict()->assertJsonPath('code', 'driver_expired');
    }

    public function test_missing_driver_details_and_scans_prevent_handover(): void
    {
        $r = $this->draft();
        $this->customer->update(['address' => null]);
        Document::where('customer_id', $this->customer->id)->where('type', 'licence')->update(['removed_at' => now()]);
        $this->postJson('/api/v1/rentals/'.$r->id.'/handover', $this->inspection($r))->assertConflict()->assertJsonPath('code', 'driver_incomplete')->assertJsonPath('details.missing', ['address', 'licence_scan']);
    }

    public function test_company_requires_actual_driver_and_company_details(): void
    {
        $company = Customer::create(['type' => 'company', 'name' => 'Company', 'driver_id' => $this->customer->id, 'phone' => '0555000001', 'address' => 'Demo']);
        $r = $this->draft(['customer_id' => $company->id]);
        $this->postJson('/api/v1/rentals/'.$r->id.'/handover', $this->inspection($r))->assertConflict()->assertJsonPath('code', 'driver_incomplete');
        $company->update(['contact_person' => 'Contact']);
        $this->postJson('/api/v1/rentals/'.$r->id.'/handover', $this->inspection($r))->assertOk();
    }

    public function test_company_driver_can_be_assigned_at_handover_and_snapshot_is_preserved(): void
    {
        $company = Customer::create(['type' => 'company', 'name' => 'Company', 'phone' => '0555000001', 'address' => 'Demo', 'contact_person' => 'Contact']);
        $r = $this->draft(['customer_id' => $company->id]);
        $this->postJson('/api/v1/rentals/'.$r->id.'/handover', $this->inspection($r, ['driver_id' => $this->customer->id]))->assertOk()->assertJsonPath('data.driver_id', $this->customer->id);
        $this->customer->update(['name' => 'Changed later']);
        $this->assertSame('Rental Test', $r->fresh()->snapshot['driver']['name']);
        $this->assertSame(2, $r->fresh()->contract_version);
    }

    public function test_discount_requires_reason_and_ten_percent_rounds_to_cents(): void
    {
        $this->postJson('/api/v1/rentals', $this->input(['discount_type' => 'percent', 'discount_value' => '10', 'pricing_reason' => '']))->assertUnprocessable()->assertJsonValidationErrors('pricing_reason');
        $this->car->update(['daily_rate' => '0.05']);
        $this->actingAs($this->agent)->postJson('/api/v1/rentals', $this->input(['discount_type' => 'percent', 'discount_value' => '10']))->assertCreated()->assertJsonPath('data.pricing.discount_cents', 1)->assertJsonPath('data.pricing.total_cents', 4);
    }

    public function test_reservation_customer_cannot_change_after_advance_is_posted(): void
    {
        $booking = app(Reservations::class)->save($this->manager, $this->input(['status' => 'confirmed']));
        app(Ledger::class)->post($this->agent, 'reservation', $booking->id, $this->entry('payment', '1000'));
        $other = Customer::create(['type' => 'individual', 'name' => 'Other customer']);
        $this->putJson('/api/v1/reservations/'.$booking->id, $this->input(['status' => 'confirmed', 'version' => 1, 'reason' => 'Reassign', 'customer_id' => $other->id]))->assertConflict()->assertJsonPath('code', 'customer_locked');
        $this->assertSame($this->customer->id, $booking->fresh()->customer_id);
    }

    public function test_stale_handover_and_scoped_api_permissions_are_enforced(): void
    {
        $r = $this->draft();
        $this->postJson('/api/v1/rentals/'.$r->id.'/handover', $this->inspection($r, ['version' => 99]))->assertConflict();
        $this->assertSame('draft', $r->fresh()->status);
        Sanctum::actingAs($this->manager, ['rentals.view']);
        $this->getJson('/api/v1/rentals/'.$r->id)->assertOk();
        $this->postJson('/api/v1/rentals/'.$r->id.'/handover', $this->inspection($r))->assertForbidden();
        $this->postJson('/api/v1/rentals/'.$r->id.'/ledger', $this->entry('payment', '1000'))->assertForbidden();
    }

    public function test_agent_discount_limit_and_additional_charges_not_discounted(): void
    {
        $this->actingAs($this->agent);
        $this->postJson('/api/v1/rentals', $this->input(['discount_type' => 'percent', 'discount_value' => '11']))->assertConflict()->assertJsonPath('code', 'discount_limit');
        $r = $this->postJson('/api/v1/rentals', $this->input(['discount_type' => 'percent', 'discount_value' => '10']))->assertCreated()->assertJsonPath('data.pricing.total_cents', 720000)->json('data');
        $this->postJson('/api/v1/rentals/'.$r['id'].'/ledger', $this->entry('charge', '1000', ['category' => 'fuel']))->assertCreated();
        $this->getJson('/api/v1/rentals/'.$r['id'].'/ledger')->assertJsonPath('totals.charges_cents', 820000);
    }

    public function test_fixed_discount_limit_and_manager_negotiated_price(): void
    {
        $this->actingAs($this->agent);
        $this->postJson('/api/v1/rentals', $this->input(['discount_type' => 'fixed', 'discount_value' => '800.01']))->assertConflict();
        $this->postJson('/api/v1/rentals', $this->input(['negotiated_total' => '5000']))->assertForbidden();
        $this->actingAs($this->manager)->postJson('/api/v1/rentals', $this->input(['negotiated_total' => '5000', 'discount_type' => 'fixed', 'discount_value' => '500']))->assertCreated()->assertJsonPath('data.pricing.total_cents', 450000);
    }

    public function test_daily_ceiling_and_weekly_whole_periods(): void
    {
        $p = app(Pricing::class);
        $start = CarbonImmutable::now();
        foreach ([5 => 800000, 24 => 800000, 28 => 1600000] as $hours => $total) {
            $this->assertSame($total, $p->quote($this->agent, $this->car, $start, $start->addHours($hours), ['basis' => 'daily', 'discount_type' => 'none'])['total_cents']);
        }
        $this->postJson('/api/v1/rentals', $this->input(['basis' => 'weekly', 'ends_at' => now('Africa/Algiers')->addDays(8)->toIso8601String()]))->assertConflict()->assertJsonPath('code', 'whole_period');
        $this->postJson('/api/v1/rentals', $this->input(['basis' => 'weekly', 'ends_at' => now('Africa/Algiers')->addDays(7)->toIso8601String()]))->assertCreated()->assertJsonPath('data.pricing.total_cents', 5000000);
    }

    public function test_overdue_vehicle_stays_unavailable_and_flags_future_reservation(): void
    {
        $r = $this->draft();
        app(Actions::class)->inspect($this->manager, $r->id, 'handover', $this->inspection($r));
        $booking = app(Reservations::class)->save($this->manager, $this->input(['status' => 'confirmed', 'starts_at' => $r->ends_at->addHours(3)->toIso8601String(), 'ends_at' => $r->ends_at->addDays(2)->toIso8601String()]));
        $this->travelTo($r->ends_at->addHours(1));
        $this->getJson('/api/v1/reservations/'.$booking->id)->assertJsonCount(1, 'conflicts')->assertJsonPath('conflicts.0.physical_overdue', true);
        $this->postJson('/api/v1/reservations', $this->input(['status' => 'confirmed', 'starts_at' => now()->addDays(5)->toIso8601String(), 'ends_at' => now()->addDays(6)->toIso8601String()]))->assertConflict()->assertJsonPath('code', 'overlap');
    }

    public function test_conflicting_extension_keeps_other_booking_and_agreement_unchanged(): void
    {
        $r = $this->draft();
        $booking = app(Reservations::class)->save($this->manager, $this->input(['status' => 'confirmed', 'starts_at' => $r->ends_at->addHours(2)->toIso8601String(), 'ends_at' => $r->ends_at->addDays(2)->toIso8601String()]));
        $this->postJson('/api/v1/rentals/'.$r->id.'/extend', $this->input(['version' => 1, 'ends_at' => $r->ends_at->addDay()->toIso8601String(), 'reason' => 'Extension']))->assertConflict();
        $this->assertSame(1, $r->fresh()->contract_version);
        $this->assertSame('confirmed', $booking->fresh()->status);
        $this->assertDatabaseCount('ledger_entries', 1);
    }

    public function test_extension_uses_rate_snapshot_and_creates_immutable_version(): void
    {
        $r = $this->draft();
        $this->car->update(['daily_rate' => '12000']);
        $v = $this->input(['version' => 1, 'ends_at' => $r->ends_at->addDay()->toIso8601String(), 'reason' => 'One more day']);
        $this->postJson('/api/v1/rentals/'.$r->id.'/extend', $v)->assertOk()->assertJsonPath('data.pricing.total_cents', 1600000)->assertJsonPath('data.contract_version', 2);
        $this->assertDatabaseCount('rental_versions', 2);
        $this->assertDatabaseCount('ledger_entries', 3);
        $this->assertSame(1600000, app(Ledger::class)->totals($r->account)['charges_cents']);
    }

    public function test_return_allows_debt_and_uses_actual_return_preparation(): void
    {
        $r = $this->draft();
        app(Actions::class)->inspect($this->manager, $r->id, 'handover', $this->inspection($r));
        $this->travelTo(now()->addHours(5));
        $actual = now();
        $this->postJson('/api/v1/rentals/'.$r->id.'/return', $this->inspection($r, ['mileage_km' => 150]))->assertOk();
        $this->assertSame(800000, app(Ledger::class)->totals($r->account)['outstanding_cents']);
        $prep = VehicleCommitment::where('kind', 'preparation')->firstOrFail();
        $this->assertTrue($prep->starts_at->equalTo($actual));
        $this->assertTrue($prep->ends_at->equalTo($actual->copy()->addMinutes(120)));
        $this->getJson('/api/v1/rentals?status=debt')->assertJsonCount(1, 'data');
    }

    public function test_return_mileage_requires_manager_reason_to_decrease(): void
    {
        $r = $this->draft();
        app(Actions::class)->inspect($this->manager, $r->id, 'handover', $this->inspection($r));
        $this->travelTo(now()->addHour());
        $this->actingAs($this->agent)->postJson('/api/v1/rentals/'.$r->id.'/return', $this->inspection($r, ['mileage_km' => 50, 'correction_reason' => 'Odometer replaced']))->assertForbidden();
        $this->actingAs($this->manager)->postJson('/api/v1/rentals/'.$r->id.'/return', $this->inspection($r, ['mileage_km' => 50]))->assertConflict();
        $this->postJson('/api/v1/rentals/'.$r->id.'/return', $this->inspection($r, ['mileage_km' => 50, 'correction_reason' => 'Odometer replaced']))->assertOk();
        $this->assertDatabaseHas('audit_events', ['action' => 'rental.mileage_corrected']);
    }

    public function test_idempotent_creation_finance_and_lifecycle_and_mismatch_rejection(): void
    {
        $input = $this->input();
        $first = $this->postJson('/api/v1/rentals', $input)->assertCreated()->json('data.id');
        $this->postJson('/api/v1/rentals', $input)->assertCreated()->assertJsonPath('data.id', $first);
        $this->postJson('/api/v1/rentals', array_replace($input, ['terms' => 'Different']))->assertConflict()->assertJsonPath('code', 'retry_mismatch');
        $r = Rental::findOrFail($first);
        $payment = $this->entry('payment', '1000');
        $this->postJson('/api/v1/rentals/'.$first.'/ledger', $payment)->assertCreated();
        $this->postJson('/api/v1/rentals/'.$first.'/ledger', $payment)->assertCreated();
        $this->assertDatabaseCount('ledger_entries', 2);
        $handover = $this->inspection($r);
        $this->postJson('/api/v1/rentals/'.$first.'/handover', $handover)->assertOk();
        $this->postJson('/api/v1/rentals/'.$first.'/handover', $handover)->assertOk();
        $this->assertDatabaseCount('inspections', 1);
    }

    public function test_deposit_limits_refunds_and_reversal_dependencies(): void
    {
        $r = $this->draft();
        $l = app(Ledger::class);
        $deposit = $l->post($this->manager, 'rental', $r->id, $this->entry('deposit_received', '10000'));
        $l->post($this->finance, 'rental', $r->id, $this->entry('deposit_applied', '8000'));
        $url = '/api/v1/rentals/'.$r->id.'/ledger';
        $this->postJson($url, $this->entry('deposit_applied', '1'))->assertConflict()->assertJsonPath('code', 'application_limit');
        $this->postJson($url, $this->entry('deposit_refunded', '2000.01'))->assertConflict()->assertJsonPath('code', 'deposit_limit');
        $this->postJson($url, $this->entry('reversal', '0', ['reverses_id' => $deposit->id]))->assertConflict()->assertJsonPath('code', 'dependent_entries');
        $this->postJson($url, $this->entry('refund', '1'))->assertConflict()->assertJsonPath('code', 'refund_limit');
        $this->assertSame(200000, $l->totals($r->account)['held_cents']);
    }

    public function test_payment_refund_and_linked_reversal_are_distinct(): void
    {
        $r = $this->draft();
        $l = app(Ledger::class);
        $payment = $l->post($this->agent, 'rental', $r->id, $this->entry('payment', '1000'));
        $refund = $l->post($this->finance, 'rental', $r->id, $this->entry('refund', '400'));
        $this->assertSame(60000, $l->totals($r->account)['payments_cents']);
        $l->post($this->finance, 'rental', $r->id, $this->entry('reversal', '0', ['reverses_id' => $refund->id]));
        $this->assertSame(100000, $l->totals($r->account)['payments_cents']);
        $this->actingAs($this->agent)->postJson('/api/v1/rentals/'.$r->id.'/ledger', $this->entry('refund', '1'))->assertForbidden();
        $this->assertSame(100000, $payment->fresh()->amount_cents);
    }

    public function test_ledger_entries_cannot_be_edited_at_database_level(): void
    {
        $r = $this->draft();
        $this->expectException(QueryException::class);
        DB::table('ledger_entries')->where('financial_account_id', $r->account->id)->update(['amount_cents' => 1]);
    }

    public function test_cancel_draft_releases_vehicle_and_preserves_advance_credit(): void
    {
        $r = $this->draft();
        app(Ledger::class)->post($this->agent, 'rental', $r->id, $this->entry('payment', '1000'));
        $this->postJson('/api/v1/rentals/'.$r->id.'/cancel', ['version' => 1, 'reason' => 'Customer cancelled', 'idempotency_key' => (string) Str::uuid()])->assertOk();
        $this->assertSame(100000, app(Ledger::class)->totals($r->account)['credit_cents']);
        $this->postJson('/api/v1/rentals', $this->input())->assertCreated();
    }

    public function test_private_photo_upload_and_finance_access(): void
    {
        Storage::fake('local');
        $r = $this->draft();
        app(Actions::class)->inspect($this->manager, $r->id, 'handover', $this->inspection($r));
        $id = Inspection::first()->id;
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII=');
        $file = UploadedFile::fake()->createWithContent('photo.png', $png);
        $p = $this->post('/api/v1/inspections/'.$id.'/photos', ['file' => $file, 'idempotency_key' => (string) Str::uuid()], ['Accept' => 'application/json'])->assertCreated()->assertJsonMissingPath('data.path')->json('data.id');
        $this->get('/inspection-photos/'.$p)->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->actingAs($this->finance)->get('/inspection-photos/'.$p)->assertForbidden();
        $this->postJson('/api/v1/rentals/'.$r->id.'/handover', $this->inspection($r))->assertForbidden();
    }

    public function test_localized_screens_and_livewire_blank_optional_fields(): void
    {
        $r = $this->draft();
        foreach (['fr', 'ar', 'en'] as $locale) {
            $this->manager->update(['locale' => $locale]);
            foreach (['/rentals', '/rentals/'.$r->id, '/customers/'.$this->customer->id] as $url) {
                $this->get($url)->assertOk();
            }
        }
        Livewire::test(RentalProfile::class, ['id' => $r->id])->set('form.fuel_percent', 80)->set('form.condition_confirmed', true)->set('form.condition_notes', 'Checked')->call('inspect', 'handover')->assertHasNoErrors();
        $this->assertSame('active', $r->fresh()->status);
        Livewire::test(LedgerPanel::class, ['owner' => 'rental', 'ownerId' => $r->id])->set('form.amount', '1000')->set('form.reason', 'Cash payment')->call('postEntry')->assertHasNoErrors();
        $this->assertSame(100000, app(Ledger::class)->totals($r->account)['payments_cents']);
    }
}
