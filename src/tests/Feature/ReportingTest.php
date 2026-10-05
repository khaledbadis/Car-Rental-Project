<?php

namespace Tests\Feature;

use App\Livewire\Expenses;
use App\Livewire\Reports;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\FinancialAccount;
use App\Models\LedgerEntry;
use App\Models\Rental;
use App\Models\Reservation;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleCategory;
use App\Models\VehicleCommitment;
use App\Modules\Maintenance\Actions as Maintenance;
use App\Modules\Reporting\Report;
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

class ReportingTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    private Vehicle $car;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now('Africa/Algiers')->setDate(2026, 10, 4)->setTime(12, 0));
        $this->manager = User::factory()->create(['role' => 'manager', 'active' => true]);
        $this->actingAs($this->manager);
        $this->car = Vehicle::create(['registration' => 'REPORT01', 'make' => 'Test', 'model' => 'Car', 'year' => 2025, 'category_id' => VehicleCategory::create(['name' => 'Test'])->id, 'fuel_type' => 'petrol', 'transmission' => 'manual', 'mileage_km' => 1000, 'daily_rate' => '1000', 'entered_service_at' => '2026-10-01']);
    }

    private function expense(array $v = []): array
    {
        return array_replace(['vehicle_id' => $this->car->id, 'category' => 'cleaning', 'amount' => '125.50', 'incurred_on' => '2026-10-01', 'description' => 'Test evidence', 'idempotency_key' => (string) Str::uuid()], $v);
    }

    private function report(): array
    {
        return app(Report::class)->read($this->manager, ['from' => '2026-10-01', 'to' => '2026-10-03']);
    }

    private function rental(array $v = []): Rental
    {
        $data = array_replace(['vehicle_id' => $this->car->id, 'customer_id' => Customer::create(['name' => 'Test', 'type' => 'individual'])->id, 'status' => 'returned', 'starts_at' => '2026-10-01T00:00:00+01:00', 'ends_at' => '2026-10-02T00:00:00+01:00', 'handed_over_at' => '2026-10-01T00:00:00+01:00', 'returned_at' => '2026-10-02T00:00:00+01:00', 'preparation_minutes' => 120, 'pricing' => [], 'snapshot' => [], 'created_by' => $this->manager->id], $v);
        foreach (['starts_at', 'ends_at', 'handed_over_at', 'returned_at'] as $field) {
            if ($data[$field]) {
                $data[$field] = CarbonImmutable::parse($data[$field])->utc();
            }
        }

        return Rental::create($data);
    }

    private function block(string $start, ?string $end, string $kind = 'maintenance', ?string $released = null): void
    {
        VehicleCommitment::create(['vehicle_id' => $this->car->id, 'kind' => $kind, 'starts_at' => CarbonImmutable::parse($start)->utc(), 'ends_at' => $end ? CarbonImmutable::parse($end)->utc() : null, 'released_at' => $released ? CarbonImmutable::parse($released)->utc() : null, 'reason' => 'Test', 'created_by' => $this->manager->id]);
    }

    public function test_expense_retry_reversal_and_database_immutability(): void
    {
        $p = $this->expense();
        $id = $this->postJson('/api/v1/expenses', $p)->assertCreated()->assertJsonPath('data.amount_cents', 12550)->json('data.id');
        $this->postJson('/api/v1/expenses', $p)->assertCreated()->assertJsonPath('data.id', $id);
        $this->postJson('/api/v1/expenses', array_replace($p, ['amount' => '1']))->assertConflict();
        $input = ['reason' => 'Correction', 'idempotency_key' => (string) Str::uuid()];
        $this->postJson('/api/v1/expenses/'.$id.'/reverse', $input)->assertCreated()->assertJsonPath('data.amount_cents', -12550);
        $this->postJson('/api/v1/expenses/'.$id.'/reverse', $input)->assertCreated();
        $this->assertSame(0, $this->report()['totals']['expenses']);
        $this->assertDatabaseCount('expenses', 2);
        $this->postJson('/api/v1/expenses/'.$id.'/reverse', ['reason' => 'Again', 'idempotency_key' => (string) Str::uuid()])->assertConflict();
        $this->expectException(QueryException::class);
        DB::table('expenses')->where('id', $id)->update(['amount_cents' => 0]);
    }

    public function test_maintenance_cost_has_one_link_even_on_retry_and_reverses_once(): void
    {
        $p = ['vehicle_id' => $this->car->id, 'service_type' => 'oil', 'serviced_on' => '2026-10-01', 'mileage_km' => 1000, 'cost' => '500.25', 'idempotency_key' => (string) Str::uuid()];
        $a = app(Maintenance::class);
        $r = $a->record($this->manager, $p);
        $a->record($this->manager, $p);
        $this->assertDatabaseCount('expenses', 1);
        $this->assertDatabaseHas('expenses', ['maintenance_record_id' => $r->id, 'amount_cents' => 50025]);
        $data = $this->report();
        $this->assertSame(50025, $data['totals']['expenses']);
        $this->assertSame(50025, $data['vehicles'][0]['maintenance_cost']);
        $this->postJson('/api/v1/expenses', $this->expense(['maintenance_record_id' => $r->id]))->assertUnprocessable();
    }

    public function test_utilization_merges_exclusions_and_uses_actual_hours(): void
    {
        $this->rental();
        $this->block('2026-10-02T00:00:00+01:00', '2026-10-02T12:00:00+01:00');
        $this->block('2026-10-02T06:00:00+01:00', '2026-10-03T00:00:00+01:00', 'administrative');
        $this->block('2026-10-03T00:00:00+01:00', '2026-10-04T00:00:00+01:00', 'cleaning');
        $row = $this->report()['vehicles'][0];
        $this->assertEquals(48, $row['eligible_hours']);
        $this->assertEquals(24, $row['rented_hours']);
        $this->assertEquals(50, $row['utilization']);
        $this->block('2026-10-01T12:00:00+01:00', null, 'maintenance', '2026-10-01T18:00:00+01:00');
        $row = $this->report()['vehicles'][0];
        $this->assertSame('overlap', $row['utilization_status']);
        $this->assertNull($row['utilization']);
    }

    public function test_utilization_zero_unknown_archive_and_active_elapsed_time(): void
    {
        $this->car->update(['entered_service_at' => null]);
        $this->assertNull($this->report()['vehicles'][0]['utilization']);
        $this->car->update(['entered_service_at' => '2026-10-01', 'archived_at' => CarbonImmutable::parse('2026-10-02T00:00:00+01:00')->utc()]);
        $this->assertEquals(24, $this->report()['vehicles'][0]['eligible_hours']);
        $this->block('2026-10-01T00:00:00+01:00', null);
        $this->assertSame('no_eligible_hours', $this->report()['vehicles'][0]['utilization_status']);
    }

    public function test_revenue_corrections_collections_deposits_and_dashboard_reconcile(): void
    {
        $r = $this->rental();
        $account = FinancialAccount::create(['customer_id' => $r->customer_id, 'rental_id' => $r->id]);
        $base = ['financial_account_id' => $account->id, 'actor_id' => $this->manager->id, 'reason' => 'Synthetic', 'effective_at' => '2026-10-03T10:00:00+01:00', 'created_at' => now()];
        $charge = LedgerEntry::create($base + ['kind' => 'charge', 'category' => 'base', 'amount_cents' => 100000, 'charge_delta' => 100000]);
        LedgerEntry::create($base + ['kind' => 'reversal', 'reverses_id' => $charge->id, 'amount_cents' => 100000, 'charge_delta' => -100000]);
        LedgerEntry::create($base + ['kind' => 'charge', 'category' => 'base', 'amount_cents' => 120000, 'charge_delta' => 120000]);
        LedgerEntry::create($base + ['kind' => 'charge', 'category' => 'opening', 'amount_cents' => 900000, 'charge_delta' => 900000]);
        LedgerEntry::create($base + ['kind' => 'payment', 'amount_cents' => 50000, 'payment_delta' => 50000]);
        LedgerEntry::create($base + ['kind' => 'deposit_received', 'amount_cents' => 30000, 'deposit_delta' => 30000]);
        LedgerEntry::create($base + ['kind' => 'deposit_applied', 'amount_cents' => 10000, 'deposit_delta' => -10000, 'payment_delta' => 10000]);
        $data = $this->report();
        $this->assertSame(120000, $data['totals']['revenue']);
        $this->assertSame(50000, $data['totals']['collections']);
        $this->assertSame(120000, $data['daily'][0]['revenue']);
        $this->assertSame(50000, $data['daily'][2]['collections']);
        $dashboard = $this->getJson('/api/v1/dashboard?from=2026-10-01&to=2026-10-03')->assertOk()->json('data.money');
        $this->assertSame($dashboard['revenue'], $data['totals']['revenue']);
        $this->assertSame($dashboard['collections'], $data['totals']['collections']);
    }

    public function test_permissions_private_evidence_filters_csv_and_locales(): void
    {
        Storage::fake('local');
        $id = $this->postJson('/api/v1/expenses', $this->expense())->assertCreated()->json('data.id');
        $a = $this->post('/api/v1/expenses/'.$id.'/attachments', ['file' => UploadedFile::fake()->create('bill.pdf', 10, 'application/pdf')])->assertCreated()->assertJsonMissingPath('data.path')->json('data.id');
        $this->get('/expense-attachments/'.$a)->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->getJson('/api/v1/reports?from=2026-10-04&to=2026-10-01')->assertUnprocessable();
        $this->get('/reports/export?from=2026-10-01&to=2026-10-03')->assertOk()->assertDownload('vehicle-report-2026-10-01-2026-10-03.csv');
        foreach (['fr', 'ar', 'en'] as $locale) {
            $this->manager->update(['locale' => $locale]);
            $this->get('/reports')->assertOk();
            $this->get('/expenses')->assertOk();
        }
        Livewire::test(Reports::class)->set('to', 'invalid')->call('apply')->assertHasErrors('to');
        $this->actingAs(User::factory()->create(['role' => 'agent', 'active' => true]));
        $this->get('/reports')->assertForbidden();
        $this->get('/expenses')->assertForbidden();
        $this->get('/expense-attachments/'.$a)->assertForbidden();
        $this->postJson('/api/v1/expenses', $this->expense())->assertForbidden();
        Sanctum::actingAs($this->manager, ['fleet.view']);
        $this->getJson('/api/v1/reports?from=2026-10-01&to=2026-10-03')->assertForbidden();
    }

    public function test_active_rental_uses_elapsed_time_and_flags_service_boundary(): void
    {
        $this->rental(['status' => 'active', 'starts_at' => '2026-10-04T00:00:00+01:00', 'ends_at' => '2026-10-05T00:00:00+01:00', 'handed_over_at' => '2026-10-04T00:00:00+01:00', 'returned_at' => null]);
        $row = app(Report::class)->read($this->manager, ['from' => '2026-10-04', 'to' => '2026-10-05'])['vehicles'][0];
        $this->assertEquals(12, $row['rented_hours']);
        $this->assertEquals(12, $row['eligible_hours']);
        $this->assertEquals(100, $row['utilization']);
        $this->car->update(['entered_service_at' => '2026-10-05']);
        $this->assertSame('overlap', app(Report::class)->read($this->manager, ['from' => '2026-10-04', 'to' => '2026-10-05'])['vehicles'][0]['utilization_status']);
    }

    public function test_existing_maintenance_backfill_and_csv_formula_protection(): void
    {
        $service = app(Maintenance::class)->record($this->manager, ['vehicle_id' => $this->car->id, 'service_type' => 'oil', 'serviced_on' => '2026-10-01', 'mileage_km' => 1000, 'cost' => '25', 'idempotency_key' => (string) Str::uuid()]);
        $migration = require database_path('migrations/2026_10_04_000002_create_expenses.php');
        $migration->down();
        $migration->up();
        $this->assertDatabaseHas('expenses', ['maintenance_record_id' => $service->id, 'amount_cents' => 2500]);
        $this->assertSame(2500, $this->report()['totals']['expenses']);
        $this->car->update(['registration' => '=1+1']);
        $csv = $this->get('/reports/export?from=2026-10-01&to=2026-10-03')->assertOk()->streamedContent();
        $this->assertStringContainsString("'=1+1", $csv);
        $this->assertStringContainsString('25.00', $csv);
    }

    public function test_finance_can_record_and_validate_private_files_and_web_retry(): void
    {
        Storage::fake('local');
        $this->actingAs(User::factory()->create(['role' => 'finance', 'active' => true]));
        $input = $this->expense();
        unset($input['idempotency_key']);
        Livewire::test(Expenses::class)->set('form', $input)->call('save')->assertHasNoErrors();
        $expense = Expense::first();
        $this->postJson('/api/v1/expenses/'.$expense->id.'/attachments', ['file' => UploadedFile::fake()->create('script.html', 2, 'text/html')])->assertUnprocessable();
        $this->postJson('/api/v1/expenses', $this->expense(['amount' => '-1']))->assertUnprocessable();
        $this->postJson('/api/v1/expenses', $this->expense(['incurred_on' => '2026-10-05']))->assertUnprocessable();
        $this->getJson('/api/v1/reports?from=2026-10-01&to=2026-10-03&vehicle_id='.$this->car->id)->assertOk()->assertJsonPath('data.totals.expenses', 12550);
    }

    public function test_unassigned_cancellation_and_refund_reconcile_and_filter_out(): void
    {
        $customer = Customer::create(['name' => 'Cancelled inquiry', 'type' => 'individual']);
        $booking = Reservation::create(['customer_id' => $customer->id, 'status' => 'cancelled', 'starts_at' => now()->subDay(), 'ends_at' => now(), 'created_by' => $this->manager->id]);
        $account = FinancialAccount::create(['customer_id' => $customer->id, 'reservation_id' => $booking->id]);
        $base = ['financial_account_id' => $account->id, 'actor_id' => $this->manager->id, 'reason' => 'Synthetic cancellation', 'effective_at' => now()->subDay(), 'created_at' => now()];
        LedgerEntry::create($base + ['kind' => 'charge', 'category' => 'cancellation', 'amount_cents' => 20000, 'charge_delta' => 20000]);
        LedgerEntry::create($base + ['kind' => 'payment', 'amount_cents' => 50000, 'payment_delta' => 50000]);
        LedgerEntry::create($base + ['kind' => 'refund', 'amount_cents' => 30000, 'payment_delta' => -30000]);
        $data = $this->report();
        $this->assertSame(20000, $data['totals']['standalone_cancellation']);
        $this->assertSame(['revenue' => 20000, 'collections' => 20000], $data['unallocated']);
        $filtered = app(Report::class)->read($this->manager, ['from' => '2026-10-01', 'to' => '2026-10-03', 'vehicle_id' => $this->car->id]);
        $this->assertSame(0, $filtered['totals']['revenue']);
        $this->assertSame(0, $filtered['totals']['collections']);
    }
}
