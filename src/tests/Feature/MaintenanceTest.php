<?php

namespace Tests\Feature;

use App\Livewire\Maintenance;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleCategory;
use App\Modules\Maintenance\Actions;
use App\Modules\Reservations\Actions as Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Livewire\Livewire;
use Tests\TestCase;

class MaintenanceTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    private Vehicle $car;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now('Africa/Algiers')->setDate(2026, 10, 4)->setTime(10, 0));
        $this->manager = User::factory()->create(['role' => 'manager', 'active' => true]);
        $this->car = Vehicle::create(['registration' => 'SERVICE01', 'make' => 'Test', 'model' => 'Car', 'year' => 2025, 'category_id' => VehicleCategory::create(['name' => 'Test'])->id, 'fuel_type' => 'petrol', 'transmission' => 'manual', 'mileage_km' => 10000, 'daily_rate' => '1000']);
        $this->actingAs($this->manager);
    }

    private function payload(array $overrides = []): array
    {
        return array_replace(['vehicle_id' => $this->car->id, 'service_type' => 'oil', 'serviced_on' => '2026-10-04', 'mileage_km' => 10000, 'cost' => '1250.50', 'next_due_on' => '2026-11-03', 'next_due_km' => 10500, 'idempotency_key' => (string) Str::uuid()], $overrides);
    }

    public function test_record_retries_costs_and_historical_odometer(): void
    {
        $input = $this->payload(['mileage_km' => 10200]);
        $first = $this->postJson('/api/v1/maintenance', $input)->assertCreated()->assertJsonPath('data.cost_cents', 125050)->json('data.id');
        $this->postJson('/api/v1/maintenance', $input)->assertCreated()->assertJsonPath('data.id', $first);
        $this->assertDatabaseCount('maintenance_records', 1);
        $this->postJson('/api/v1/maintenance', array_replace($input, ['cost' => '2']))->assertConflict();
        $this->assertSame(10200, $this->car->fresh()->mileage_km);
        $this->postJson('/api/v1/maintenance', $this->payload(['serviced_on' => '2026-09-01', 'mileage_km' => 5000]))->assertCreated();
        $this->assertSame(10200, $this->car->fresh()->mileage_km);
        $this->assertDatabaseHas('audit_events', ['action' => 'maintenance.recorded', 'actor_id' => $this->manager->id]);
    }

    public function test_reminder_boundaries_supersession_and_dashboard(): void
    {
        $a = app(Actions::class);
        $a->record($this->manager, $this->payload());
        $this->getJson('/api/v1/maintenance/reminders')->assertOk()->assertJsonPath('data.0.status', 'soon');
        DB::table('agency_settings')->where('id', 1)->update(['maintenance_warning_days' => 29, 'maintenance_warning_km' => 499]);
        $this->assertCount(0, $a->reminders($this->manager));
        $this->car->update(['mileage_km' => 10500]);
        $this->assertSame('due', $a->reminders($this->manager)[0]['status']);
        $data = $this->getJson('/api/v1/dashboard?from=2026-10-04&to=2026-10-04')->assertOk()->json('data');
        $this->assertContains('maintenance', array_column($data['urgent'], 'kind'));
        $a->record($this->manager, $this->payload(['serviced_on' => '2026-09-01', 'next_due_on' => '2026-09-02', 'next_due_km' => null]));
        $this->assertCount(1, $a->reminders($this->manager));
        $a->record($this->manager, $this->payload(['next_due_on' => null, 'next_due_km' => null]));
        $this->assertCount(0, $a->reminders($this->manager));
        $a->record($this->manager, $this->payload(['service_type' => 'brakes', 'serviced_on' => '2026-10-01', 'next_due_on' => '2026-10-04', 'next_due_km' => null]));
        $this->assertSame('due', $a->reminders($this->manager)[0]['status']);
        $this->car->update(['archived_at' => now()]);
        $this->assertCount(0, $a->reminders($this->manager));
    }

    public function test_block_link_keeps_occupancy_until_explicit_versioned_release(): void
    {
        $schedule = app(Schedule::class);
        $block = $schedule->block($this->manager, ['vehicle_id' => $this->car->id, 'kind' => 'maintenance', 'starts_at' => now()->subDay()->toIso8601String(), 'reason' => 'Workshop']);
        $record = app(Actions::class)->record($this->manager, $this->payload(['vehicle_commitment_id' => $block->id]));
        $this->assertCount(1, $schedule->conflicts($this->car->id, now()->toImmutable(), now()->addHour()->toImmutable()));
        $this->postJson('/api/v1/maintenance/'.$record->id.'/release', ['version' => 99, 'reason' => 'Done'])->assertConflict();
        $this->postJson('/api/v1/maintenance/'.$record->id.'/release', ['version' => $block->fresh()->version, 'reason' => 'Done'])->assertOk();
        $this->assertCount(0, $schedule->conflicts($this->car->id, now()->toImmutable(), now()->addHour()->toImmutable()));
        $clean = $schedule->block($this->manager, ['vehicle_id' => $this->car->id, 'kind' => 'cleaning', 'starts_at' => now()->toIso8601String(), 'reason' => 'Clean']);
        $this->postJson('/api/v1/maintenance', $this->payload(['vehicle_commitment_id' => $clean->id]))->assertUnprocessable()->assertJsonValidationErrors('vehicle_commitment_id');
        $other = $this->car->replicate();
        $other->registration = 'SERVICE02';
        $other->save();
        $this->postJson('/api/v1/maintenance', $this->payload(['vehicle_id' => $other->id, 'vehicle_commitment_id' => $block->id]))->assertUnprocessable();
    }

    public function test_private_upload_and_role_and_token_scope(): void
    {
        Storage::fake('local');
        $r = app(Actions::class)->record($this->manager, $this->payload(['notes' => 'Private invoice note']));
        $id = $this->post('/api/v1/maintenance/'.$r->id.'/attachments', ['file' => UploadedFile::fake()->create('bill.pdf', 10, 'application/pdf')])->assertCreated()->assertJsonMissingPath('data.path')->json('data.id');
        $this->get('/maintenance-attachments/'.$id)->assertOk()->assertHeader('x-content-type-options', 'nosniff');
        $this->postJson('/api/v1/maintenance/'.$r->id.'/attachments', ['file' => UploadedFile::fake()->create('script.html', 10, 'text/html')])->assertUnprocessable();
        $agent = User::factory()->create(['role' => 'agent', 'active' => true]);
        $this->actingAs($agent);
        $this->postJson('/api/v1/maintenance', $this->payload())->assertForbidden();
        $this->getJson('/api/v1/maintenance')->assertOk()->assertJsonMissingPath('data.0.cost_cents')->assertJsonMissingPath('data.0.notes')->assertJsonMissingPath('data.0.attachments');
        $this->get('/maintenance-attachments/'.$id)->assertForbidden();
        $finance = User::factory()->create(['role' => 'finance', 'active' => true]);
        $this->actingAs($finance);
        $this->getJson('/api/v1/maintenance')->assertOk()->assertJsonPath('data.0.cost_cents', 125050);
        $this->get('/maintenance-attachments/'.$id)->assertOk();
        $this->postJson('/api/v1/maintenance', $this->payload())->assertForbidden();
        Sanctum::actingAs($this->manager, ['maintenance.view']);
        $this->postJson('/api/v1/maintenance', $this->payload())->assertForbidden();
        $this->getJson('/api/v1/maintenance')->assertJsonMissingPath('data.0.cost_cents');
    }

    public function test_validation_web_parity_and_locale_rendering(): void
    {
        foreach ([['cost' => '-1'], ['serviced_on' => '2026-10-05'], ['next_due_km' => 10000], ['next_due_on' => '2026-10-03']] as $bad) {
            $this->postJson('/api/v1/maintenance', $this->payload($bad))->assertUnprocessable();
        }
        $form = $this->payload(['next_due_km' => '', 'next_due_on' => '']);
        unset($form['idempotency_key']);
        Livewire::test(Maintenance::class)->set('form', $form)->call('save')->assertHasNoErrors();
        $this->assertDatabaseCount('maintenance_records', 1);
        foreach (['fr', 'ar', 'en'] as $locale) {
            $this->manager->update(['locale' => $locale]);
            $this->get('/maintenance')->assertOk()->assertSee('dir="'.($locale === 'ar' ? 'rtl' : 'ltr').'"', false);
        }
        $this->car->update(['archived_at' => now()]);
        $this->postJson('/api/v1/maintenance', $this->payload())->assertConflict();
    }
}
