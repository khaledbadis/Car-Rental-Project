<?php

namespace Tests\Feature;

use App\Livewire\CatalogDirectory;
use App\Livewire\CatalogProfile;
use App\Models\Document;
use App\Models\User;
use App\Models\VehicleCategory;
use App\Modules\Catalog\Actions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class CatalogTest extends TestCase
{
    use RefreshDatabase;

    private function actor(string $role = 'manager'): User
    {
        return User::factory()->create(['role' => $role, 'active' => true, 'locale' => 'fr']);
    }

    private function vehicle(): array
    {
        return ['registration' => '123-456-16', 'make' => 'Renault', 'model' => 'Clio', 'year' => 2022, 'category_id' => VehicleCategory::firstOrCreate(['name' => 'Economy'])->id, 'fuel_type' => 'petrol', 'transmission' => 'automatic', 'mileage_km' => 64000, 'daily_rate' => '8000.00'];
    }

    private function pdf(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('insurance.pdf', "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n%%EOF");
    }

    public function test_vehicle_normalization_unique_and_read_only_agent(): void
    {
        $m = $this->actor();
        $this->actingAs($m)->postJson('/api/v1/vehicles', $this->vehicle())->assertCreated()->assertJsonPath('data.registration', '12345616');
        $this->postJson('/api/v1/vehicles', $this->vehicle())->assertUnprocessable();
        $this->actingAs($this->actor('agent'))->getJson('/api/v1/vehicles')->assertOk();
        $this->postJson('/api/v1/vehicles', $this->vehicle())->assertForbidden();
    }

    public function test_vehicle_edit_is_versioned_and_audited(): void
    {
        $u = $this->actor();
        $v = app(Actions::class)->save($u, 'vehicle', $this->vehicle());
        $input = $this->vehicle() + ['version' => 1, 'reason' => 'New daily rate'];
        $input['daily_rate'] = '9000.00';
        $this->actingAs($u)->putJson('/api/v1/vehicles/'.$v->id, $input)->assertOk()->assertJsonPath('data.version', 2);
        $this->putJson('/api/v1/vehicles/'.$v->id, $input)->assertConflict();
        $this->assertDatabaseHas('audit_events', ['action' => 'vehicle.updated', 'reason' => 'New daily rate']);
    }

    public function test_customers_share_phone_but_not_document_identifiers(): void
    {
        $u = $this->actor('agent');
        $this->actingAs($u);
        $first = ['type' => 'individual', 'name' => 'Demo One', 'phone' => '0555 00 00 01', 'identity_type' => 'national_id', 'identity_number' => 'AB-123'];
        $this->postJson('/api/v1/customers', $first)->assertCreated();
        $second = $first;
        $second['name'] = 'Demo Two';
        $second['identity_number'] = 'AB123';
        $this->postJson('/api/v1/customers', $second)->assertUnprocessable();
        $second['identity_number'] = null;
        $this->postJson('/api/v1/customers', $second)->assertCreated();
        $this->getJson('/api/v1/customers/duplicates?phone=0555000001')->assertOk()->assertJsonCount(2, 'data');
    }

    public function test_company_driver_and_incident_notes(): void
    {
        $u = $this->actor('agent');
        $driver = app(Actions::class)->save($u, 'customer', ['type' => 'individual', 'name' => 'Demo Driver']);
        $this->actingAs($u)->postJson('/api/v1/customers', ['type' => 'company', 'name' => 'Demo Company', 'driver_id' => $driver->id])->assertCreated();
        $this->postJson('/api/v1/customers/'.$driver->id.'/notes', ['category' => 'late_return', 'body' => 'Returned two hours late.'])->assertCreated();
        $this->getJson('/api/v1/customers/'.$driver->id)->assertJsonPath('data.notes.0.body', 'Returned two hours late.');
    }

    public function test_private_document_access_and_removal_audit(): void
    {
        Storage::fake('local');
        $u = $this->actor();
        $customer = app(Actions::class)->save($u, 'customer', ['type' => 'individual', 'name' => 'Demo']);
        $response = $this->actingAs($u)->post('/api/v1/customers/'.$customer->id.'/documents', ['type' => 'identity', 'file' => $this->pdf()], ['Accept' => 'application/json'])->assertCreated()->assertJsonMissingPath('data.path');
        $id = $response->json('data.id');
        $d = Document::find($id);
        $this->get('/documents/'.$id)->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->actingAs($this->actor('finance'))->get('/documents/'.$id)->assertForbidden();
        $this->getJson('/api/v1/documents/'.$id)->assertForbidden();
        $this->actingAs($this->actor('agent'))->deleteJson('/api/v1/documents/'.$id, ['reason' => 'Remove'])->assertForbidden();
        $this->actingAs($u)->deleteJson('/api/v1/documents/'.$id, ['reason' => 'Incorrect scan'])->assertNoContent();
        $this->get('/documents/'.$id)->assertNotFound();
        Storage::disk('local')->assertMissing($d->path);
        $this->assertDatabaseHas('audit_events', ['action' => 'document.removed', 'reason' => 'Incorrect scan']);
    }

    public function test_upload_rejects_spoofed_and_oversized_files(): void
    {
        Storage::fake('local');
        $u = $this->actor();
        $v = app(Actions::class)->save($u, 'vehicle', $this->vehicle());
        $this->actingAs($u);
        $this->post('/api/v1/vehicles/'.$v->id.'/documents', ['type' => 'insurance', 'file' => UploadedFile::fake()->createWithContent('bad.pdf', 'plain text')], ['Accept' => 'application/json'])->assertUnprocessable();
        DB::table('agency_settings')->where('id', 1)->update(['upload_limit_mb' => 1]);
        $file = UploadedFile::fake()->create('large.pdf', 2048, 'application/pdf');
        $this->post('/api/v1/vehicles/'.$v->id.'/documents', ['type' => 'insurance', 'file' => $file], ['Accept' => 'application/json'])->assertUnprocessable();
        $this->assertDatabaseCount('documents', 0);
    }

    public function test_archiving_preserves_documents_and_notes(): void
    {
        Storage::fake('local');
        $u = $this->actor();
        $c = app(Actions::class)->save($u, 'customer', ['type' => 'individual', 'name' => 'Historic Demo']);
        app(Actions::class)->note($u, $c->id, ['body' => 'Preserve me', 'category' => 'other']);
        $d = app(Actions::class)->upload($u, 'customer', $c->id, ['type' => 'identity', 'file' => $this->pdf()]);
        $this->actingAs($u)->postJson('/api/v1/customers/'.$c->id.'/archive', ['reason' => 'Inactive customer', 'version' => 1])->assertOk();
        $this->getJson('/api/v1/customers')->assertJsonCount(0, 'data');
        $this->getJson('/api/v1/customers?status=archived')->assertJsonCount(1, 'data');
        $this->get('/documents/'.$d->id)->assertOk();
        $this->assertDatabaseCount('customer_notes', 1);
        $this->get('/customers/'.$c->id)->assertOk();
    }

    public function test_expiry_boundaries_and_alerts(): void
    {
        Storage::fake('local');
        $this->travelTo(now('Africa/Algiers')->setDate(2026, 9, 21)->startOfDay());
        $u = $this->actor();
        $v = app(Actions::class)->save($u, 'vehicle', $this->vehicle());
        foreach ([-1 => 'expired', 0 => 'due_7', 7 => 'due_7', 8 => 'due_15', 15 => 'due_15', 16 => 'due_30', 30 => 'due_30', 31 => 'valid'] as $days => $status) {
            $d = app(Actions::class)->upload($u, 'vehicle', $v->id, ['type' => 'insurance', 'file' => $this->pdf(), 'expires_at' => now('Africa/Algiers')->addDays($days)->format('Y-m-d')]);
            $this->assertSame($status, $d->expiry_status);
        }
        $this->actingAs($u)->getJson('/api/v1/alerts')->assertOk()->assertJsonCount(7, 'data');
    }

    public function test_catalog_pages_render_in_all_locales(): void
    {
        $u = $this->actor();
        $v = app(Actions::class)->save($u, 'vehicle', $this->vehicle());
        $c = app(Actions::class)->save($u, 'customer', ['type' => 'individual', 'name' => 'Demo']);
        foreach (['fr', 'ar', 'en'] as $locale) {
            $u->update(['locale' => $locale]);
            $this->actingAs($u);
            foreach (['/vehicles', '/vehicles/'.$v->id, '/customers', '/customers/'.$c->id] as $url) {
                $this->get($url)->assertOk();
            }
        }
    }

    public function test_livewire_uses_shared_create_and_permission_rules(): void
    {
        $this->actingAs($this->actor());
        Livewire::test(CatalogDirectory::class, ['kind' => 'vehicle'])->set('form', $this->vehicle())->call('save')->assertHasNoErrors();
        $this->assertDatabaseCount('vehicles', 1);
        $this->actingAs($this->actor('finance'));
        Livewire::test(CatalogDirectory::class, ['kind' => 'vehicle'])->set('form', $this->vehicle())->call('save')->assertForbidden();
    }

    public function test_guests_cannot_access_documents_or_records(): void
    {
        $this->get('/vehicles')->assertRedirect('/login');
        $this->getJson('/api/v1/customers')->assertUnauthorized();
        $this->get('/documents/1')->assertRedirect('/login');
    }

    public function test_livewire_upload_uses_authenticated_temporary_route(): void
    {
        Storage::fake('local');
        $u = $this->actor();
        $v = app(Actions::class)->save($u, 'vehicle', $this->vehicle());
        $this->actingAs($u);
        Livewire::test(CatalogProfile::class, ['kind' => 'vehicle', 'id' => $v->id])->set('file', $this->pdf())->set('documentType', 'insurance')->call('saveDocument')->assertHasNoErrors();
        $this->assertDatabaseCount('documents', 1);
    }
}
