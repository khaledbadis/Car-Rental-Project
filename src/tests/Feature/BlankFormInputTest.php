<?php

namespace Tests\Feature;

use App\Livewire\CatalogDirectory;
use App\Livewire\CatalogProfile;
use App\Livewire\Staff;
use App\Models\Customer;
use App\Models\Document;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class BlankFormInputTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create(['role' => 'manager', 'active' => true]));
    }

    private function vehicleForm(): array
    {
        return array_replace(array_fill_keys(array_keys(config('catalog.vehicle')), ''), ['registration' => ' BLANK-01 ', 'make' => 'Test', 'model' => 'Car', 'year' => 2026, 'category_id' => VehicleCategory::firstOrCreate(['name' => 'Test'])->id, 'fuel_type' => 'petrol', 'transmission' => 'automatic', 'mileage_km' => 0, 'daily_rate' => '50000']);
    }

    public function test_vehicle_create_and_edit_with_blank_optional_numbers_and_dates(): void
    {
        Livewire::test(CatalogDirectory::class, ['kind' => 'vehicle'])->set('form', $this->vehicleForm())->call('save')->assertHasNoErrors()->assertRedirect();
        $v = Vehicle::firstOrFail();
        $this->assertNull($v->weekly_rate);
        $this->assertNull($v->monthly_rate);
        $this->assertNull($v->entered_service_at);
        $v->update(['weekly_rate' => '80000', 'monthly_rate' => '200000', 'entered_service_at' => '2026-01-01']);
        Livewire::test(CatalogProfile::class, ['kind' => 'vehicle', 'id' => $v->id])->set('form.weekly_rate', '')->set('form.monthly_rate', '  ')->set('form.entered_service_at', '')->set('form.daily_rate', '0')->set('reason', 'Clear optional fields')->call('save')->assertHasNoErrors();
        $v->refresh();
        $this->assertNull($v->weekly_rate);
        $this->assertNull($v->monthly_rate);
        $this->assertNull($v->entered_service_at);
        $this->assertSame('0.00', $v->daily_rate);
    }

    public function test_customer_forms_accept_blank_dates_and_driver_ids(): void
    {
        $form = array_replace(array_fill_keys(array_keys(config('catalog.customer')), ''), ['type' => 'individual', 'name' => ' Test Customer ']);
        Livewire::test(CatalogDirectory::class, ['kind' => 'customer'])->set('form', $form)->call('save')->assertHasNoErrors()->assertRedirect();
        $c = Customer::firstOrFail();
        $this->assertNull($c->birth_date);
        $this->assertNull($c->licence_expiry_date);
        $this->assertSame('Test Customer', $c->name);
        Livewire::test(CatalogProfile::class, ['kind' => 'customer', 'id' => $c->id])->set('form.birth_date', '')->set('form.identity_expiry_date', '')->set('form.licence_issue_date', '')->set('reason', 'Update blank dates')->call('save')->assertHasNoErrors();
        $form['type'] = 'company';
        $form['name'] = 'Test Company';
        Livewire::test(CatalogDirectory::class, ['kind' => 'customer'])->set('form', $form)->call('save')->assertHasNoErrors()->assertRedirect();
        $this->assertNull(Customer::where('type', 'company')->firstOrFail()->driver_id);
    }

    public function test_document_upload_accepts_cleared_expiry(): void
    {
        Storage::fake('local');
        $c = Customer::create(['type' => 'individual', 'name' => 'Document test']);
        $file = UploadedFile::fake()->createWithContent('id.pdf', "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n%%EOF");
        Livewire::test(CatalogProfile::class, ['kind' => 'customer', 'id' => $c->id])->set('file', $file)->set('documentType', 'identity')->set('expiresAt', '')->call('saveDocument')->assertHasNoErrors();
        $this->assertNull(Document::firstOrFail()->expires_at);
    }

    public function test_staff_create_and_edit_without_password_reset(): void
    {
        $password = '  TestPass!2026  ';
        Livewire::test(Staff::class)->set('name', ' Test Staff ')->set('email', 'STAFF@example.test')->set('password', $password)->set('reason', 'Test account')->call('save')->assertHasNoErrors();
        $u = User::where('email', 'staff@example.test')->firstOrFail();
        $hash = $u->password;
        $this->assertTrue(Hash::check($password, $hash));
        $this->assertSame('Test Staff', $u->name);
        Livewire::test(Staff::class)->call('edit', $u->id)->set('name', 'Updated Staff')->set('password', '')->set('reason', 'Rename only')->call('save')->assertHasNoErrors();
        $this->assertSame($hash, $u->fresh()->password);
        Livewire::test(Staff::class)->set('name', 'Invalid')->set('email', 'invalid@example.test')->set('password', '')->set('reason', 'Test validation')->call('save')->assertHasErrors(['password']);
    }

    public function test_invalid_values_still_get_field_errors_and_api_matches_web(): void
    {
        $form = $this->vehicleForm();
        Livewire::test(CatalogDirectory::class, ['kind' => 'vehicle'])->set('form', array_replace($form, ['weekly_rate' => 'not money']))->call('save')->assertHasErrors(['form.weekly_rate']);
        $this->postJson('/api/v1/vehicles',$form)->assertCreated()->assertJsonPath('data.weekly_rate',null)->assertJsonPath('data.entered_service_at',null);
    }
}
