<?php

namespace Tests\Feature;

use App\Livewire\Dashboard;
use App\Livewire\Preferences;
use App\Models\Customer;
use App\Models\Rental;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleCategory;
use App\Modules\Foundation\Actions as Foundation;
use App\Modules\Release\CutoverRehearsal;
use App\Modules\Release\Profile;
use App\Modules\Rentals\Actions;
use App\Modules\Rentals\Ledger;
use App\Modules\Rentals\Money;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Livewire\Livewire;
use Tests\TestCase;

class ReleaseTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now('Africa/Algiers')->setDate(2027, 1, 10)->setTime(10, 0));
        $this->manager = User::factory()->create(['role' => 'manager', 'active' => true]);
        $this->customer = Customer::create(['name' => 'Release Customer', 'type' => 'individual']);
        $this->actingAs($this->manager);
    }

    private function rental(): Rental
    {
        $car = Vehicle::create(['registration' => 'RELEASE01', 'make' => 'Test', 'model' => 'Car', 'year' => 2025, 'category_id' => VehicleCategory::create(['name' => 'Release'])->id, 'fuel_type' => 'petrol', 'transmission' => 'manual', 'mileage_km' => 0, 'daily_rate' => '1000']);

        return app(Actions::class)->create($this->manager, ['customer_id' => $this->customer->id, 'vehicle_id' => $car->id, 'starts_at' => now()->toIso8601String(), 'ends_at' => now()->addDay()->toIso8601String(), 'basis' => 'daily', 'discount_type' => 'none', 'document_override_reason' => 'Synthetic test', 'idempotency_key' => (string) Str::uuid()]);
    }

    private function entry(string $kind, string $amount): array
    {
        return ['kind' => $kind, 'amount' => $amount, 'method' => 'cash', 'reason' => 'Test', 'effective_at' => now()->toIso8601String(), 'idempotency_key' => (string) Str::uuid()];
    }

    public function test_profile_preserves_username_and_audit_without_copying_contact_data(): void
    {
        $username = $this->manager->username;
        app(Foundation::class)->audit($this->manager, 'profile.updated', 'user', $this->manager->id, null, null);
        $before = DB::table('audit_events')->first();
        $this->postJson('/api/v1/me/profile', ['name' => 'Updated Name', 'email' => 'updated@example.test', 'phone' => '0555111222', 'address' => 'Private address'])->assertOk()->assertJsonPath('data.username', $username);
        $after = DB::table('audit_events')->first();
        $this->assertEquals($before, $after);
        $this->assertSame($username, DB::table('audit_events')->latest('id')->value('actor_username'));
        $this->assertStringNotContainsString('Private address', DB::table('audit_events')->latest('id')->value('after'));
        $this->postJson('/api/v1/me/profile', ['name' => 'Again', 'email' => 'updated@example.test', 'username' => 'changed'])->assertUnprocessable();
        Livewire::test(Preferences::class)->set('profile.name', 'Web name')->call('saveProfile')->assertHasNoErrors();
        $this->assertSame($username, $this->manager->fresh()->username);
    }

    public function test_username_database_guard_rejects_direct_change(): void
    {
        $this->expectException(QueryException::class);
        DB::table('users')->where('id', $this->manager->id)->update(['username' => 'new']);
    }

    public function test_private_avatar_replacement_and_invalid_file(): void
    {
        Storage::fake('local');
        $data = ['name' => 'Name', 'email' => $this->manager->email];
        $file = UploadedFile::fake()->createWithContent('avatar.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII='));
        $u = app(Profile::class)->save($this->manager, $data + ['avatar' => $file]);
        $this->actingAs($u)->get('/profile/avatar')->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        $old = $u->avatar_path;
        $this->assertArrayNotHasKey('avatar_path', $u->toArray());
        app(Profile::class)->save($u, $data + ['remove_avatar' => true]);
        Storage::disk('local')->assertMissing($old);
        $this->postJson('/api/v1/me/profile', $data + ['avatar' => UploadedFile::fake()->create('avatar.svg', 1, 'image/svg+xml')])->assertUnprocessable();
    }

    public function test_receipts_are_atomic_stable_and_distinguish_refunds_reversals(): void
    {
        $r = $this->rental();
        $input = $this->entry('payment', '500');
        $e = app(Ledger::class)->post($this->manager, 'rental', $r->id, $input);
        $receipt = $e->receipt;
        $this->assertSame('REC-2027-000001', $receipt->number);
        app(Ledger::class)->post($this->manager, 'rental', $r->id, $input);
        $this->assertDatabaseCount('receipts', 1);
        $this->customer->update(['name' => 'Changed later']);
        $this->assertSame('Release Customer', $receipt->fresh()->snapshot['customer']['name']);
        $refund = app(Ledger::class)->post($this->manager, 'rental', $r->id, $this->entry('refund', '100'));
        $this->assertSame('REF-2027-000001', $refund->receipt->number);
        $reverse = app(Ledger::class)->post($this->manager, 'rental', $r->id, $this->entry('reversal', '0') + ['reverses_id' => $refund->id]);
        $this->assertSame($refund->receipt->number, $reverse->receipt->snapshot['corrects']);
        $this->get(route('receipts.print', $reverse->receipt->id))->assertOk()->assertSee('RECTIFICATION')->assertSee('REF-2027-000001');
        $this->travelTo(now()->addYear());
        $e2 = app(Ledger::class)->post($this->manager, 'rental', $r->id, $this->entry('payment', '100'));
        $this->assertSame('REC-2028-000001', $e2->receipt->number);
    }

    public function test_receipt_database_guard(): void
    {
        $r = $this->rental();
        $e = app(Ledger::class)->post($this->manager, 'rental', $r->id, $this->entry('payment', '50'));
        $this->expectException(QueryException::class);
        $e->receipt->update(['number' => 'changed']);
    }

    public function test_contract_versions_have_frozen_template_and_role_scope(): void
    {
        $r = $this->rental();
        $legacy = json_decode(DB::table('rental_versions')->where('rental_id', $r->id)->value('snapshot'), true);
        unset($legacy['template']);
        DB::table('rental_versions')->insert(['rental_id' => $r->id, 'number' => 2, 'snapshot' => json_encode($legacy), 'actor_id' => $this->manager->id, 'reason' => 'Legacy version fixture', 'created_at' => now()]);
        config(['contract.agency.name' => 'Changed agency']);
        $this->get(route('contracts.print', [$r->id, 2]))->assertOk()->assertDontSee('Changed agency');

        $this->get(route('contracts.print', [$r->id, 1]))->assertOk()->assertSee('MODÈLE')->assertDontSee('Changed agency');
        $finance = User::factory()->create(['role' => 'finance', 'active' => true]);
        $this->actingAs($finance)->get(route('contracts.print', [$r->id, 1]))->assertForbidden();
    }

    public function test_dashboard_excludes_deposits_and_drafts_from_revenue_and_hides_money_for_agents(): void
    {
        $r = $this->rental();
        app(Ledger::class)->post($this->manager, 'rental', $r->id, $this->entry('payment', '300'));
        app(Ledger::class)->post($this->manager, 'rental', $r->id, $this->entry('deposit_received', '5000'));
        $url = '/api/v1/dashboard?from=2027-01-01&to=2027-01-31';
        $this->getJson($url)->assertOk()->assertJsonPath('data.money.revenue', 0)->assertJsonPath('data.money.collections', 30000)->assertJsonPath('data.money.held', 500000)->assertJsonPath('data.money.outstanding', 70000);
        $r->update(['status' => 'active', 'handed_over_at' => now()]);
        $this->getJson($url)->assertOk()->assertJsonPath('data.fleet.rented', 1)->assertJsonPath('data.money.revenue', 100000);
        app(Ledger::class)->post($this->manager, 'rental', $r->id, $this->entry('refund', '100'));
        $this->getJson($url)->assertJsonPath('data.money.collections', 20000);
        $agent = User::factory()->create(['role' => 'agent', 'active' => true]);
        $this->actingAs($agent)->getJson($url)->assertOk()->assertJsonPath('data.money', null);
        $this->get('/')->assertOk();
    }

    public function test_fictional_cutover_reconciles_and_rolls_back(): void
    {
        $before = DB::table('rentals')->count();
        $report = app(CutoverRehearsal::class)->run($this->manager);
        $this->assertSame($report['expected'], $report['actual']);
        $this->assertTrue($report['active_occupancy_verified']);
        $this->assertDatabaseCount('rentals', $before);
        $this->assertDatabaseMissing('vehicles', ['registration' => 'MIGRATION-DEMO-01']);
    }

    public function test_profile_and_dashboard_tokens_are_scoped(): void
    {
        Sanctum::actingAs($this->manager, ['rentals.view']);
        $this->postJson('/api/v1/me/profile', ['name' => 'Denied', 'email' => 'denied@example.test'])->assertForbidden();
        $this->getJson('/api/v1/dashboard?from=2027-01-01&to=2027-01-31')->assertForbidden();
    }

    public function test_dashboard_invalid_livewire_filter_keeps_last_valid_period(): void
    {
        Livewire::test(Dashboard::class)->set('from', '2027-02-01')->set('to', '2027-01-01')->call('apply')->assertHasErrors('to')->assertSet('period.from', '2027-01-01');
        $this->assertSame('-1,25 DA', Money::display(-125));
    }

    public function test_dashboard_all_locales_and_invalid_ranges(): void
    {
        foreach (['en', 'fr', 'ar'] as $locale) {
            $this->manager->update(['locale' => $locale]);
            $this->get('/')->assertOk()->assertSee('svg', false);
        }
        $this->getJson('/api/v1/dashboard?from=2027-02-01&to=2027-01-01')->assertUnprocessable();
        $this->getJson('/api/v1/dashboard?from=2020-01-01&to=2027-01-01')->assertUnprocessable();
    }
}
