<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\Document;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleCategory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

class RentalDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new \RuntimeException('Synthetic rental fixtures are local/test only.');
        }
        $actor = User::where('email', 'manager@demo.test')->firstOrFail();
        $category = VehicleCategory::firstOrCreate(['name' => 'Rental workflow demo']);
        $car = Vehicle::firstOrCreate(['registration' => 'RENTALDEMO01'], ['make' => 'Renault', 'model' => 'Clio', 'year' => 2024, 'category_id' => $category->id, 'fuel_type' => 'petrol', 'transmission' => 'automatic', 'mileage_km' => 100, 'daily_rate' => '8000', 'weekly_rate' => '50000', 'monthly_rate' => '180000', 'notes' => 'Fictional Phase 4 workflow vehicle.']);
        $driver = Customer::firstOrCreate(['name' => 'Fictional Rental Driver'], ['type' => 'individual', 'birth_date' => '1990-01-01', 'phone' => '0555000999', 'address' => 'Fictional demonstration address', 'identity_type' => 'passport', 'identity_number' => 'SAMPLE-NOT-VALID', 'identity_issue_date' => '2020-01-01', 'identity_expiry_date' => '2035-12-31', 'licence_number' => 'SAMPLE-NOT-VALID', 'licence_issue_date' => '2010-01-01', 'licence_expiry_date' => '2035-12-31', 'licence_country' => 'DZ']);
        // A tiny synthetic image, never an actual identity or legal document.
        $path = 'demo/rental-placeholder.png';
        Storage::disk('local')->put($path, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII='));
        foreach (['insurance', 'inspection', 'identity', 'licence'] as $type) {
            $owner = in_array($type, ['insurance', 'inspection']) ? ['vehicle_id' => $car->id] : ['customer_id' => $driver->id];
            Document::firstOrCreate($owner + ['type' => $type, 'path' => $path], ['original_name' => 'SAMPLE-NOT-VALID.png', 'mime' => 'image/png', 'size' => Storage::disk('local')->size($path), 'expires_at' => '2035-12-31', 'uploaded_by' => $actor->id]);
        }
    }
}
