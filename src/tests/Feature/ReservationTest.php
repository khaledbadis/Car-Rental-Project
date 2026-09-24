<?php

namespace Tests\Feature;

use App\Livewire\ReservationEditor;
use App\Livewire\Reservations;
use App\Models\Customer;
use App\Models\Document;
use App\Models\Reservation;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleCategory;
use App\Models\VehicleCommitment;
use App\Modules\Reservations\Actions;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class ReservationTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    private Vehicle $car;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->manager = User::factory()->create(['role' => 'manager', 'active' => true]);
        $this->car = Vehicle::create(['registration' => 'TEST01', 'make' => 'Renault', 'model' => 'Clio', 'year' => 2024, 'category_id' => VehicleCategory::create(['name' => 'Economy'])->id, 'fuel_type' => 'petrol', 'transmission' => 'automatic', 'mileage_km' => 100, 'daily_rate' => '8000']);
        $this->customer = Customer::create(['type' => 'individual', 'name' => 'Test customer']);
        foreach (['insurance', 'inspection'] as $type) {
            Document::create(['vehicle_id' => $this->car->id, 'type' => $type, 'path' => 'test', 'original_name' => 'test.pdf', 'mime' => 'application/pdf', 'size' => 10, 'expires_at' => '2030-12-31', 'uploaded_by' => $this->manager->id]);
        }$this->actingAs($this->manager);
    }

    private function input(array $extra = []): array
    {
        return array_replace(['customer_id' => $this->customer->id, 'vehicle_id' => $this->car->id, 'category_id' => $this->car->category_id, 'status' => 'confirmed', 'starts_at' => '2027-01-10T10:00:00+01:00', 'ends_at' => '2027-01-11T10:00:00+01:00'], $extra);
    }

    public function test_tentative_and_confirmation(): void
    {
        $this->postJson('/api/v1/reservations', $this->input(['status' => 'tentative', 'vehicle_id' => null]))->assertCreated();
        $this->assertDatabaseCount('vehicle_commitments', 0);
        $this->postJson('/api/v1/reservations', $this->input(['vehicle_id' => null]))->assertUnprocessable();
        $this->postJson('/api/v1/reservations', $this->input())->assertCreated();
        $this->assertDatabaseCount('vehicle_commitments', 1);
    }

    public function test_overlap_and_exact_buffer_boundary(): void
    {
        $this->postJson('/api/v1/reservations', $this->input())->assertCreated();
        $this->postJson('/api/v1/reservations', $this->input(['starts_at' => '2027-01-11T11:59:00+01:00', 'ends_at' => '2027-01-12T10:00:00+01:00']))->assertConflict()->assertJsonPath('code', 'overlap');
        $this->postJson('/api/v1/reservations', $this->input(['starts_at' => '2027-01-11T12:00:00+01:00', 'ends_at' => '2027-01-12T10:00:00+01:00']))->assertCreated();
        $this->assertSame('2027-01-10 09:00:00', Reservation::first()->starts_at->format('Y-m-d H:i:s'));
    }

    public function test_snapshot_buffer_and_stale_edits(): void
    {
        $r = app(Actions::class)->save($this->manager, $this->input());
        DB::table('agency_settings')->update(['preparation_minutes' => 300]);
        $v = $this->input(['version' => 1, 'reason' => 'Notes', 'notes' => 'Updated']);
        $this->putJson('/api/v1/reservations/'.$r->id, $v)->assertOk()->assertJsonPath('data.preparation_minutes', 120)->assertJsonPath('data.version', 2);
        $this->putJson('/api/v1/reservations/'.$r->id, $v)->assertConflict()->assertJsonPath('code', 'stale');
    }

    public function test_expiry_and_scoped_override(): void
    {
        Document::where('type', 'insurance')->update(['expires_at' => '2027-01-11']);
        $this->postJson('/api/v1/reservations', $this->input(['ends_at' => '2027-01-11T23:59:00+01:00']))->assertCreated();
        $v = $this->input(['starts_at' => '2027-01-13T10:00:00+01:00', 'ends_at' => '2027-01-14T10:00:00+01:00']);
        $this->postJson('/api/v1/reservations', $v)->assertConflict()->assertJsonPath('code', 'documents');
        $this->actingAs(User::factory()->create(['role' => 'agent', 'active' => true]))->postJson('/api/v1/reservations', $v + ['document_override_reason' => 'Exception'])->assertForbidden();
        $r = $this->actingAs($this->manager)->postJson('/api/v1/reservations', $v + ['document_override_reason' => 'Manager exception'])->assertCreated()->json('data');
        $this->assertDatabaseHas('audit_events', ['action' => 'reservation.document_override', 'reason' => 'Manager exception']);
        $this->putJson('/api/v1/reservations/'.$r['id'], array_replace($v, ['version' => 1, 'reason' => 'Extension', 'ends_at' => '2027-01-15T10:00:00+01:00']))->assertConflict()->assertJsonPath('code', 'documents');
    }

    public function test_missing_documents_and_overlap_not_overridden(): void
    {
        Document::query()->update(['removed_at' => now()]);
        $this->postJson('/api/v1/reservations', $this->input())->assertConflict()->assertJsonPath('code', 'documents');
        $this->postJson('/api/v1/reservations', $this->input(['document_override_reason' => 'Exception']))->assertCreated();
        $this->postJson('/api/v1/reservations', $this->input(['document_override_reason' => 'Exception']))->assertConflict()->assertJsonPath('code', 'overlap');
    }

    public function test_cancel_requires_reason_releases_commitment(): void
    {
        $r = app(Actions::class)->save($this->manager, $this->input());
        $this->postJson('/api/v1/reservations/'.$r->id.'/cancel', ['version' => 1])->assertUnprocessable();
        $this->postJson('/api/v1/reservations/'.$r->id.'/cancel', ['version' => 1, 'reason' => 'No-show'])->assertOk()->assertJsonPath('data.status', 'cancelled');
        $this->postJson('/api/v1/reservations', $this->input())->assertCreated();
        $this->assertDatabaseHas('audit_events', ['action' => 'reservation.cancelled', 'reason' => 'No-show']);
    }

    public function test_no_show_keeps_inventory(): void
    {
        $r = app(Actions::class)->save($this->manager, $this->input());
        $this->travelTo($r->starts_at->addHours(3));
        $this->getJson('/api/v1/reservations/'.$r->id)->assertJsonPath('pickup_status', 'no_show');
        $this->assertNull(VehicleCommitment::first()->released_at);
        $this->postJson('/api/v1/reservations', $this->input())->assertConflict();
    }

    public function test_reassignment_atomicity(): void
    {
        $car = $this->car->replicate();
        $car->registration = 'TEST02';
        $car->save();
        foreach ($this->car->documents as $d) {
            $copy = $d->replicate();
            $copy->vehicle_id = $car->id;
            $copy->save();
        }$a = app(Actions::class);
        $one = $a->save($this->manager, $this->input());
        $two = $a->save($this->manager, $this->input(['vehicle_id' => $car->id]));
        $v = $this->input(['vehicle_id' => $car->id, 'version' => 1, 'reason' => 'Reassignment']);
        $this->putJson('/api/v1/reservations/'.$one->id, $v)->assertConflict();
        $this->assertSame($this->car->id, $one->fresh()->vehicle_id);
        $a->cancel($this->manager, $two->id, ['version' => 1, 'reason' => 'Cancelled']);
        $this->putJson('/api/v1/reservations/'.$one->id, $v)->assertOk();
        $this->postJson('/api/v1/reservations', $this->input())->assertCreated();
        $this->assertSame($car->id, VehicleCommitment::where('reservation_id', $one->id)->first()->vehicle_id);
    }

    public function test_indefinite_emergency_block_flags_retained_booking(): void
    {
        $r = app(Actions::class)->save($this->manager, $this->input());
        $v = ['vehicle_id' => $this->car->id, 'kind' => 'maintenance', 'starts_at' => '2027-01-10T09:00:00+01:00', 'reason' => 'Breakdown'];
        $this->postJson('/api/v1/blocks', $v)->assertConflict();
        $b = $this->postJson('/api/v1/blocks', $v + ['emergency' => true])->assertCreated()->json('data');
        $this->getJson('/api/v1/reservations/'.$r->id)->assertJsonCount(1, 'conflicts')->assertJsonPath('data.status', 'confirmed');
        $this->postJson('/api/v1/reservations', $this->input(['starts_at' => '2027-02-10T10:00:00+01:00', 'ends_at' => '2027-02-11T10:00:00+01:00']))->assertConflict();
        $this->postJson('/api/v1/blocks/'.$b['id'].'/release', ['version' => 1, 'reason' => 'Repaired'])->assertOk();
        $this->getJson('/api/v1/reservations/'.$r->id)->assertJsonCount(0, 'conflicts');
    }

    public function test_roles_tokens_and_search_reasons(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'finance', 'active' => true]));
        $this->getJson('/api/v1/reservations')->assertOk();
        $this->postJson('/api/v1/reservations', $this->input())->assertForbidden();
        $this->actingAs(User::factory()->create(['role' => 'agent', 'active' => true]));
        $v = ['vehicle_id' => $this->car->id, 'kind' => 'maintenance', 'starts_at' => '2027-01-10T09:00:00+01:00', 'reason' => 'Work'];
        $this->postJson('/api/v1/blocks', $v)->assertForbidden();
        $this->postJson('/api/v1/blocks', array_replace($v, ['kind' => 'cleaning']))->assertCreated();
        $this->getJson('/api/v1/availability?'.http_build_query($this->input()))->assertOk()->assertJsonPath('data.0.available', false)->assertJsonPath('data.0.conflicts.0.kind', 'cleaning');
        $this->app['auth']->forgetGuards();
        $token = $this->manager->createToken('read', ['reservations.view'])->plainTextToken;
        $this->withToken($token)->postJson('/api/v1/reservations', $this->input())->assertForbidden();
    }

    public function test_invalid_dates_and_archived_records(): void
    {
        $this->postJson('/api/v1/reservations', $this->input(['starts_at' => '2027-01-10 10:00']))->assertUnprocessable();
        $this->postJson('/api/v1/reservations', $this->input(['ends_at' => '2027-01-09T10:00:00+01:00']))->assertUnprocessable();
        $this->car->update(['archived_at' => now()]);
        $this->postJson('/api/v1/reservations', $this->input())->assertConflict()->assertJsonPath('code', 'archived');
    }

    public function test_localized_pages_and_livewire_adapters(): void
    {
        $r = app(Actions::class)->save($this->manager, $this->input());
        foreach (['fr', 'ar', 'en'] as $locale) {
            $this->manager->update(['locale' => $locale]);
            foreach (['/reservations', '/reservations/'.$r->id, '/availability'] as $url) {
                $this->get($url)->assertOk();
            }
        }Livewire::test(ReservationEditor::class)->set('form', array_replace($this->input(['status' => 'tentative', 'vehicle_id' => '']), ['starts_at' => '2027-01-10T10:00', 'ends_at' => '2027-01-11T10:00']))->call('save')->assertHasNoErrors()->assertRedirect();
        Livewire::test(Reservations::class)->set('mode', 'calendar')->set('week', '2027-01-10')->assertSee($this->customer->name);
    }

    public function test_database_constraint_rejects_an_overlapping_allocation_without_actions(): void
    {
        app(Actions::class)->save($this->manager, $this->input());
        $tentative = app(Actions::class)->save($this->manager, $this->input(['status' => 'tentative']));
        $this->expectException(QueryException::class);
        $this->expectExceptionCode('23P01');
        VehicleCommitment::create(['vehicle_id' => $this->car->id, 'reservation_id' => $tentative->id, 'kind' => 'reservation', 'starts_at' => '2027-01-10 09:00:00+00', 'ends_at' => '2027-01-11 11:00:00+00', 'created_by' => $this->manager->id]);
    }

    public function test_scheduled_block_half_open_boundary_and_archive_history(): void
    {
        $this->postJson('/api/v1/blocks', ['vehicle_id' => $this->car->id, 'kind' => 'cleaning', 'starts_at' => '2027-01-10T08:00:00+01:00', 'ends_at' => '2027-01-10T10:00:00+01:00', 'reason' => 'Preparation'])->assertCreated();
        $r = $this->postJson('/api/v1/reservations', $this->input())->assertCreated()->json('data');
        $this->car->update(['archived_at' => now()]);
        $this->getJson('/api/v1/reservations/'.$r['id'])->assertOk()->assertJsonPath('data.vehicle.registration', 'TEST01');
        $this->getJson('/api/v1/availability?'.http_build_query($this->input()))->assertJsonCount(0, 'data');
        $this->assertDatabaseCount('vehicle_commitments', 2);
    }

    public function test_calendar_includes_preparation_spilling_into_the_next_day(): void
    {
        $this->postJson('/api/v1/reservations', $this->input(['ends_at' => '2027-01-11T23:00:00+01:00']))->assertCreated();
        $this->getJson('/api/v1/reservations?from=2027-01-12&to=2027-01-12')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/reservations?to=2027-01-12')->assertOk();
    }
}
