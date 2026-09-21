<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\Vehicle;
use App\Models\VehicleCategory;
use Illuminate\Database\Seeder;

class CatalogDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new \RuntimeException('Synthetic catalogue is local/test only.');
        }
        $category = VehicleCategory::firstOrCreate(['name' => 'Économique automatique']);
        Vehicle::firstOrCreate(['registration' => 'DEMO001'], ['make' => 'Renault', 'model' => 'Clio', 'year' => 2022, 'category_id' => $category->id, 'fuel_type' => 'petrol', 'transmission' => 'automatic', 'mileage_km' => 64230, 'daily_rate' => '8000.00', 'weekly_rate' => '50000.00', 'entered_service_at' => '2026-01-01', 'notes' => 'Fictional demonstration vehicle.']);
        Vehicle::firstOrCreate(['registration' => 'DEMO002'], ['make' => 'Peugeot', 'model' => '208', 'year' => 2023, 'category_id' => $category->id, 'fuel_type' => 'petrol', 'transmission' => 'automatic', 'mileage_km' => 31800, 'daily_rate' => '9000.00', 'entered_service_at' => '2026-01-01', 'notes' => 'Fictional demonstration vehicle.']);
        Customer::firstOrCreate(['name' => 'Client Démo'], ['type' => 'individual', 'phone' => '0555 00 00 01', 'phone_normalized' => '0555000001', 'address' => 'Adresse fictive — Alger']);
    }
}
